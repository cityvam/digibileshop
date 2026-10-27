<?php
/**
 * Parsian Electronic Commerce (PEC) Gateway Handler
 *
 * این فایل مسئولیت مدیریت تمام ارتباطات با API درگاه پرداخت پارسیان را بر عهده دارد.
 * این درگاه از پروتکل SOAP استفاده می‌کند.
 */

class ParsianGateway {
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private $pin; // کد پذیرنده (Merchant ID)

    // آدرس‌های سرویس‌های درگاه پارسیان
    private const SALE_SERVICE_URL = 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?WSDL';
    private const CONFIRM_SERVICE_URL = 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?WSDL';
    private const PAYMENT_URL = 'https://pec.shaparak.ir/NewIPG/';

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

        $this->pin = $this->gateway_settings['pin'] ?? '';
    }

    /**
     * مرحله ۱: ایجاد درخواست پرداخت و هدایت کاربر به درگاه
     *
     * @param int $amount مبلغ به ریال
     * @param int $order_id شماره سفارش
     * @param string $callback_url آدرس بازگشت پس از پرداخت
     * @param string $description توضیحات تراکنش (توسط پارسیان استفاده نمی‌شود)
     * @return void
     */
    public function payment_request(int $amount, int $order_id, string $callback_url, string $description = ''): void {
        if (empty($this->pin)) {
            die("تنظیمات درگاه پارسیان کامل نیست. کد پذیرنده (PIN) الزامی است.");
        }

        if (!class_exists('SoapClient')) {
            die("ماژول SOAP برای ارتباط با درگاه پارسیان مورد نیاز است. لطفاً آن را در php.ini فعال کنید.");
        }

        // درگاه پارسیان مبلغ را به ریال دریافت می‌کند.
        $params = [
            'LoginAccount' => $this->pin,
            'Amount'       => $amount,
            'OrderId'      => $order_id,
            'CallBackUrl'  => $callback_url,
        ];

        try {
            $client = new SoapClient(self::SALE_SERVICE_URL);
            $response = $client->SalePaymentRequest(['requestData' => $params]);

            if (isset($response->SalePaymentRequestResult->Status) && $response->SalePaymentRequestResult->Status === 0) {
                $token = $response->SalePaymentRequestResult->Token;
                // هدایت کاربر به صفحه پرداخت
                header('Location: ' . self::PAYMENT_URL . '?Token=' . $token);
                exit;
            } else {
                $status = $response->SalePaymentRequestResult->Status ?? 'Unknown';
                throw new Exception('خطا در دریافت توکن پرداخت از درگاه پارسیان. ' . $this->get_error_message($status));
            }
        } catch (Exception $e) {
            die("خطا در ارتباط با درگاه پارسیان: " . $e->getMessage());
        }
    }

    /**
     * مرحله ۲: تایید پرداخت پس از بازگشت کاربر از درگاه
     *
     * @param array $callback_data اطلاعات بازگشتی از درگاه (معمولاً $_POST)
     * @return array نتیجه تایید پرداخت
     */
    public function payment_verify(array $callback_data): array {
        if (empty($this->pin)) {
            return ['success' => false, 'message' => 'تنظیمات درگاه پارسیان کامل نیست.'];
        }

        $token = $callback_data['Token'] ?? null;
        $status = $callback_data['status'] ?? null; // پارسیان از status با حرف کوچک استفاده می‌کند
        $rrn = $callback_data['RRN'] ?? null;

        if ($status === null || (int)$status !== 0) {
            return ['success' => false, 'message' => 'تراکنش ناموفق بود یا توسط کاربر لغو شد. ' . $this->get_error_message((int)$status)];
        }

        if (empty($token)) {
            return ['success' => false, 'message' => 'توکن بازگشتی از درگاه دریافت نشد.'];
        }

        $params = [
            'LoginAccount' => $this->pin,
            'Token'        => $token,
        ];

        try {
            $client = new SoapClient(self::CONFIRM_SERVICE_URL);
            $response = $client->ConfirmPayment(['requestData' => $params]);

            if (isset($response->ConfirmPaymentResult->Status) && $response->ConfirmPaymentResult->Status === 0) {
                return [
                    'success' => true,
                    'ref_id' => $rrn, // RRN کد رهگیری است
                    'message' => 'پرداخت با موفقیت تایید شد.'
                ];
            } else {
                $status = $response->ConfirmPaymentResult->Status ?? 'Unknown';
                return ['success' => false, 'message' => 'تایید تراکنش ناموفق بود. ' . $this->get_error_message($status)];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در ارتباط با سرور تایید پارسیان: ' . $e->getMessage()];
        }
    }

    /**
     * تابع کمکی برای ترجمه کدهای خطای پارسیان
     */
    private function get_error_message(int $status_code): string {
        $messages = [
            0 => 'موفق',
            -1 => 'خطای داخلی سیستم',
            -100 => 'پارامترهای ارسالی صحیح نمی باشد',
            -101 => 'طول رشته درخواست غیر معتبر است',
            -102 => 'IP یا PIN پذیرنده صحیح نمی باشد',
            -103 => 'توکن تکراری است',
            -111 => 'زمان انجام تراکنش به پایان رسیده است',
            -112 => 'تراکنش اصلی پیدا نشد',
            -113 => 'تراکنش قبلا تایید شده است',
            -114 => 'تراکنش قبلا برگشت خورده است',
            -115 => 'مبلغ تراکنش اصلی با مبلغ درخواستی برگشت وجه مغایرت دارد',
            -116 => 'تراکنش مورد نظر تایید و یا برگشت داده شده است',
            -117 => 'تراکنش مورد نظر تایید نشده است',
            -119 => 'تراکنش اصلی پیدا نشد',
            -120 => 'مبلغ تراکنش برگشتی بیش از مبلغ تراکنش اصلی می باشد',
            -121 => 'تراکنش درخواستی در سیستم وجود ندارد',
        ];

        return $messages[$status_code] ?? 'خطای تعریف نشده. کد: ' . $status_code;
    }
}
?>