<?php
require_once 'inc/config.php';

// بررسی وضعیت لاگین بودن کاربر
if (isset($_SESSION['seller_id'])) {
    // اگر لاگین بود، هدایت به داشبورد
    header("Location: dashboard.php");
} else {
    // اگر لاگین نبود، هدایت به صفحه ورود
    header("Location: login.php");
}
exit();