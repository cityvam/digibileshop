<?php

class AsanpardakhtGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $merchant_config_id;
    private $username;
    private $password;
    private $key;
    private $iv;
    
    // آدرس‌های وب‌سرویس آسان پرداخت
    private $wsdl_url = 'https://services.asanpardakht.net/paygate/merchantservices.asmx?wsdl';
    private $payment_url = 'https://asan.shaparak.ir';

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

        $this->merchant_config_id = $this->gateway_settings['merchant_config_id'] ?? '';
        $this->username = $this->gateway_settings['username'] ?? '';
        $this->password = $this->gateway_settings['password'] ?? '';
        $this->key = $this->gateway_settings['key'] ?? '';
        $this->iv = $this->gateway_settings['iv'] ?? '';
    }

    /**
     * رمزنگاری داده‌ها (AES-256-CBC)
     */
    private function encrypt($data) {
        $key = base64_decode($this->key);
        $iv = base64_decode($this->iv);
        return base64_encode(openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
    }

    /**
     * رمزگشایی داده‌ها
     */
    private function decrypt($data) {
        $key = base64_decode($this->key);
        $iv = base64_decode($this->iv);
        return openssl_decrypt(base64_decode($data), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * درخواست پرداخت
     */
    public function payment_request($amount_rials, $order_id, $callback_url, $description, $mobile = '', $national_code = '') {
        try {
            // تنظیمات برای رفع خطای SSL و کش WSDL
            $options = [
                'trace' => 1,
                'exceptions' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'user_agent' => 'PHPSoapClient',
                'keep_alive' => false,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ])
            ];
            $client = new SoapClient($this->wsdl_url, $options);
            
            // طبق مستندات صفحه ۱۰: P1,P2,P3,P4,P5,P6,P7,P8,P9
            // P1: کد سرویس (1 برای خرید)
            // P2: نام کاربری
            // P3: رمز عبور
            // P4: شناسه سفارش (یکتا و عددی)
            // P5: مبلغ به ریال
            // P6: تاریخ و زمان (YYYYMMDD HHMMSS)
            // P7: توضیحات (تا ۱۰۰ کاراکتر)
            // P8: آدرس بازگشت
            // P9: شناسه پرداخت (0 اگر استفاده نمی‌شود)
            
            $local_date = date("Ymd His"); // توجه: فرمت با فاصله
            
            $data_to_encrypt = "1,{$this->username},{$this->password},{$order_id},{$amount_rials},{$local_date},{$description},{$callback_url},0";
            
            $encrypted_request = $this->encrypt($data_to_encrypt);

            $params = [
                'merchantConfigurationID' => (int)$this->merchant_config_id,
                'encryptedRequest' => $encrypted_request
            ];

            $result = $client->RequestOperation($params);

            // پاسخ به صورت "0,RefId" یا کد خطا است
            $response_str = is_string($result) ? $result : ($result->RequestOperationResult ?? '');
            
            if (substr($response_str, 0, 1) === '0') {
                $parts = explode(',', $response_str);
                $ref_id = $parts[1] ?? ''; // توکن پرداخت

                if (!empty($ref_id)) {
                    // هدایت کاربر به درگاه
                    echo '<form id="asan_payment" action="' . $this->payment_url . '" method="POST">
                            <input type="hidden" name="RefId" value="' . $ref_id . '">
                            <input type="hidden" name="mobileNo" value="' . $mobile . '">
                          </form>
                          <script>document.getElementById("asan_payment").submit();</script>
                          <div style="text-align:center; margin-top:50px; font-family:tahoma;">در حال انتقال به درگاه آسان پرداخت...</div>';
                    exit;
                }
            }
            
            die("خطا در دریافت توکن از آسان پرداخت: " . htmlspecialchars($response_str));

        } catch (Exception $e) {
            die("خطای سیستمی درگاه (Soap Fault): " . $e->getMessage());
        }
    }

    /**
     * بررسی و تایید تراکنش
     */
    public function payment_verify($data) {
        // طبق مستندات صفحه ۱۵، پارامتر ReturningParams به صورت POST برمی‌گردد
        $encrypted_returning_params = $data['ReturningParams'] ?? '';

        if (empty($encrypted_returning_params)) {
             return [
                'success' => false,
                'message' => 'پارامترهای بازگشتی از درگاه دریافت نشد.'
            ];
        }

        // رمزگشایی پارامترها
        // فرمت: Amount,SaleOrderId,RefId,ResCode,MessageText,PayGateTranID,RRN,LastFourDigitsOfPAN
        $decrypted_params = $this->decrypt($encrypted_returning_params);
        $params_array = explode(',', $decrypted_params);

        if (count($params_array) < 4) {
             return [
                'success' => false,
                'message' => 'فرمت پاسخ درگاه نامعتبر است.'
            ];
        }

        $amount = $params_array[0] ?? 0;
        $sale_order_id = $params_array[1] ?? '';
        $ref_id = $params_array[2] ?? '';
        $res_code = $params_array[3] ?? '';
        $pay_gate_tran_id = $params_array[5] ?? ''; // شناسه پیگیری اصلی
        $rrn = $params_array[6] ?? '';

        if ($res_code !== '0' && $res_code !== '00') {
             return [
                'success' => false,
                'order_id' => $sale_order_id,
                'message' => 'پرداخت ناموفق بود یا توسط کاربر لغو شد. کد: ' . $res_code
            ];
        }

        try {
            // تنظیمات برای رفع خطای SSL و کش WSDL
            $options = [
                'trace' => 1,
                'exceptions' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'user_agent' => 'PHPSoapClient',
                'keep_alive' => false,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ])
            ];
            $client = new SoapClient($this->wsdl_url, $options);
            
            // آماده‌سازی پارامترهای تایید (Verification)
            // encryptedCredentials = username,password رمز شده
            $encrypted_credentials = $this->encrypt("{$this->username},{$this->password}");

            $params = [
                'merchantConfigurationID' => (int)$this->merchant_config_id,
                'encryptedCredentials' => $encrypted_credentials,
                'payGateTranID' => (string)$pay_gate_tran_id // طبق PDF صفحه ۱۷ این پارامتر long است اما در SOAP معمولا string پاس می‌شود
            ];

            // مرحله ۱: تایید (Verification)
            $result = $client->VerifyRequest($params);
            $verify_result = is_string($result) ? $result : ($result->RequestVerificationResult ?? '');

            // طبق صفحه ۱۷، کد ۵۰۰ به معنای موفقیت است
            if ($verify_result == '500') {
                // مرحله ۲: تسویه (Reconciliation)
                // طبق صفحه ۱۸، باید RequestReconciliation فراخوانی شود
                $recon_result_obj = $client->RequestReconciliation($params);
                $recon_result = is_string($recon_result_obj) ? $recon_result_obj : ($recon_result_obj->RequestReconciliationResult ?? '');

                // طبق صفحه ۱۸، کد ۶۰۰ به معنای موفقیت است
                if ($recon_result == '600') {
                    return [
                        'success' => true,
                        'order_id' => $sale_order_id,
                        'ref_id' => $rrn,
                        'message' => 'پرداخت با موفقیت تایید و تسویه شد.'
                    ];
                } else {
                    return [
                        'success' => true, // پرداخت وریفای شده ولی ستل نشده، معمولا سفارش را موفق در نظر می‌گیرند
                        'order_id' => $sale_order_id,
                        'ref_id' => $rrn,
                        'message' => 'پرداخت تایید شد اما تسویه خودکار انجام نشد (کد ۶۰۰ دریافت نشد). کد: ' . $recon_result
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'order_id' => $sale_order_id,
                    'message' => 'تراکنش تایید نشد (Verification Failed). کد: ' . $verify_result
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'order_id' => $sale_order_id,
                'message' => 'خطای ارتباط با بانک: ' . $e->getMessage()
            ];
        }
    }
}
?>