<?php
/**
 * SibPay Gateway Handler
 *
 * این فایل مسئولیت مدیریت تمام ارتباطات با API درگاه SibPay (SoftPOS) را بر عهده دارد.
 */

// جلوگیری از تداخل در صورتی که فایل کانفیگ قبلاً فراخوانی شده باشد
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../inc/config.php';
}

class SibPayGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $api_url;
    private $token;
    private $merchant_code;

    // سازنده هماهنگ با order_process.php (دریافت اتصال دیتابیس و اسلاگ)
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

        // نگاشت تنظیمات با پشتیبانی از حالت‌های مختلف نگارش کلیدها در دیتابیس
        $this->token = trim($this->gateway_settings['token'] ?? ($this->gateway_settings['Token'] ?? ''));
        $this->merchant_code = trim($this->gateway_settings['merchant_code'] ?? ($this->gateway_settings['merchantCode'] ?? ($this->gateway_settings['merchantId'] ?? '')));
        $this->api_url = rtrim(trim($this->gateway_settings['api_url'] ?? ''), '/');
    }

    /**
     * مرحله ۱ و ۲: دریافت توکن و هدایت کاربر به درگاه
     * آرگومان‌ها دقیقاً مطابق فراخوانی در order_process.php تنظیم شده‌اند
     */
    public function payment_request(int $amount, int $order_id, string $callback_url, string $description = '', string $mobile = '', string $national_code = ''): void {
        if (empty($this->api_url)) {
            die("خطا: آدرس API (api_url) برای درگاه SibPay تنظیم نشده است.");
        }

        if (empty($this->token) || empty($this->merchant_code)) {
            die("خطا: توکن یا کد پذیرنده برای درگاه SibPay تنظیم نشده است.");
        }

        // اولویت‌ها برای خواندن آدرس بازگشت از تنظیمات دیتابیس (در صورت نیاز به بازنویسی)
        $db_callback = $this->gateway_settings['custom_callback_url'] 
            ?? ($this->gateway_settings['callbackUrl'] 
            ?? ($this->gateway_settings['callback_url'] 
            ?? ($this->gateway_settings['callbackurl'] ?? null)));

        $final_callback_url = !empty($db_callback) ? $db_callback : $callback_url;

        // حذف کاراکترهای غیر الفبایی از شناسه سفارش (الزامات SoftPOS)
        $cleanOrderId = preg_replace('/[^A-Za-z0-9]/', '', (string)$order_id);

        $payload = [
            'token' => (string)$this->token,
            'merchantCode' => (string)$this->merchant_code,
            'productId' => (string)$cleanOrderId,
            'amount' => (int)$amount,
            'callbackUrl' => (string)$final_callback_url,
            'description' => $description,
            'mobile' => $mobile
        ];

        try {
            $url = $this->api_url;
            if (strpos($url, '/webservice/request') === false) {
                $url .= '/webservice/request';
            }
            $response = $this->send_request($url, $payload);

            if (isset($response['result']) && $response['result'] === true && !empty($response['data'])) {
                $data = $response['data'][0];
                
                $redirect_url = is_string($data) ? $data : ($data['url'] ?? ($data['link'] ?? ($data['paymentUrl'] ?? '')));

                if (empty($redirect_url)) {
                    throw new Exception("آدرس پرداخت در پاسخ درگاه یافت نشد.");
                }

                // هدایت کاربر به درگاه پرداخت با نمایش پیام انتظار
                echo '
                <!DOCTYPE html><html lang="fa" dir="rtl">
                <head>
                    <title>در حال انتقال به درگاه پرداخت</title>
                    <meta charset="utf-8">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
                    <style>
                        body { font-family: Vazirmatn, sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
                        .container { max-width: 700px; }
                    </style>
                </head>
                <body>
                    <div class="container text-center">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <h4 class="card-title">در حال انتقال به درگاه پرداخت</h4>
                                <p class="text-muted">لطفاً چند لحظه منتظر بمانید...</p>
                                <div class="spinner-border text-primary my-3" role="status"><span class="visually-hidden">Loading...</span></div>
                                <script>window.location.href = "' . htmlspecialchars($redirect_url) . '";</script>
                            </div>
                        </div>
                    </div>
                </body>
                </html>';
                exit;
            } else {
                $http_code = $response['_http_code'] ?? 'Unknown';
                if (isset($response['_http_code'])) unset($response['_http_code']);
                
                throw new Exception("خطا در اتصال به درگاه SibPay (Status: $http_code): " . json_encode($response, JSON_UNESCAPED_UNICODE));
            }

        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * مرحله ۳: تایید پرداخت پس از بازگشت کاربر از درگاه
     */
    public function payment_verify(array $callback_data): array {
        $order_id = $callback_data['productId'] ?? ($callback_data['orderId'] ?? null);
        $payment_token = $callback_data['paymentToken'] ?? ($callback_data['token'] ?? null);

        if (!$order_id || !$payment_token) {
            return ['success' => false, 'message' => 'اطلاعات بازگشتی از درگاه ناقص است.', 'order_id' => $order_id];
        }

        $cleanOrderId = preg_replace('/[^A-Za-z0-9]/', '', (string)$order_id);

        $payload = [
            'token' => (string)$this->token,
            'merchantCode' => (string)$this->merchant_code,
            'productId' => (string)$cleanOrderId,
            'paymentToken' => (string)$payment_token
        ];

        try {
            $url = $this->api_url;
            if (strpos($url, '/webservice/check') === false) {
                $url .= '/webservice/check';
            }
            $response = $this->send_request($url, $payload);

            if (isset($response['result']) && $response['result'] === true && !empty($response['data'])) {
                $data = $response['data'][0];
                return [
                    'success' => true,
                    'ref_id' => $data['referenceId'] ?? $payment_token,
                    'order_id' => $order_id, // بازگرداندن شماره سفارش برای استفاده در payment_verify.php
                    'message' => 'پرداخت با موفقیت تایید شد.'
                ];
            }

            return ['success' => false, 'message' => 'تایید تراکنش ناموفق بود.', 'order_id' => $order_id];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در تایید تراکنش: ' . $e->getMessage(), 'order_id' => $order_id];
        }
    }

    private function send_request(string $url, array $data): array {
        $json_data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("خطای cURL: " . $curl_error);
        }

        $response_array = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("پاسخ دریافتی از سرور درگاه معتبر نیست.");
        }

        if (is_array($response_array)) {
            $response_array['_http_code'] = $http_code;
        }

        return $response_array;
    }
}
?>