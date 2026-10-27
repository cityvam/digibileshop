<?php
require_once 'inc/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'لطفاً ابتدا وارد حساب خود شوید.']);
    exit();
}

$customer_id = $_SESSION['customer_id'];

// پاک کردن تخفیف از سشن
if (isset($_POST['clear']) && $_POST['clear'] == '1') {
    unset($_SESSION['applied_discount_code']);
    unset($_SESSION['applied_discount_id']);
    echo json_encode(['status' => 'success']);
    exit();
}

if (!isset($_POST['code'])) {
    echo json_encode(['status' => 'error', 'message' => 'کد تخفیف معتبر نیست.']);
    exit();
}

$code = trim($_POST['code']);
$total_price = isset($_POST['total_price']) ? (float)$_POST['total_price'] : 0;

// بررسی کد در دیتابیس
$stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$discount = $stmt->get_result()->fetch_assoc();

if (!$discount) {
    echo json_encode(['status' => 'error', 'message' => 'کد تخفیف معتبر نیست یا منقضی شده است.']);
    exit();
}

// بررسی تاریخ انقضا
$now = date('Y-m-d H:i:s');
if ($discount['start_date'] && $discount['start_date'] > $now) {
    echo json_encode(['status' => 'error', 'message' => 'زمان استفاده از این کد تخفیف هنوز فرا نرسیده است.']);
    exit();
}
if ($discount['expires_at'] && $discount['expires_at'] < $now) {
    echo json_encode(['status' => 'error', 'message' => 'کد تخفیف منقضی شده است.']);
    exit();
}

// بررسی محدودیت تعداد استفاده
$times_used = (int)$discount['times_used'];

// (جدید) اگر کاربر دکمه بک را زده باشد، تعداد استفاده در دیتابیس قبلاً زیاد شده است.
// باید چک کنیم اگر سفارشی با این کد دارد، آن را جزو ظرفیت حساب نکنیم (یعنی یک واحد کم کنیم).
$stmt_draft_disc = $conn->prepare("SELECT id FROM orders WHERE customer_id = ? AND status_id = 1 AND applied_discount_id = ?");
$stmt_draft_disc->bind_param("ii", $customer_id, $discount['id']);
$stmt_draft_disc->execute();
if ($stmt_draft_disc->get_result()->fetch_assoc()) {
    $times_used = max(0, $times_used - 1);
}

if ($discount['usage_limit'] !== null && $times_used >= $discount['usage_limit']) {
    echo json_encode(['status' => 'error', 'message' => 'ظرفیت استفاده از این کد تخفیف به پایان رسیده است.']);
    exit();
}

// بررسی حداقل مبلغ خرید
if ($discount['min_purchase'] > 0 && $total_price < $discount['min_purchase']) {
    echo json_encode(['status' => 'error', 'message' => 'حداقل مبلغ خرید برای استفاده از این کد ' . number_format($discount['min_purchase']) . ' تومان است.']);
    exit();
}

// محاسبه مبلغ تخفیف
$discount_amount = 0;
if ($discount['type'] === 'percentage') {
    $discount_amount = ($total_price * $discount['value']) / 100;
} else {
    $discount_amount = $discount['value'];
}

// تخفیف نمیتواند بیشتر از مبلغ کل باشد
$discount_amount = min($discount_amount, $total_price);

// ذخیره در سشن برای استفاده در order_process
$_SESSION['applied_discount_code'] = $code;
$_SESSION['applied_discount_id'] = $discount['id'];

echo json_encode([
    'status' => 'success',
    'discount_id' => $discount['id'],
    'type' => $discount['type'],
    'value' => (float)$discount['value'],
    'discount_amount' => (float)$discount_amount,
    'message' => 'کد تخفیف با موفقیت اعمال شد.'
]);
