<?php

class VamipayGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $provider_name;
    private $p_id;
    private $encryption_key;
    private $gateway_url = 'https://payment.vamipay.com/';

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

        // تنظیمات دریافتی از دیتابیس
        $this->provider_name = $this->gateway_settings['provider_name'] ?? '';
        $this->p_id = $this->gateway_settings['p_id'] ?? '';
        $this->encryption_key = $this->gateway_settings['encryption_key'] ?? '';
    }

    /**
     * رمزنگاری داده‌ها طبق مستندات (AES-256-ECB)
     */
    private function encrypt($data) {
        return openssl_encrypt($data, 'AES-256-ECB', $this->encryption_key, 0);
    }

    /**
     * رمزگشایی داده‌ها
     */
    private function decrypt($data) {
        return openssl_decrypt($data, 'AES-256-ECB', $this->encryption_key, 0);
    }

    /**
     * درخواست پرداخت
     */
    public function payment_request($amount_rials, $order_id, $callback_url, $description, $mobile = '', $national_code = '') {
        // آماده‌سازی داده‌ها طبق مستندات
        $payload = [
            'mobile' => $mobile,
            'nationalCode' => $national_code,
            'invoiceNumber' => (string)$order_id,
            'callbackUrl' => $callback_url,
            'pId' => (int)$this->p_id
        ];

        $json_payload = json_encode($payload);
        
        // رمزنگاری داده‌ها
        $encrypted_data = $this->encrypt($json_payload);
        
        // ساخت آدرس هدایت
        $redirect_url = $this->gateway_url . $this->provider_name . '?data=' . urlencode($encrypted_data);
        
        // هدایت کاربر
        header("Location: $redirect_url");
        exit();
    }

    /**
     * بررسی نتیجه پرداخت
     */
    public function payment_verify($data) {
        // دریافت پارامتر رمز شده responseData
        $encrypted_response = $data['responseData'] ?? null;
        
        if (!$encrypted_response) {
             return [
                'success' => false,
                'message' => 'پاسخی از درگاه دریافت نشد.'
            ];
        }

        // رمزگشایی
        $decrypted_json = $this->decrypt($encrypted_response);
        $result = json_decode($decrypted_json, true);

        if (!$result) {
             return [
                'success' => false,
                'message' => 'خطا در رمزگشایی پاسخ درگاه.'
            ];
        }

        // بررسی وضعیت
        $status = $result['responseStatus'] ?? false;
        $code = $result['responseCode'] ?? '';
        $order_id = $result['invoiceNumber'] ?? null;
        $rrn = $result['rrn'] ?? '';

        if ($status === true && $code === '00') {
            return [
                'success' => true,
                'order_id' => $order_id,
                'ref_id' => $rrn,
                'message' => 'پرداخت با موفقیت انجام شد.'
            ];
        } else {
            return [
                'success' => false,
                'order_id' => $order_id,
                'message' => 'پرداخت ناموفق بود. کد خطا: ' . $code
            ];
        }
    }
}
?>