<?php
// تنظیمات کوکی سشن برای جلوگیری از پریدن لاگین هنگام بازگشت از درگاه (SameSite issue)
if (PHP_VERSION_ID >= 70300) {
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => $is_secure ? 'None' : 'Lax'
    ]);
}

// شروع session برای مدیریت وضعیت ورود کاربر
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- تشخیص هوشمند محیط و تنظیمات دیتابیس ---
// این کد بررسی می‌کند که آیا پروژه روی کامپیوتر محلی (localhost) اجرا می‌شود یا روی هاست.
if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    // محیط توسعه (کامپیوتر شما)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // در XAMPP معمولا رمز عبور خالی است
    define('DB_NAME', 'pro1_db');
} else {
    // محیط محصول نهایی (هاست شما)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'digibile_maindb');
    define('DB_PASS', 'hQGMrJ6MKcQyy5Lq5797');
    define('DB_NAME', 'digibile_maindb');
}

// ایجاد اتصال به دیتابیس
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// بررسی اتصال
if ($conn->connect_error) {
    die("اتصال به دیتابیس ناموفق بود: " . $conn->connect_error);
}

// تنظیم کاراکتر ست برای پشتیبانی از فارسی
$conn->set_charset("utf8mb4");

// آدرس پایه سایت (در صورت نیاز)
// (تغییر) تعریف داینامیک آدرس پایه سایت برای جلوگیری از خطای مسیردهی
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];

if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    // محیط توسعه (localhost): نام پوشه پروژه را به آدرس اضافه کن
    $project_folder = basename(dirname(__DIR__)); // نام پوشه پروژه را می‌گیرد (store_pro)
    define('BASE_URL', $protocol . $domain_name . '/' . $project_folder . '/');
    // (جدید) برای تصاویر هم از همان آدرس پایه محلی استفاده می‌کنیم
    define('IMAGE_BASE_URL', $protocol . $domain_name . '/' . $project_folder . '/');
} else {
    // محیط هاست: فقط نام دامنه را استفاده کن
    define('BASE_URL', $protocol . $domain_name . '/');
    // (جدید و موقت) برای تصاویر از آدرس استاتیک استفاده کن
    define('IMAGE_BASE_URL', 'https://static.digibile.com/');
}
