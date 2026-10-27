<?php
/**
 * SEP (Saman Electronic Payment) Gateway Handler
 *
 * این فایل مسئولیت مدیریت تمام ارتباطات با API درگاه سپ (سامان کیش) را بر عهده دارد.
 */

class SepGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    // آدرس پایه سرویس‌های سپ
    private const API_URL = 'https://sep.shaparak.ir';

    /**
     * (اصلاح) سازنده کلاس اتصال دیتابیس و اسلاگ را دریافت می‌کند.
     */
    public function __construct($db, string $gateway_slug) {
        $this->db = $db;
        $this->gateway_slug = $gateway_slug;
        $this->load_settings();
    }

    /**
     * واکشی تنظیمات بر اساس اسلاگ
     */
    private function load_settings() {
        $stmt = $this->db->prepare("SELECT settings FROM payment_gateways WHERE slug = ?");
        $stmt->bind_param("s", $this->gateway_slug);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $this->gateway_settings = json_decode($result['settings'] ?? '{}', true);
    }

    /**
     * مرحله ۱: ایجاد درخواست پرداخت و هدایت کاربر به درگاه
     *
     * @param int $amount مبلغ به ریال
     * @param int $order_id شماره سفارش
     * @param string $callback_url آدرس بازگشت پس از پرداخت
     * @param string $description توضیحات تراکنش
     * @return void
     */
    public function payment_request(int $amount, int $order_id, string $callback_url, string $description = ''): void {
        $mid = $this->gateway_settings['terminal_id'] ?? '';

        if (empty($mid)) {
            die("تنظیمات درگاه سپ کامل نیست. کد پذیرنده (Terminal ID) الزامی است.");
        }

        $data = [
            'action'      => 'token',
            'TerminalId'  => $mid,
            'Amount'      => $amount, // مبلغ از order_process به ریال ارسال می‌شود
            'ResNum'      => $order_id,
            'RedirectUrl' => $callback_url,
            'CellNumber'  => $_SESSION['user_mobile'] ?? ''
        ];

        try {
            // دریافت توکن پرداخت
            $response = $this->send_request(self::API_URL . '/onlinepg/onlinepg', $data);

            if (isset($response['status']) && $response['status'] == 1 && !empty($response['token'])) {
                $token = $response['token'];
                // هدایت کاربر به صفحه پرداخت سپ
                header("Location: https://sep.shaparak.ir/OnlinePG/SendToken?token=" . $token);
                exit;
            } else {
                $error_message = $response['errorDesc'] ?? 'خطا در دریافت توکن پرداخت.';
                throw new Exception($error_message);
            }
        } catch (Exception $e) {
            die("خطا در ارتباط با درگاه سپ: " . $e->getMessage());
        }
    }

    /**
     * مرحله ۲: تایید پرداخت پس از بازگشت کاربر از درگاه
     *
     * @param array $callback_data اطلاعات بازگشتی از درگاه (معمولاً $_POST)
     * @return array نتیجه تایید پرداخت
     */
    public function payment_verify(array $callback_data): array {
        $mid = $this->gateway_settings['terminal_id'] ?? '';
        
        // پارامترهای بازگشتی استاندارد سپ
        $state = $callback_data['State'] ?? '';
        $refNum = $callback_data['RefNum'] ?? '';
        $resNum = $callback_data['ResNum'] ?? ''; // شماره سفارش
        
        // بررسی وضعیت اولیه
        if ($state !== 'OK') {
            return ['success' => false, 'message' => 'تراکنش ناموفق بود یا توسط کاربر لغو شد. کد وضعیت: ' . $state];
        }

        if (empty($refNum)) {
            return ['success' => false, 'message' => 'شناسه مرجع (RefNum) دریافت نشد.'];
        }

        $data = [
            'RefNum' => $refNum,
            'TerminalNumber' => $mid
        ];

        try {
            $response = $this->send_request(self::API_URL . '/verifyTxnRandomSessionkey/ipg/VerifyTransaction', $data);
            
            // در وب‌سرویس جدید ساختار JSON شامل Success و ResultCode است
            if (is_array($response)) {
                $is_success = (isset($response['Success']) && $response['Success'] === true) || 
                              (isset($response['ResultCode']) && $response['ResultCode'] == 0);
                
                if ($is_success) {
                    return [
                        'success' => true,
                        'ref_id' => $refNum,
                        'message' => 'پرداخت با موفقیت تایید شد.'
                    ];
                }
                $result = $response['ResultCode'] ?? ($response['result'] ?? -1);
            } else {
                $result = $response;
            }

            // فال‌بک برای حالت قدیمی (بازگشت مبلغ)
            if (is_numeric($result) && $result > 0) {
                return [
                    'success' => true,
                    'ref_id' => $refNum,
                    'message' => 'پرداخت با موفقیت تایید شد.'
                ];
            } else {
                $error_map = [
                    -6 => 'بیش از نیم ساعت از زمان اجرای تراکنش گذشته است',
                    -2 => 'تراکنش یافت نشد',
                    -1 => 'خطای داخلی',
                ];
                $msg = $error_map[$result] ?? "کد خطا: $result";
                
                return ['success' => false, 'message' => 'تایید تراکنش ناموفق بود. ' . $msg];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در ارتباط با سرور تایید: ' . $e->getMessage()];
        }
    }

    /**
     * تابع کمکی برای ارسال درخواست cURL
     */
    private function send_request(string $url, array $data) {
        $json_data = json_encode($data);

        // --- DEBUG: ثبت اطلاعات درخواست در لاگ ---
        error_log("SEP Request URL: " . $url);
        error_log("SEP Request Data: " . $json_data);
        // ---------------------------------------

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        // در محیط پروداکشن حتما SSL Verify را فعال کنید
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // --- DEBUG: ثبت پاسخ در لاگ ---
        error_log("SEP Response Code: " . $http_code);
        error_log("SEP Response Body: " . $response_body);
        error_log("SEP cURL Error: " . $curl_error);
        // -----------------------------

        if ($curl_error) {
            throw new Exception("خطای cURL: " . $curl_error);
        }

        $response_array = json_decode($response_body, true);

        // اگر پاسخ JSON معتبر نبود اما یک عدد بود (رفتار خاص برخی متدهای سپ)
        if (json_last_error() !== JSON_ERROR_NONE) {
             if (is_numeric($response_body)) {
                 return $response_body;
             }
             // اگر خطای HTML یا چیز دیگری بود
             $clean_error = strip_tags($response_body);
             throw new Exception("پاسخ نامعتبر از درگاه: " . substr($clean_error, 0, 200));
        }

        return $response_array;
    }
}
?>
