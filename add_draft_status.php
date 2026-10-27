<?php
require_once 'inc/config.php';

$sql = "INSERT IGNORE INTO order_statuses (id, status_key, status_name, sort_order, color_class, icon_class) 
        VALUES (9, 'draft', 'پیش‌نویس', 0, 'bg-info', 'bi-pencil-square')";

if ($conn->query($sql)) {
    echo "وضعیت 'پیش‌نویس' با موفقیت اضافه شد یا قبلا وجود داشته است.";
} else {
    echo "خطا در اضافه کردن وضعیت: " . $conn->error;
}
?>
