<?php

class SadadGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $merchant_id;
    private $terminal_id;
    private $transaction_key;
    
    // آدرس‌های وب‌سرویس سداد
    private $request_url = 'https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest';
    private $verify_url = 'https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify';
    private $payment_url = 'https://sadad.shaparak.ir/VPG/Purchase';

    public function __construct($db, $gateway_slug) {
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

        $this->merchant_id = $this->gateway_settings['merchant_id'] ?? '';
        $this->terminal_id = $this->gateway_settings['terminal_id'] ?? '';
        $this->transaction_key = $this->gateway_settings['transaction_key'] ?? '';
    }

    /**
     * (جدید) تابع لاگ‌گیری برای دیباگ
     */
    private function log_sadad($message) {
        // این تابع به شما کمک می‌کند مقادیر ارسالی به درگاه را بررسی کنید
        $log_file = __DIR__ . '/sadad_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }

    /**
     * تولید امضای دیجیتال (SignData)
     */
    private function generate_sign_data($data) {
        $key = base64_decode($this->transaction_key);
        // (اصلاح) طبق مستندات سداد، امضا با الگوریتم TripleDES (DES-EDE3) انجام می‌شود.
        $ciphertext = openssl_encrypt($data, "DES-EDE3", $key, OPENSSL_RAW_DATA);
        return base64_encode($ciphertext);
    }

    /**
     * ارسال درخواست به سداد (REST)
     */
    private function call_api($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Curl Error: " . $error);
        }

        return json_decode($result, true);
    }

    /**
     * درخواست پرداخت
     */
    public function payment_request($amount_rials, $order_id, $callback_url, $description, $mobile = '', $national_code = '') {
        try {
            $local_date_time = date("m/d/Y g:i:s a");
            $sign_data_str = "{$this->terminal_id};{$order_id};{$amount_rials}";
            
            // (جدید) لاگ کردن اطلاعات قبل از امضا
            $this->log_sadad("--- New Payment Request ---");
            $this->log_sadad("Terminal ID: " . $this->terminal_id);
            $this->log_sadad("Order ID: " . $order_id);
            $this->log_sadad("Amount (Rials): " . $amount_rials);
            $this->log_sadad("String to be signed: " . $sign_data_str);

            $sign_data = $this->generate_sign_data($sign_data_str);
            $this->log_sadad("Generated SignData: " . $sign_data);

            $params = [
                'MerchantId' => $this->merchant_id,
                'TerminalId' => $this->terminal_id,
                'Amount' => $amount_rials,
                'OrderId' => $order_id,
                'LocalDateTime' => $local_date_time,
                'ReturnUrl' => $callback_url,
                'SignData' => $sign_data,
                'AdditionalData' => $description,
                'MultiplexingData' => null,
                'UserId' => $mobile ? (int)$mobile : 0,
                'ApplicationName' => 'StorePro'
            ];

            $result = $this->call_api($this->request_url, $params);
            $this->log_sadad("API Response: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            if (isset($result['ResCode']) && $result['ResCode'] == 0) {
                $token = $result['Token'];
                // هدایت کاربر به درگاه
                $redirect_url = $this->payment_url . "?Token=" . $token;
                header("Location: $redirect_url");
                exit;
            } else {
                $msg = $result['Description'] ?? 'خطای نامشخص';
                $this->log_sadad("Error: " . $msg . " (Code: " . ($result['ResCode'] ?? 'N/A') . ")");
                die("خطا در اتصال به درگاه سداد: " . $msg . " (کد: " . ($result['ResCode'] ?? 'N/A') . ")");
            }

        } catch (Exception $e) {
            $this->log_sadad("EXCEPTION: " . $e->getMessage());
            die("خطای سیستمی درگاه سداد: " . $e->getMessage());
        }
    }

    /**
     * بررسی و تایید تراکنش
     */
    public function payment_verify($data) {
        $token = $data['token'] ?? $data['Token'] ?? null;
        $res_code = $data['ResCode'] ?? null;
        $order_id = $data['OrderId'] ?? null;

        if ($res_code !== '0') {
             return [
                'success' => false,
                'order_id' => $order_id,
                'message' => 'پرداخت ناموفق بود یا توسط کاربر لغو شد. کد خطا: ' . $res_code
            ];
        }

        if (!$token) {
             return [
                'success' => false,
                'order_id' => $order_id,
                'message' => 'توکن پرداخت دریافت نشد.'
            ];
        }

        try {
            $sign_data = $this->generate_sign_data($token);

            $params = [
                'Token' => $token,
                'SignData' => $sign_data
            ];

            $result = $this->call_api($this->verify_url, $params);

            if (isset($result['ResCode']) && $result['ResCode'] == 0) {
                return [
                    'success' => true,
                    'order_id' => $order_id,
                    'ref_id' => $result['RetrivalRefNo'], // شماره مرجع
                    'message' => 'پرداخت با موفقیت انجام شد.'
                ];
            } else {
                return [
                    'success' => false,
                    'order_id' => $order_id,
                    'message' => 'تراکنش تایید نشد. ' . ($result['Description'] ?? '')
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'order_id' => $order_id,
                'message' => 'خطای ارتباط با بانک: ' . $e->getMessage()
            ];
        }
    }
}
?>