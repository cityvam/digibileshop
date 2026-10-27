<?php
require_once 'inc/config.php';

// 1. بررسی اینکه آیا کاربر وارد شده است
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// --- منطق افزودن آدرس جدید ---
if (isset($_POST['add_address'])) {
    // دریافت و پاک‌سازی داده‌ها
    $title = trim($_POST['title']);
    $recipient_name = trim($_POST['recipient_name']);
    $recipient_mobile = trim($_POST['recipient_mobile']);
    $province = trim($_POST['province']);
    $city = trim($_POST['city']);
    $full_address = trim($_POST['full_address']);
    $postal_code = trim($_POST['postal_code']);
    $source_page = $_POST['source'] ?? 'addresses'; // (جدید) تشخیص صفحه مبدا

    // اعتبارسنجی
    if (empty($title) || empty($recipient_name) || empty($recipient_mobile) || empty($province) || empty($city) || empty($full_address)) {
        $_SESSION['message'] = "لطفاً تمام فیلدهای ستاره‌دار را پر کنید.";
        $_SESSION['message_type'] = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO customer_addresses (customer_id, title, recipient_name, recipient_mobile, province, city, full_address, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $customer_id, $title, $recipient_name, $recipient_mobile, $province, $city, $full_address, $postal_code);
        if ($stmt->execute()) {
            $_SESSION['message'] = "آدرس جدید با موفقیت اضافه شد.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "خطایی در افزودن آدرس رخ داد.";
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    // (جدید) بازگشت به صفحه صحیح
    if ($source_page === 'checkout') {
        header("Location: checkout.php");
    } else {
        header("Location: profile/addresses.php");
    }
    exit();
}

// --- منطق ویرایش آدرس ---
if (isset($_POST['update_address'])) {
    $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
    // دریافت و پاک‌سازی داده‌ها
    $title = trim($_POST['title']);
    $recipient_name = trim($_POST['recipient_name']);
    $recipient_mobile = trim($_POST['recipient_mobile']);
    $province = trim($_POST['province']);
    $city = trim($_POST['city']);
    $full_address = trim($_POST['full_address']);
    $postal_code = trim($_POST['postal_code']);

    // اعتبارسنجی
    if (!$address_id || empty($title) || empty($recipient_name) || empty($recipient_mobile) || empty($province) || empty($city) || empty($full_address)) {
        $_SESSION['message'] = "اطلاعات ارسالی نامعتبر است.";
        $_SESSION['message_type'] = "danger";
    } else {
        $stmt = $conn->prepare("UPDATE customer_addresses SET title=?, recipient_name=?, recipient_mobile=?, province=?, city=?, full_address=?, postal_code=? WHERE id=? AND customer_id=?");
        $stmt->bind_param("sssssssii", $title, $recipient_name, $recipient_mobile, $province, $city, $full_address, $postal_code, $address_id, $customer_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "آدرس با موفقیت به‌روزرسانی شد.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "خطایی در به‌روزرسانی آدرس رخ داد.";
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: addresses.php");
    exit();
}

// --- منطق حذف آدرس ---
if (isset($_GET['delete_id'])) {
    $address_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($address_id) {
        $stmt = $conn->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?");
        $stmt->bind_param("ii", $address_id, $customer_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "آدرس با موفقیت حذف شد.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "خطایی در حذف آدرس رخ داد.";
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: addresses.php");
    exit();
}

header("Location: index.php");
exit();
?>