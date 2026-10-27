<?php
// فعال‌سازی نمایش خطاها برای رفع مشکل صفحه سفید
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// اتصال به فایل‌های اصلی و تنظیمات
require_once 'inc/config.php'; // (اصلاح) اطمینان از اتصال به دیتابیس
require_once 'inc/functions.php'; // فرض بر این است که اتصال دیتابیس ($conn) و ثابت‌ها در اینجا هستند

// دریافت اسلاگ از پارامتر URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$post = null;
$error_message = '';

if (!empty($slug)) {
    // جستجوی پست در دیتابیس بر اساس اسلاگ
    // فرض بر این است که جدول articles وجود دارد
    $stmt = $conn->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published' LIMIT 1");
    
    if ($stmt === false) {
        $error_message = 'خطا در پایگاه داده: ' . htmlspecialchars($conn->error) . '<br>احتمالاً جدول articles وجود ندارد.';
    } else {
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();

        if ($post) {
            // بررسی کوکی برای جلوگیری از افزایش تکراری بازدید در ۲۴ ساعت
            $cookie_name = 'viewed_article_' . $post['id'];
            if (!isset($_COOKIE[$cookie_name])) {
                // افزایش تعداد بازدید
                $stmt_view = $conn->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
                $stmt_view->bind_param("i", $post['id']);
                $stmt_view->execute();
                // تنظیم کوکی برای ۲۴ ساعت (86400 ثانیه)
                setcookie($cookie_name, '1', time() + 86400, "/");
            }
        }
    }
}

include_once 'inc/header.php';    // فراخوانی هدر سایت

if (!empty($error_message)) {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">' . $error_message . '</div></div>';
} elseif ($post) {

        // آماده‌سازی داده‌ها برای نمایش
        $title = htmlspecialchars($post['title']);
        // مسیر تصویر (فرض بر این است که IMAGE_BASE_URL تعریف شده است)
        $image_url = !empty($post['image']) ? IMAGE_BASE_URL . 'upload/posts/' . htmlspecialchars($post['image']) : '';
        $content = $post['content']; // محتوای اصلی پست
        // تبدیل تاریخ در صورت وجود تابع جلالی
        $date = function_exists('to_jalali_date') ? to_jalali_date($post['created_at']) : $post['created_at'];
        ?>

        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <article class="blog-post">
                        <h1 class="mb-3 fw-bold"><?php echo $title; ?></h1>
                        
                        <div class="text-muted mb-4 border-bottom pb-3">
                            <small><i class="bi bi-calendar-event me-1"></i> <?php echo $date; ?></small>
                        </div>

                        <?php if ($image_url): ?>
                            <div class="mb-4">
                                <img src="<?php echo $image_url; ?>" class="img-fluid rounded w-100 shadow-sm" alt="<?php echo $title; ?>">
                            </div>
                        <?php endif; ?>

                        <div class="article-content lh-lg text-justify">
                            <?php echo $content; ?>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <?php
} elseif (empty($slug)) {
    echo '<div class="container py-5"><div class="alert alert-danger text-center">آدرس مقاله نامعتبر است.</div></div>';
} else {
    echo '<div class="container py-5"><div class="alert alert-warning text-center">مقاله مورد نظر یافت نشد یا حذف شده است.</div></div>';
}

include_once 'inc/footer.php'; // فراخوانی فوتر سایت
?>