<?php
require_once 'inc/config.php';

echo "<h1>اصلاح کدهای رنگ در دیتابیس</h1>";

// حذف فاصله‌های اضافی از ابتدای و انتهای کدهای رنگ
$sql = "UPDATE product_variants SET color_code = TRIM(color_code) WHERE color_code IS NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>تمام کدهای رنگ با موفقیت اصلاح شدند (TRIM شد).</p>";
    echo "<p>تعداد ردیف‌های تغییر یافته: " . $conn->affected_rows . "</p>";
} else {
    echo "<p style='color: red;'>خطا در اصلاح کدهای رنگ: " . $conn->error . "</p>";
}

// اطمینان از اینکه هیچ کد رنگی خالی نیست (اختیاری)
$sql_empty = "UPDATE product_variants SET color_code = NULL WHERE color_code = ''";
$conn->query($sql_empty);

echo "<hr>";
echo "<p>اکنون می‌توانید به صفحه محصولات بازگردید و فیلتر رنگ را تست کنید.</p>";
echo "<a href='products.php'>بازگشت به محصولات</a>";
?>