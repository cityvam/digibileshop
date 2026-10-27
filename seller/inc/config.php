<?php
// شروع session در ابتدای همه صفحات
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- فراخوانی فایل Autoloader کامپوزر (با مسیردهی مطمئن) ---
// ابتدا مسیر ریشه پروژه (پوشه Pro1) را پیدا می‌کنیم و سپس فایل autoload را از آنجا فراخوانی می‌کنیم.
require_once dirname(__DIR__) . '/vendor/autoload.php';

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

// تنظیم منطقه زمانی برای تاریخ شمسی
date_default_timezone_set('Asia/Tehran');

// تعریف آدرس پایه سایت
// این بخش به صورت خودکار آدرس را تشخیص می‌دهد (http یا https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// --- تشخیص هوشمند و مطمئن مسیر پروژه (روش جدید) ---
// این روش با مقایسه مسیر فیزیکی و ریشه وب‌سرور، مسیر پروژه را به درستی پیدا می‌کند.
// این کار تضمین می‌کند که آدرس‌ها هم در لوکال‌هاست و هم در سرور اصلی صحیح باشند.
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$script_directory = str_replace('\\', '/', __DIR__); // مسیر پوشه inc

// مسیر پروژه را با حذف ریشه داکیومنت از مسیر اسکریپت به دست می‌آوریم
$project_path = str_replace($document_root, '', $script_directory);
// مسیر پوشه inc را از انتهای آن حذف می‌کنیم تا به ریشه پروژه برسیم
$project_path = dirname($project_path);

$base_path = rtrim($project_path, '/') . '/';
define('BASE_URL', $protocol . $host . $base_path);

// تعریف آدرس سایت اصلی (سمت مشتری)
$main_site_url = 'https://digibile.com';
if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    // فرض بر این است که در لوکال، پوشه Pro1 هم‌سطح پوشه Seller است
    $main_site_url = $protocol . $host . dirname(rtrim($base_path, '/')) . '/Pro1';
}
define('MAIN_SITE_URL', $main_site_url);

// اتصال به دیتابیس با PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // تنظیم PDO برای نمایش خطاها
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("خطا در اتصال به دیتابیس: " . $e->getMessage());
}

// لود کردن فایل توابع
require_once __DIR__ . '/functions.php';

// واکشی تنظیمات سایت و ذخیره در یک متغیر گلوبال
try {
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $site_settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // اگر جدول تنظیمات وجود نداشت یا خطایی رخ داد، یک آرایه خالی ایجاد کن تا سایت خراب نشود
    $site_settings = [];
    error_log("Could not fetch site settings: " . $e->getMessage());
}

// =================================================================
//  Static Assets Path & URL (روش پویا از دیتابیس)
// =================================================================
// تعریف مسیر و آدرس فایل‌های استاتیک بر اساس تنظیمات دیتابیس
// اگر مقادیر در دیتابیس خالی باشند، از مقادیر پیش‌فرض برای محیط لوکال استفاده می‌شود.

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

$image_root_path = !empty($site_settings['image_root_path']) ? $site_settings['image_root_path'] : (PROJECT_ROOT . '/upload/');
$image_domain_address = !empty($site_settings['image_domain_address']) ? $site_settings['image_domain_address'] : (rtrim(BASE_URL, '/') . '/upload');

// اطمینان از وجود یک اسلش در انتهای مسیر فیزیکی و عدم وجود آن در انتهای آدرس اینترنتی
define('STATIC_ASSETS_PATH', rtrim($image_root_path, '/\\') . '/');
define('STATIC_ASSETS_URL', rtrim($image_domain_address, '/\\'));