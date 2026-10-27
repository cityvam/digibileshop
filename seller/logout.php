<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// حذف کوکی "مرا به خاطر بسپار"
if (isset($_COOKIE['remember_me'])) {
    unset($_COOKIE['remember_me']);
    // بهترین راه برای حذف کوکی، ست کردن تاریخ انقضای آن در گذشته است
    setcookie('remember_me', '', time() - 3600, '/');
}

// پاک کردن تمام متغیرهای سشن
session_unset();
// از بین بردن سشن
session_destroy();

header("Location: login.php");
exit();