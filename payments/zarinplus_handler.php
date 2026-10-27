<?php
/**
 * ZarinPlus Gateway Handler
 *
 * این فایل مسئولیت مدیریت تمام ارتباطات با API درگاه زرین‌پلاس را بر عهده دارد.
 */

// (اصلاح نهایی) استفاده از مسیر مطلق برای جلوگیری از خطا در فراخوانی فایل
require_once __DIR__ . '/../inc/config.php';

class ZarinplusGateway
{
    private $db;
    private $gateway_slug;
    private $gateway_settings;
    private const REQUEST_URL = 'https://api.zarinplus.com/payment/v2/request/';
    private const VERIFY_URL = 'https://api.zarinplus.com/payment/v2/verify/';

    /**
     * (اصلاح) سازنده کلاس اتصال دیتابیس و اسلاگ را دریافت می‌کند.
     * @param mysqli $db
     * @param string $gateway_slug
     */
    public function __construct($db, string $gateway_slug)
    {
        $this->db = $db;
        $this->gateway_slug = $gateway_slug;
        $this->load_settings();
    }

    /**
     * واکشی تنظیمات بر اساس اسلاگ
     */
    private function load_settings()
    {
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
     * @throws Exception
     */
    public function payment_request(int $amount, int $order_id, string $callback_url, string $description = ''): void
    {
        $token = $this->gateway_settings['token'] ?? '';
        if (empty($token)) {
            throw new Exception("پیکربندی درگاه زرین‌پلاس کامل نیست. توکن فروشنده الزامی است.");
        }

        // (جدید) شماره سفارش را به آدرس بازگشت اضافه می‌کنیم تا در مرحله تایید به آن دسترسی داشته باشیم
        // این کار باعث می‌شود بتوانیم مبلغ صحیح را از دیتابیس بخوانیم
        $final_callback_url = $callback_url . '&order_id=' . $order_id;

        // اطلاعات کاربر (برای مثال)
        // در یک پروژه واقعی، این اطلاعات باید از دیتابیس یا سشن خوانده شوند
        $mobile = $_SESSION['customer_mobile'] ?? ''; // فرض بر اینکه موبایل در سشن موجود است
        $email = $_SESSION['customer_email'] ?? '';   // فرض بر اینکه ایمیل در سشن موجود است

        $request_data = [
            'amount'    => $amount,
            'token'     => $token,
            'success'   => $final_callback_url,
            'cancel'    => $final_callback_url, // کاربر در صورت انصراف به همین آدرس بازمی‌گردد
            'item'      => $description,
            'cellphone' => $mobile,
            'email'     => $email,
        ];

        try {
            $response = $this->send_request(self::REQUEST_URL, $request_data);

            if (isset($response['status']) && $response['status'] === true && !empty($response['redirect_url'])) {
                header("Location: " . $response['redirect_url']);
                exit;
            } else {
                // (اصلاح) پیام خطا ممکن است آرایه باشد، آن را به JSON تبدیل می‌کنیم
                $error_details = $response['message'] ?? 'خطای نامشخص در ایجاد تراکنش.';
                if (is_array($error_details)) {
                    $error_details = json_encode($error_details, JSON_UNESCAPED_UNICODE);
                }
                throw new Exception("خطا در ارتباط با درگاه زرین‌پلاس: " . $error_details);
            }
        } catch (Exception $e) {
            die("خطا: " . $e->getMessage());
        }
    }

    /**
     * مرحله ۲: تایید پرداخت پس از بازگشت کاربر از درگاه
     *
     * @param array $callback_data اطلاعات بازگشتی از درگاه (در $_GET)
     * @return array نتیجه تایید پرداخت
     */
    public function payment_verify(array $callback_data): array
    {
        global $conn; // (جدید) دسترسی به اتصال دیتابیس برای خواندن مبلغ سفارش

        $token = $this->gateway_settings['token'] ?? '';
        if (empty($token)) {
            return ['success' => false, 'message' => 'پیکربندی درگاه زرین‌پلاس کامل نیست.'];
        }

        // (جدید) شماره سفارش را از پارامترهای بازگشتی می‌خوانیم
        $order_id = $callback_data['order_id'] ?? null;
        $authority = $callback_data['Authority'] ?? null;
        $status = $callback_data['Status'] ?? null;

        // (اصلاح) اگر تراکنش ناموفق بود یا کاربر انصراف داده بود، شماره سفارش را هم برمی‌گردانیم
        if (!$authority || $status !== 'OK' || !$order_id) {
            return [
                'success' => false,
                'message' => 'تراکنش توسط کاربر لغو شد یا ناموفق بود.',
                'order_id' => $order_id
            ];
        }

        // (جدید) خواندن مبلغ سفارش از دیتابیس با استفاده از order_id
        $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_result = $stmt->get_result()->fetch_assoc();

        if (!$order_result || empty($order_result['total_amount'])) {
            return ['success' => false, 'message' => 'مبلغ سفارش برای تایید تراکنش یافت نشد.', 'order_id' => $order_id];
        }
        $amount = (int)$order_result['total_amount'];

        $verify_data = [
            'authority' => $authority,
            'token'     => $token,
            'amount'    => $amount // (اصلاح) ارسال مبلغ خوانده شده از دیتابیس
        ];

        try {
            $response = $this->send_request(self::VERIFY_URL, $verify_data);

            // طبق مستندات زرین‌پلاس، کد 200 به معنی موفقیت است
            if (isset($response['status']) && $response['status'] === true && isset($response['data']['code']) && $response['data']['code'] === 200) {
                return [
                    'success'   => true,
                    'ref_id'    => $response['data']['reference'] ?? $authority,
                    'message'   => 'پرداخت با موفقیت تایید شد.',
                    'order_id'  => $order_id // (مهم) بازگرداندن شماره سفارش در صورت موفقیت
                ];
            } else {
                $error_message = 'تایید تراکنش ناموفق بود.';
                if (isset($response['data']['code']) && $response['data']['code'] === 201) {
                    $error_message = 'این تراکنش قبلا تایید شده است.';
                } elseif (isset($response['message'])) {
                    $error_message .= ' پیام درگاه: ' . json_encode($response['message']);
                }
                return ['success' => false, 'message' => $error_message, 'order_id' => $order_id]; // (مهم) بازگرداندن شماره سفارش در صورت خطا
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطا در ارتباط با سرور تایید: ' . $e->getMessage(), 'order_id' => $order_id];
        }
    }

    /**
     * تابع کمکی برای ارسال درخواست cURL به سرور API
     * @param string $url
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function send_request(string $url, array $data): array
    {
        $json_data = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // در محیط عملیاتی باید true باشد

        $response_body = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("خطای cURL: " . $curl_error);
        }

        $response_array = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("پاسخ دریافتی از سرور درگاه معتبر نیست. پاسخ: " . $response_body);
        }

        return $response_array;
    }
}
?>
```

### ۲. به‌روزرسانی فایل‌های تست و بازگشت از درگاه

برای اینکه سیستم بتواند فایل کنترل‌کننده `zarinplus_handler.php` را پیدا و استفاده کند، باید منطق نگاشت نام (mapping) را در فایل‌های `gateway_test.php` و `payment_callback.php` اصلاح کنیم.

#### `gateway_test.php`

در این فایل، یک شرط برای نگاشت اسلاگ `zarinplus` به فایل `zarinplus_handler.php` اضافه می‌شود.

```diff
--- a/d:/xampp3/htdocs/Pro1/gateways/gateway_test.php
+++ b/d:/xampp3/htdocs/Pro1/gateways/gateway_test.php