<?php
/**
 * Sepehr Gateway Handler (پرداخت الکترونیک سپهر - بانک صادرات)
 *
 * این فایل مسئولیت مدیریت تمام ارتباطات با API درگاه سپهر را بر عهده دارد.
 */

require_once __DIR__ . '/../inc/config.php';

class SepehrGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $terminal_id;
    private $get_token_url;
    private $payment_url;
    private $verify_url;

    /**
     * (اصلاح) سازنده کلاس اتصال دیتابیس و اسلاگ را دریافت می‌کند.
     */
    public function __construct($db, string $gateway_slug) {
        $this->db = $db;
        $this->gateway_slug = $gateway_slug;
        $this->load_settings();
    }

    private function load_settings() {
        $stmt = $this->db->prepare("SELECT settings FROM payment_gateways WHERE slug = ?");
        $stmt->bind_param("s", $this->gateway_slug);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $this->gateway_settings = json_decode($result['settings'] ?? '{}', true);

        $this->terminal_id = $this->gateway_settings['terminal_id'] ?? null;
        $this->get_token_url = $this->gateway_settings['get_token_url'] ?? 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken';
        $this->payment_url = rtrim($this->gateway_settings['payment_url'] ?? 'https://sepehr.shaparak.ir:8080/Pay', '/');
        $this->verify_url = $this->gateway_settings['verify_url'] ?? 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice';
    }

    /**
     * مرحله ۱ و ۲: دریافت توکن و هدایت کاربر به درگاه
     */
    public function payment_request(int $amount, int $order_id, string $callback_url, string $description = ''): void {
        if (empty($this->terminal_id)) {
            die("خطا: شماره ترمینال برای درگاه سپهر پیکربندی نشده است.");
        }

        // مرحله ۱: دریافت توکن
        $token_data = [
            'TerminalID'  => $this->terminal_id,
            'Amount'      => (string)$amount,
            'InvoiceID'   => (string)$order_id,
            'callbackURL' => $callback_url, // (اصلاح) همیشه از آدرس بازگشت ساخته شده در order_process استفاده می‌کنیم
            'payload'     => $description
        ];

        try {
            $token_response = $this->send_request($this->get_token_url, $token_data);

            // بررسی پاسخ توکن
            if (isset($token_response['Status']) && $token_response['Status'] == 0 && !empty($token_response['Accesstoken'])) {
                $token = $token_response['Accesstoken'];
            } else {
                // --- بهبود نمایش خطا ---
                $error_code = (string)($token_response['Status'] ?? '-99');
                $error_message = $this->get_error_message($error_code);
                $full_response_text = json_encode($token_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $final_error = "خطا در ارتباط با درگاه سپهر: " . $error_message . "<hr><pre style='direction: ltr; text-align: left; background-color: #f1f1f1; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($full_response_text) . "</pre>";
                throw new Exception($final_error);
            }

            // مرحله ۲: ساخت فرم و هدایت کاربر
            echo '
                <!DOCTYPE html><html lang="fa" dir="rtl">
                <head>
                    <title>در حال انتقال به درگاه پرداخت</title>
                    <meta charset="utf-8">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
                    <style>
                        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
                        .container { max-width: 700px; }
                        .monospace { direction: ltr; text-align: left; background-color: #e9ecef; padding: 0.5rem 1rem; border-radius: 0.25rem; word-break: break-all; }
                    </style>
                </head>
                <body>
                    <div class="container text-center">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="card-title">در حال انتقال به درگاه پرداخت</h4>
                                <p class="text-muted">لطفاً چند لحظه منتظر بمانید...</p>
                                <div class="spinner-border text-primary my-3" role="status"><span class="visually-hidden">Loading...</span></div>
                                <div class="alert alert-info mt-3">
                                    <p class="mb-1 small"><strong>جهت اطلاع (برای رفع اشکال):</strong> آدرس بازگشتی (Callback URL) که به درگاه ارسال می‌شود:</p>
                                    <p class="monospace small mb-0">' . htmlspecialchars($callback_url) . '</p>
                                </div>
                                <p class="small text-muted mt-2" id="countdown-message">انتقال خودکار تا <span id="countdown-timer">5</span> ثانیه دیگر...</p>
                                <form id="payment_form" method="post" action="' . $this->payment_url . '">
                                    <input type="hidden" name="TerminalID" value="' . htmlspecialchars($this->terminal_id) . '" />
                                    <input type="hidden" name="token" value="' . htmlspecialchars($token) . '" />
                                    <input type="hidden" name="getMethod" value="1" />
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>let count = 5; const timer = setInterval(function() { count--; document.getElementById("countdown-timer").textContent = count; if (count <= 0) { clearInterval(timer); document.getElementById("payment_form").submit(); } }, 1000);</script>
                </body>
                </html>';
            exit;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * مرحله ۳: تایید پرداخت پس از بازگشت کاربر از درگاه
     */
    public function payment_verify(array $callback_data): array {
        if (empty($this->terminal_id)) {
            return ['success' => false, 'message' => 'خطا: شماره ترمینال برای درگاه سپهر پیکربندی نشده است.'];
        }

        // پارامتر digitalreceipt از درگاه برمی‌گردد
        $digital_receipt = $callback_data['digitalreceipt'] ?? null;
        $resp_code = $callback_data['respcode'] ?? null;
        // دریافت شماره سفارش از پارامترهای بازگشتی (معمولاً invoiceid)
        $order_id = $callback_data['invoiceid'] ?? $callback_data['InvoiceID'] ?? null;

        if ($resp_code !== '0') {
            return [
                'success' => false, 
                'message' => $this->get_error_message((string)$resp_code), 
                'order_id' => $order_id,
                'is_cancelled' => ((string)$resp_code === '-1') // تشخیص انصراف کاربر
            ];
        }

        if (empty($digital_receipt)) {
            return ['success' => false, 'message' => 'رسید دیجیتال از درگاه دریافت نشد.', 'order_id' => $order_id];
        }

        $verify_data = [
            'digitalreceipt' => $digital_receipt,
            'Tid'            => $this->terminal_id
        ];

        try {
            $verify_response = $this->send_request($this->verify_url, $verify_data);

            // بررسی پاسخ تایید تراکنش
            if (isset($verify_response['Status']) && $verify_response['Status'] == 0) {
                return [
                    'success' => true,
                    'ref_id'  => $verify_response['RRN'] ?? $digital_receipt, // RRN کد رهگیری اصلی است
                    'message' => 'پرداخت با موفقیت تایید شد.',
                    'order_id' => $order_id
                ];
            } else {
                $error_code = (string)($verify_response['ReturnId'] ?? '-99');
                return ['success' => false, 'message' => 'تایید تراکنش ناموفق بود. ' . $this->get_error_message($error_code), 'order_id' => $order_id];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در ارتباط با سرور تایید: ' . $e->getMessage(), 'order_id' => $order_id];
        }
    }

    /**
     * متد کمکی برای ترجمه کدهای خطا به پیام‌های فارسی
     * @param string $code کد خطا
     * @return string پیام فارسی خطا
     */
    private function get_error_message(string $code): string {
        $messages = [
            // کدهای عمومی شاپرک (که به مرورگر کاربر برمی‌گردد)
            '0'    => 'تراکنش با موفقیت انجام شد.',
            '-1'   => 'کاربر از انجام تراکنش انصراف داد.',
            '5'    => 'تراکنش نامعتبر است. (از انجام تراکنش صرف نظر شده است)',
            '12'   => 'تراکنش نامعتبر است.',
            '14'   => 'شماره کارت وارد شده نامعتبر است.',
            '15'   => 'صادرکننده کارت نامعتبر است.',
            '33'   => 'تاریخ انقضای کارت سپری شده است.',
            '34'   => 'دارنده کارت مظنون به تقلب است (CVV2 یا تاریخ انقضا اشتباه است).',
            '38'   => 'تعداد دفعات ورود رمز اشتباه بیش از حد مجاز است. کارت شما ممکن است مسدود شود.',
            '41'   => 'کارت مفقودی است.',
            '43'   => 'کارت مسروقه است.',
            '51'   => 'موجودی حساب کافی نیست.',
            '54'   => 'تاریخ انقضای کارت سپری شده است.',
            '55'   => 'رمز دوم (رمز پویا) یا رمز اینترنتی اشتباه است.',
            '57'   => 'انجام این تراکنش برای دارنده کارت مجاز نیست.',
            '58'   => 'انجام این تراکنش برای این پایانه (فروشگاه) مجاز نیست.',
            '61'   => 'مبلغ تراکنش بیش از حد مجاز است.',
            '62'   => 'کارت محدود شده است.',
            '65'   => 'تعداد درخواست تراکنش در یک بازه زمانی بیش از حد مجاز است.',
            '68'   => 'زمان پاسخگویی به پایان رسیده است (Timeout). لطفاً مجدداً تلاش کنید.',
            '75'   => 'تعداد دفعات ورود رمز اشتباه بیش از حد مجاز است. کارت شما مسدود شد.',
            '90'   => 'سامانه بانک مقصد در حال انجام عملیات پایان روز می‌باشد. لطفاً چند دقیقه دیگر تلاش کنید.',
            '96'   => 'خطای سیستمی در شبکه پرداخت. لطفاً مجدداً تلاش کنید.',

            // کدهای اختصاصی وب‌سرویس سپهر (برای تایید و بازگشت وجه)
            '-1'   => 'تراکنش در سیستم پیدا نشد.',
            '-2'   => 'خطای ارتباطی: IP سرور شما در لیست IP های مجاز درگاه نیست یا پورت 8081 مسدود است.',
            '-3'   => 'تراکنش قبلاً بازگشت داده شده است (Reverse).',
            '-4'   => 'امکان انجام این درخواست (تایید/بازگشت) برای این تراکنش وجود ندارد.',
            '-5'   => 'آدرس IP سرور شما نامعتبر است.',
            '-6'   => 'سرویس بازگشت وجه برای این فروشگاه فعال نیست.',

            // کد خطای عمومی
            '-99'  => 'خطای تعریف نشده از سمت درگاه پرداخت.'
        ];

        // اگر کد در لیست پیام‌ها وجود داشت، آن را برمی‌گردانیم.
        if (array_key_exists($code, $messages)) {
            return $messages[$code];
        }

        // اگر کد عددی بود و در لیست نبود، یک پیام عمومی‌تر نمایش می‌دهیم.
        if (is_numeric($code)) {
            return 'تراکنش ناموفق بود. کد خطا: ' . $code;
        }

        // اگر کد یک رشته متنی بود (که گاهی از سمت درگاه ارسال می‌شود)، همان را نمایش می‌دهیم.
        return htmlspecialchars($code);
    }

    /**
     * تابع کمکی برای ارسال درخواست cURL به سرور API
     */
    private function send_request(string $url, array $data): array {
        if (!extension_loaded('curl')) {
            throw new Exception("ماژول cURL روی سرور نصب یا فعال نیست.");
        }

        $json_data = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // (جدید) اجبار به استفاده از IPv4 برای جلوگیری از خطای "Could not resolve host" در برخی هاست‌ها
        if (defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        $response_body = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            // (اصلاح) پرتاب استثنا به جای die برای مدیریت بهتر در لایه‌های بالاتر
            throw new Exception("خطای فیزیکی در ارتباط با درگاه (cURL): " . $curl_error);
        }

        $response_array = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("پاسخ دریافتی از سرور درگاه معتبر نیست. پاسخ: " . $response_body);
        }

        return $response_array;
    }
}
?>