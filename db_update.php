<?php
require_once 'inc/config.php';

echo "<h1>به‌روزرسانی پایگاه داده</h1>";

// 1. افزودن ستون payment_ref_id
$col_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_ref_id'");
if ($col_check->num_rows == 0) {
    $sql = "ALTER TABLE orders ADD COLUMN payment_ref_id VARCHAR(255) NULL AFTER total_amount";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>ستون <strong>payment_ref_id</strong> با موفقیت به جدول orders اضافه شد.</p>";
    } else {
        echo "<p style='color: red;'>خطا در افزودن ستون payment_ref_id: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ستون payment_ref_id از قبل وجود دارد.</p>";
}

// 2. افزودن ستون delivery_time_slot_id
$col_check2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_time_slot_id'");
if ($col_check2->num_rows == 0) {
    $sql2 = "ALTER TABLE orders ADD COLUMN delivery_time_slot_id INT NULL AFTER shipping_address";
    if ($conn->query($sql2) === TRUE) {
        echo "<p style='color: green;'>ستون <strong>delivery_time_slot_id</strong> با موفقیت به جدول orders اضافه شد.</p>";
    } else {
        echo "<p style='color: red;'>خطا در افزودن ستون delivery_time_slot_id: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ستون delivery_time_slot_id از قبل وجود دارد.</p>";
}

// 3. افزودن ستون offer_id به جدول order_items (برای رفع خطای فعلی)
$col_check3 = $conn->query("SHOW COLUMNS FROM order_items LIKE 'offer_id'");
if ($col_check3->num_rows == 0) {
    $sql3 = "ALTER TABLE order_items ADD COLUMN offer_id INT NULL AFTER variant_id";
    if ($conn->query($sql3) === TRUE) {
        echo "<p style='color: green;'>ستون <strong>offer_id</strong> با موفقیت به جدول order_items اضافه شد.</p>";
    } else {
        echo "<p style='color: red;'>خطا در افزودن ستون offer_id: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ستون offer_id از قبل وجود دارد.</p>";
}

// 4. افزودن جدول payment_transactions برای لاگ تراکنش‌ها
$table_check = $conn->query("SHOW TABLES LIKE 'payment_transactions'");
if ($table_check->num_rows == 0) {
    $sql4 = "
    CREATE TABLE `payment_transactions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) DEFAULT NULL,
      `gateway_slug` varchar(100) COLLATE utf8mb4_persian_ci NOT NULL,
      `amount` int(11) NOT NULL,
      `status` enum('success','failed','cancelled') COLLATE utf8mb4_persian_ci NOT NULL,
      `ref_id` varchar(255) COLLATE utf8mb4_persian_ci DEFAULT NULL,
      `gateway_response` text COLLATE utf8mb4_persian_ci,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `order_id` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
    ";
    if ($conn->query($sql4) === TRUE) {
        echo "<p style='color: green;'>جدول <strong>payment_transactions</strong> با موفقیت ایجاد شد.</p>";
    } else {
        echo "<p style='color: red;'>خطا در ایجاد جدول payment_transactions: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>جدول payment_transactions از قبل وجود دارد.</p>";
}

echo "<hr><p>عملیات تمام شد. لطفاً این فایل (db_update.php) را حذف کنید و مجدداً یک سفارش جدید ثبت کنید.</p>";
?>