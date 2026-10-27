<?php
// این فایل توسط سرور وامی‌پی فراخوانی می‌شود تا اطلاعات سفارش را استعلام کند.
header('Content-Type: application/json');

// اتصال به دیتابیس
require_once '../inc/config.php';

// (جدید) تابع لاگ‌گیری برای دیباگ
function log_vamipay($message) {
    $log_file = __DIR__ . '/vamipay_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// دریافت پارامتر invoiceId (رمز شده)
$encrypted_invoice_id = $_GET['invoiceId'] ?? '';

log_vamipay("درخواست جدید دریافت شد. پارامترها: " . json_encode($_GET, JSON_UNESCAPED_UNICODE));

if (empty($encrypted_invoice_id)) {
    log_vamipay("خطا: پارامتر invoiceId ارسال نشده است.");
    echo json_encode(['error' => 'InvoiceId is missing']);
    exit;
}

// دریافت کلید رمزنگاری از تنظیمات درگاه
$stmt = $conn->prepare("SELECT settings FROM payment_gateways WHERE slug = 'vamipay_gateway'");
$stmt->execute();
$gateway_data = $stmt->get_result()->fetch_assoc();

if (!$gateway_data) {
    log_vamipay("خطا: تنظیمات درگاه vamipay_gateway یافت نشد.");
    echo json_encode(['error' => 'Gateway settings not found']);
    exit;
}

$settings = json_decode($gateway_data['settings'], true);
$encryption_key = $settings['encryption_key'] ?? '';

// رمزگشایی شناسه سفارش
$order_id = openssl_decrypt($encrypted_invoice_id, 'AES-256-ECB', $encryption_key, 0);

if (!$order_id) {
    log_vamipay("خطا: رمزگشایی invoiceId ناموفق بود. مقدار دریافتی: " . $encrypted_invoice_id);
    echo json_encode(['error' => 'Decryption failed']);
    exit;
}

// جستجوی سفارش در دیتابیس
$stmt_order = $conn->prepare("SELECT id, total_amount FROM orders WHERE id = ?");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();

if ($order) {
    // تبدیل مبلغ به ریال
    $amount_rials = (int)$order['total_amount'] * 10;

    // فرمت خروجی طبق صفحه ۹ مستندات
    $response = [
        "InvoiceId" => (string)$order['id'],
        "Amount" => $amount_rials,
        "ServiceType" => "خرید کالا", // عنوان سرویس
        "ServiceDescription" => "پرداخت سفارش شماره " . $order['id']
    ];
    log_vamipay("موفقیت: سفارش پیدا شد. پاسخ ارسال شد: " . json_encode($response, JSON_UNESCAPED_UNICODE));
    echo json_encode($response);
} else {
    log_vamipay("خطا: سفارشی با شناسه $order_id پیدا نشد.");
    echo json_encode(['error' => 'Order not found']);
}
?>
