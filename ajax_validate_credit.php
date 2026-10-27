<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'لطفاً ابتدا وارد شوید.']);
    exit();
}

if (!isset($_POST['code'])) {
    echo json_encode(['status' => 'error', 'message' => 'کد خرید وارد نشده است.']);
    exit();
}

$code = trim($_POST['code']);
$customer_id = $_SESSION['customer_id'];

// واکشی اطلاعات مشتری برای چک کردن سازمان
$stmt_customer = $conn->prepare("SELECT organization_id FROM customers WHERE id = ?");
$stmt_customer->bind_param("i", $customer_id);
$stmt_customer->execute();
$customer_data = $stmt_customer->get_result()->fetch_assoc();
$org_id = $customer_data['organization_id'] ?? null;

$stmt = $conn->prepare("SELECT * FROM store_credits WHERE code = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$credit = $stmt->get_result()->fetch_assoc();

if (!$credit) {
    echo json_encode(['status' => 'error', 'message' => 'کد خرید معتبر نیست یا غیرفعال شده است.']);
    exit();
}

// بررسی تاریخ انقضا
if ($credit['expires_at'] && strtotime($credit['expires_at']) < time()) {
    echo json_encode(['status' => 'error', 'message' => 'کد خرید منقضی شده است.']);
    exit();
}

// بررسی موجودی
$current_balance = (float)$credit['current_balance'];

// (جدید) اگر کاربر دکمه بک را زده باشد، مبلغ در سفارش درافت (status 1) کسر شده است.
// باید چک کنیم اگر سفارشی با این کد دارد، آن مبلغ را به موجودی مجازی اضافه کنیم.
$stmt_draft = $conn->prepare("SELECT applied_credit_amount FROM orders WHERE customer_id = ? AND status_id = 1 AND applied_credit_id = ?");
$stmt_draft->bind_param("ii", $customer_id, $credit['id']);
$stmt_draft->execute();
$draft_data = $stmt_draft->get_result()->fetch_assoc();
if ($draft_data) {
    $current_balance += (float)$draft_data['applied_credit_amount'];
}

if ($current_balance <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'موجودی این کد خرید به اتمام رسیده است.']);
    exit();
}

// بررسی محدودیت مشتری خاص (customer_id)
if ($credit['customer_id'] && $credit['customer_id'] != $customer_id) {
    echo json_encode(['status' => 'error', 'message' => 'این کد خرید برای شما معتبر نیست.']);
    exit();
}

// بررسی محدودیت لیست مشتریان (customer_ids)
if ($credit['customer_ids']) {
    $allowed_customers = explode(',', $credit['customer_ids']);
    if (!in_array($customer_id, $allowed_customers)) {
        echo json_encode(['status' => 'error', 'message' => 'این کد خرید برای شما صادر نشده است.']);
        exit();
    }
}

// بررسی محدودیت سازمان‌ها (organization_ids)
if ($credit['organization_ids']) {
    $allowed_orgs = explode(',', $credit['organization_ids']);
    if (!$org_id || !in_array($org_id, $allowed_orgs)) {
        echo json_encode(['status' => 'error', 'message' => 'این کد خرید مربوط به سازمان شما نیست.']);
        exit();
    }
}

// پاسخ موفق
echo json_encode([
    'status' => 'success',
    'message' => 'کد خرید معتبر است.',
    'credit_id' => $credit['id'],
    'balance' => (int)$current_balance,
    'title' => $credit['title'],
    'description' => $credit['description']
]);
