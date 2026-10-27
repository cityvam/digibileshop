<?php

class BehpardakhMellatGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $terminal_id;
    private $user_name;
    private $user_password;
    
    // آدرس‌های وب‌سرویس ملت
    private $wsdl_url = 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';
    private $payment_url = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';

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

        $this->terminal_id = $this->gateway_settings['terminal_id'] ?? '';
        $this->user_name = $this->gateway_settings['user_name'] ?? '';
        $this->user_password = $this->gateway_settings['user_password'] ?? '';
    }

    /**
     * درخواست پرداخت (bpPayRequest)
     */
    public function payment_request($amount_rials, $order_id, $callback_url, $description, $mobile = '', $national_code = '') {
        try {
            $client = new SoapClient($this->wsdl_url);
            $params = [
                'terminalId' => $this->terminal_id,
                'userName' => $this->user_name,
                'userPassword' => $this->user_password,
                'orderId' => $order_id,
                'amount' => $amount_rials,
                'localDate' => date('Ymd'),
                'localTime' => date('His'),
                'additionalData' => $description,
                'callBackUrl' => $callback_url,
                'payerId' => 0
            ];

            $result = $client->bpPayRequest($params);
            $response = explode(',', $result->return);
            
            // اگر کد بازگشتی 0 باشد، یعنی درخواست موفق بوده
            if ($response[0] == '0') {
                $ref_id = $response[1];
                
                // هدایت کاربر به درگاه با فرم POST مخفی
                echo '<form id="mellat_payment" action="' . $this->payment_url . '" method="POST">
                        <input type="hidden" name="RefId" value="' . $ref_id . '">
                        <input type="hidden" name="mobileNo" value="' . $mobile . '">
                      </form>
                      <script>document.getElementById("mellat_payment").submit();</script>
                      <div style="text-align:center; margin-top:50px; font-family:tahoma;">در حال انتقال به درگاه بانک ملت...</div>';
                exit;
            } else {
                die("خطا در اتصال به درگاه ملت: " . $this->get_error_message($response[0]));
            }
        } catch (Exception $e) {
            die("خطای سیستمی (Soap Fault): " . $e->getMessage());
        }
    }

    /**
     * بررسی و تایید تراکنش (bpVerifyRequest + bpSettleRequest)
     */
    public function payment_verify($data) {
        $res_code = $data['ResCode'] ?? '';
        $sale_order_id = $data['SaleOrderId'] ?? '';
        $sale_reference_id = $data['SaleReferenceId'] ?? ''; // شماره پیگیری (RRN)

        // اگر پرداخت توسط کاربر لغو شده یا ناموفق بوده
        if ($res_code != '0') {
             return [
                'success' => false,
                'order_id' => $sale_order_id,
                'message' => 'پرداخت ناموفق بود. کد خطا: ' . $this->get_error_message($res_code)
            ];
        }

        try {
            $client = new SoapClient($this->wsdl_url);
            $params = [
                'terminalId' => $this->terminal_id,
                'userName' => $this->user_name,
                'userPassword' => $this->user_password,
                'orderId' => $sale_order_id,
                'saleOrderId' => $sale_order_id,
                'saleReferenceId' => $sale_reference_id
            ];

            // مرحله 1: تایید تراکنش (Verify)
            $verify_result = $client->bpVerifyRequest($params);
            
            if ($verify_result->return == '0') {
                // مرحله 2: ستل کردن تراکنش (Settle) - نهایی سازی
                $settle_result = $client->bpSettleRequest($params);
                
                if ($settle_result->return == '0' || $settle_result->return == '45') { // 45 یعنی قبلا ستل شده
                     return [
                        'success' => true,
                        'order_id' => $sale_order_id,
                        'ref_id' => $sale_reference_id,
                        'message' => 'پرداخت با موفقیت انجام شد.'
                    ];
                } else {
                    // اگر ستل نشد، پول را برگشت بزن (Reversal)
                    $client->bpReversalRequest($params);
                    return [
                        'success' => false,
                        'order_id' => $sale_order_id,
                        'message' => 'تراکنش تایید نهایی نشد (Settle Error). کد: ' . $settle_result->return
                    ];
                }
            } else {
                 return [
                    'success' => false,
                    'order_id' => $sale_order_id,
                    'message' => 'تراکنش تایید نشد (Verify Error). کد: ' . $verify_result->return
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

    private function get_error_message($code) {
        $errors = [
            '0' => 'تراکنش موفق', '11' => 'شماره کارت نامعتبر', '12' => 'موجودی ناکافی',
            '13' => 'رمز نادرست', '14' => 'تعداد دفعات رمز بیش از حد', '15' => 'کارت نامعتبر',
            '16' => 'دفعات برداشت بیش از حد', '17' => 'انصراف کاربر', '18' => 'تاریخ انقضا گذشته',
            '21' => 'پذیرنده نامعتبر', '23' => 'خطای امنیتی', '24' => 'اطلاعات کاربری پذیرنده نامعتبر',
            '25' => 'مبلغ نامعتبر', '31' => 'پاسخ نامعتبر', '33' => 'حساب نامعتبر',
            '34' => 'خطای سیستمی', '41' => 'شماره درخواست تکراری', '43' => 'قبلا وریفای شده',
            '45' => 'قبلا ستل شده', '46' => 'ستل نشده', '48' => 'ریورس شده', '51' => 'تراکنش تکراری',
            '54' => 'تراکنش مرجع موجود نیست', '61' => 'خطا در واریز'
        ];
        return $errors[$code] ?? "کد خطا: $code";
    }
}
?>