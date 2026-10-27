<?php
// (اصلاح) فایل‌های کانفیگ و توابع را به ابتدای فایل منتقل می‌کنیم
// تا session قبل از هر شرطی در دسترس باشد.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// (جدید) حذف کالاهای منقضی شده از سبد خرید
cleanup_expired_cart_items();

// (جدید) همگام‌سازی دوطرفه: اگر سفارش پیش‌نویس در دیتابیس حذف شده باشد، سبد خرید را پاک کن
if (isset($_SESSION['customer_id'])) {
    validate_customer_cart_from_db($_SESSION['customer_id']);
}

// (اصلاح) شرط نمایش نوار ادمین به محل استفاده منتقل شد تا از تداخل متغیر جلوگیری شود.

// --- (جدید) استایل‌های مربوط به نوار ادمین ---
// (تغییر) شرط به صورت مستقیم در اینجا بررسی می‌شود.
if (isset($_SESSION['is_admin_logged_in']) && ($_SESSION['is_admin_logged_in'] == 1 || $_SESSION['is_admin_logged_in'] == 2)) {
    echo <<<HTML
    <style>
        /* (اصلاح) استایل نوار افقی ادمین */
        .admin-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 40px; /* ارتفاع نوار */
            background-color: #212529; /* رنگ تیره */
            display: flex;
            flex-direction: row; /* چیدمان افقی */
            align-items: center;
            padding: 0 20px; /* پدینگ داخلی */
            z-index: 1050; /* بالاتر از سایر عناصر */
            justify-content: space-between; /* برای قرار دادن آیتم‌ها در دو طرف */
        }
        .admin-bar a {
            color: #f8f9fa;
            font-size: 0.9rem; /* اندازه فونت آیکون‌ها و متن */
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px; /* فاصله بین آیکون و متن */
        }
        .admin-bar .admin-bar-left, .admin-bar .admin-bar-right {
            display: flex;
            align-items: center;
            gap: 20px; /* فاصله بین لینک‌ها */
        }
        body {
            margin-top: 40px !important; /* به اندازه ارتفاع نوار، به بدنه سایت مارجین می‌دهیم */
        }
    </style>
HTML;
}

// (اصلاح) شمارش تعداد کل محصولات در سبد خرید با استفاده از تابع کمکی
$cart_item_count = get_cart_item_count();

// (جدید) واکشی آیتم‌های سبد خرید برای پیش‌نمایش
$cart_preview_items = [];
$cart_total_price = 0;
if ($cart_item_count > 0 && !empty($_SESSION['cart'])) {
    $cart_ids = array_keys($_SESSION['cart']);
    if (!empty($cart_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($cart_ids), '?'));
        $types = str_repeat('i', count($cart_ids));
        
        $stmt_cart = $conn->prepare("
            SELECT po.id as offer_id, po.price, p.name, p.image 
            FROM product_offers po 
            JOIN product_variants pv ON po.variant_id = pv.id 
            JOIN products p ON pv.product_id = p.id 
            WHERE po.id IN ($ids_placeholder)
        ");
        $stmt_cart->bind_param($types, ...$cart_ids);
        $stmt_cart->execute();
        $res_cart = $stmt_cart->get_result();
        
        while ($row = $res_cart->fetch_assoc()) {
            $qty = $_SESSION['cart'][$row['offer_id']]['quantity'];
            $row['quantity'] = $qty;
            $cart_preview_items[] = $row;
            $cart_total_price += $row['price'] * $qty;
        }
    }
}

// دریافت عنوان صفحه اگر تنظیم شده باشد
$page_title = isset($page_title) ? htmlspecialchars($page_title) . ' | ' : '';

// (جدید) واکشی بنر بالای صفحه
$top_banner = null;
$stmt_banner = $conn->prepare("
    SELECT image_desktop, alt_text, link_url 
    FROM site_banners 
    WHERE position = 'top_banner' AND is_active = 1
    AND (start_date IS NULL OR start_date <= NOW())
    AND (end_date IS NULL OR end_date >= NOW())
    ORDER BY sort_order ASC 
    LIMIT 1
");
$stmt_banner->execute();
$top_banner = $stmt_banner->get_result()->fetch_assoc();
?>
<?php
// (جدید) واکشی تنظیمات اصلی سایت (عنوان، توضیحات، لوگو، فاوآیکون)
$query = "SELECT TRIM(setting_key) as setting_key, setting_value FROM settings WHERE TRIM(setting_key) IN ('site_title', 'site_meta_description', 'site_logo', 'site_favicon', 'site_phone_number')";
$site_settings_result = $conn->query($query);
$site_settings = [];

// --- (جدید) بخش دیباگ برای بررسی نتیجه کوئری ---
echo "<!-- \n";
if ($site_settings_result === false) {
    echo "خطای کوئری تنظیمات سایت: " . $conn->error . "\n";
} else {
    echo "تعداد رکوردهای تنظیمات یافت شده: " . $site_settings_result->num_rows . "\n";
}
echo "-->\n";
// --- پایان بخش دیباگ ---

if ($site_settings_result) {
    while ($row = $site_settings_result->fetch_assoc()) {
        $site_settings[$row['setting_key']] = $row['setting_value'];
    }
}
// تخصیص مقادیر به متغیرها با مقادیر پیش‌فرض
$dynamic_site_title = $site_settings['site_title'] ?? 'فروشگاه دیجی بایل';
$dynamic_meta_description = $site_settings['site_meta_description'] ?? 'فروشگاه اینترنتی دیجی بایل، بررسی، انتخاب و خرید آنلاین.';
$site_logo_url = !empty($site_settings['site_logo']) ? $site_settings['site_logo'] : '';
$site_favicon_url = !empty($site_settings['site_favicon']) ? $site_settings['site_favicon'] : '';
$site_phone_number = $site_settings['site_phone_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . htmlspecialchars($dynamic_site_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($dynamic_meta_description); ?>">
    <?php if ($site_favicon_url): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($site_favicon_url); ?>" type="image/x-icon">
    <?php endif; ?>
    <!-- (تغییر) بارگذاری محلی کتابخانه‌ها برای جلوگیری از خطای شبکه -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/all.min.css"/>
    <!-- (تغییر) Bootstrap Icons به صورت محلی -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.min.css">
    <!-- Vazirmatn Font -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/Vazirmatn-font-face.css">
    <!-- noUiSlider CSS --> 
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/nouislider.min.css">
    <!-- (جدید) Swiper JS CSS for sliders -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/swiper-bundle.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">

    <style>
        /* (جدید) انیمیشن نرم برای باز شدن منوی کشویی */
        .dropdown-menu {
            display: block;
            opacity: 0;
            visibility: hidden;
            margin-top: 10px !important;
            transition: opacity 0.3s ease, margin-top 0.3s ease, visibility 0.3s;
            pointer-events: none;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            margin-top: 0 !important;
            pointer-events: auto;
        }

        /* استایل فوتر موبایل */
        .mobile-footer {
            position: fixed !important;
            bottom: 0 !important;
            left: 0;
            right: 0;
            background-color: #fff;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            padding-bottom: calc(8px + env(safe-area-inset-bottom)); /* پشتیبانی از آیفون */
            z-index: 1040;
            box-shadow: 0 -2px 15px rgba(0,0,0,0.04);
        }
        .mobile-footer .footer-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #6c757d;
            font-size: 10px;
            gap: 4px;
            width: 20%;
        }
        .mobile-footer .footer-item.active {
            color: #212529;
            font-weight: bold;
        }
        .mobile-footer .footer-item i {
            font-size: 1.4rem;
            margin-bottom: 2px;
        }
        /* فاصله پایین صفحه برای جلوگیری از مخفی شدن محتوا پشت فوتر */
        @media (max-width: 991.98px) {
            body {
                padding-bottom: 70px;
            }
            html, body {
                overflow-x: hidden;
                max-width: 100%;
            }
        }
    </style>

</head>
<body>

<!--
<?php
// --- (جدید) رندر کردن نوار ادمین در صورت لاگین بودن ---
// (تغییر) شرط به صورت مستقیم در اینجا بررسی می‌شود.
if (isset($_SESSION['is_admin_logged_in']) && ($_SESSION['is_admin_logged_in'] == 1 || $_SESSION['is_admin_logged_in'] == 2)) {
    // آدرس پنل مدیریت را بر اساس آدرس فعلی سایت حدس می‌زنیم.
    // این آدرس ممکن است نیاز به تنظیم دقیق‌تر داشته باشد.
    $admin_panel_url = str_replace('/store_pro/', '/Pro1/', BASE_URL) . 'dashboard.php';
    echo '
    <div class="admin-bar">
        <div class="admin-bar-left">
            <a href="' . htmlspecialchars($admin_panel_url) . '" title="رفتن به پنل مدیریت" target="_blank" rel="noopener noreferrer"><i class="fa fa-tachometer-alt"></i> <span>پنل مدیریت</span></a>
        </div>
    </div>';
}
?>
-->

<?php // (جدید) نمایش بنر بالای صفحه در صورت وجود ?>
<?php if ($top_banner): ?>
<div class="top-banner">
    <a href="<?php echo htmlspecialchars($top_banner['link_url'] ?? '#'); ?>" target="_blank">
        <img loading="eager" width="1920" height="60" style="object-fit:cover; width: 100%; height: auto;" 
             alt="<?php echo htmlspecialchars($top_banner['alt_text'] ?? 'Banner'); ?>" src="<?php echo IMAGE_BASE_URL . 'banners/' . htmlspecialchars($top_banner['image_desktop'] ?? 'https://via.placeholder.com/1920x60/1b3570/ffffff?text=Your+Banner+Here'); ?>">
    </a>
</div>
<?php endif; ?>

<?php
// نمایش پیام‌های سراسری (اختیاری)
if (isset($_SESSION['message'])) {
    echo '<div class="container-xl mt-3"><div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">' . $_SESSION['message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<header class="main-header bg-white shadow-sm">
    <div class="container-xl">
        <!-- Top Header -->
        <div class="py-2 d-flex justify-content-between align-items-center gap-4">
            <a href="<?php echo BASE_URL; ?>" class="text-dark text-decoration-none">
                <?php if ($site_logo_url): ?>
                    <img src="<?php echo htmlspecialchars($site_logo_url); ?>" alt="<?php echo htmlspecialchars($dynamic_site_title); ?>" style="max-height: 40px;">
                <?php else: ?>
                    <span class="fs-4 fw-bold"><?php echo htmlspecialchars($dynamic_site_title); ?></span> <!-- لوگوی متنی در صورت نبود تصویر -->
                <?php endif; ?>
            </a>
            <!-- (تغییر) ساختار فرم جستجو برای کنترل بهتر عرض -->
            <div class="search-container flex-grow-1 d-none d-lg-block position-relative mx-lg-4">
                <div class="w-75 mx-auto position-relative">
                    <form action="<?php echo BASE_URL; ?>search.php" method="GET" class="search-form">
                        <div class="input-group">
                            <button type="submit" class="input-group-text"><i class="fa fa-search"></i></button>
                            <input type="text" name="q" class="form-control" id="main-search-input" placeholder="جستجو..." autocomplete="off">
                        </div>
                    </form>
                    <div class="search-suggestions-box" id="search-suggestions">
                    <!-- (جدید) بخش نتایج جستجوی زنده -->
                    <div id="live-search-results">
                        <!-- نتایج ایجکس اینجا قرار می‌گیرند -->
                    </div>
                    <!-- بخش جستجوهای اخیر و محبوب -->
                    <div id="default-suggestions" class="p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-muted small mb-0">جستجوهای شما</h6>
                            <button class="btn btn-sm btn-link text-muted small" id="clear-recent-searches" style="display: none;"><i class="fa fa-trash-alt me-1"></i>حذف</button>
                        </div>
                        <div id="recent-searches-container" class="d-flex flex-wrap gap-2"></div>
                        <hr>
                        <h6 class="text-muted small mb-2">جستجوهای محبوب</h6>
                        <?php if (!empty($popular_searches)): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($popular_searches as $search): ?>
                                <a href="<?php echo BASE_URL; ?>search.php?q=<?php echo urlencode($search['term']); ?>" class="btn btn-light btn-sm rounded-pill"><?php echo htmlspecialchars($search['term']); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
            <!-- (جدید) بخش شماره تماس -->
            <?php if (!empty($site_phone_number)): ?>
            <div class="d-none d-lg-flex align-items-center justify-content-end header-contact-info">
                <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^\d+]/', '', $site_phone_number)); ?>" class="text-decoration-none text-dark fw-bold" style="font-size: 21px;"><?php echo to_persian_num(htmlspecialchars($site_phone_number)); ?></a>
            </div>
            <?php endif; ?>

            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['customer_id'])): ?>
                     <div class="dropdown d-inline-block">
                         <a href="#" class="btn me-2 position-relative" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-display="static">
                            <i class="fa fa-shopping-cart"></i>
                            <?php if ($cart_item_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo to_persian_num($cart_item_count); ?></span>
                            <?php endif; ?>
                        </a>
                        <!-- (جدید) باکس پیش‌نمایش سبد خرید -->
                        <div class="dropdown-menu shadow rounded p-3" style="width: 290px;">
                            <?php if (empty($cart_preview_items)): ?>
                                <p class="text-center text-muted mb-0 small py-2">سبد خرید خالی است</p>
                            <?php else: ?>
                                <div class="cart-preview-items custom-scrollbar">
                                    <?php foreach ($cart_preview_items as $item): ?>
                                        <div class="cart-preview-item">
                                            <img src="<?php echo IMAGE_BASE_URL . 'products/' . htmlspecialchars($item['image']); ?>" alt="product">
                                            <div class="ms-2 flex-grow-1 overflow-hidden">
                                                <p class="mb-1 small fw-bold text-truncate"><?php echo htmlspecialchars($item['name']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted"><?php echo to_persian_num($item['quantity']); ?> عدد</small>
                                                    <small class="fw-bold text-primary"><?php echo to_persian_num(number_format($item['price'])); ?> تومان</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="border-top pt-2 mt-2">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-bold">جمع کل:</span>
                                        <span class="small fw-bold text-primary"><?php echo to_persian_num(number_format($cart_total_price)); ?> تومان</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="<?php echo BASE_URL; ?>cart.php" class="btn btn-outline-primary btn-sm w-50">مشاهده سبد</a>
                                        <a href="<?php echo BASE_URL; ?>checkout.php" class="btn btn-primary btn-sm w-50">تسویه حساب</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                     </div>
                     <div class="dropdown">
                        <a href="#" class="btn dropdown-toggle d-flex align-items-center" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,20">
                            <i class="fa fa-user me-2"></i> <span><?php echo htmlspecialchars($_SESSION['customer_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu shadow-lg border-0" style="min-width: 16rem;">
                            <li>
                                <div class="px-3 py-2">
                                    <span class="d-block fw-bold"><?php echo htmlspecialchars($_SESSION['customer_name']); ?></span>
                                    <span class="d-block text-muted small"><?php echo htmlspecialchars($_SESSION['customer_email'] ?? ''); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>profile/profile.php">
                                <i class="bi bi-person-circle fs-5 me-2 text-muted"></i> <span>پروفایل کاربری</span>
                            </a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>profile/my_orders.php">
                                <i class="bi bi-box-seam fs-5 me-2 text-muted"></i> <span>سفارش‌های من</span>
                            </a></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="<?php echo BASE_URL; ?>profile/wishlist.php">
                                <i class="bi bi-heart fs-5 me-2 text-muted"></i> <span>لیست علاقه‌مندی</span>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                                <i class="bi bi-box-arrow-right fs-5 me-2"></i> <span>خروج</span>
                            </a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="dropdown d-inline-block">
                        <a href="#" class="btn me-2 position-relative" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-display="static">
                            <i class="fa fa-shopping-cart"></i>
                            <?php if ($cart_item_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo to_persian_num($cart_item_count); ?></span>
                            <?php endif; ?>
                        </a>
                        <!-- (جدید) باکس پیش‌نمایش سبد خرید -->
                        <div class="dropdown-menu shadow rounded p-3" style="width: 290px;">
                            <?php if (empty($cart_preview_items)): ?>
                                <p class="text-center text-muted mb-0 small py-2">سبد خرید خالی است</p>
                            <?php else: ?>
                                <div class="cart-preview-items custom-scrollbar">
                                    <?php foreach ($cart_preview_items as $item): ?>
                                        <div class="cart-preview-item">
                                            <img src="<?php echo IMAGE_BASE_URL . 'products/' . htmlspecialchars($item['image']); ?>" alt="product">
                                            <div class="ms-2 flex-grow-1 overflow-hidden">
                                                <p class="mb-1 small fw-bold text-truncate"><?php echo htmlspecialchars($item['name']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted"><?php echo to_persian_num($item['quantity']); ?> عدد</small>
                                                    <small class="fw-bold text-primary"><?php echo to_persian_num(number_format($item['price'])); ?> تومان</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="border-top pt-2 mt-2">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-bold">جمع کل:</span>
                                        <span class="small fw-bold text-primary"><?php echo to_persian_num(number_format($cart_total_price)); ?> تومان</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="<?php echo BASE_URL; ?>cart.php" class="btn btn-outline-primary btn-sm w-50">مشاهده سبد</a>
                                        <a href="<?php echo BASE_URL; ?>checkout.php" class="btn btn-primary btn-sm w-50">تسویه حساب</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-primary">ورود | ثبت نام</a>
                <?php endif; ?>
            </div>
        </div>
        <hr class="my-0">
        <!-- Bottom Header / Navbar -->
        <nav class="py-2 d-flex justify-content-between align-items-center">
            <ul class="nav main-nav align-items-center">
                <?php 
                    // فراخوانی فایل مگامنو که اکنون شامل منطق منوی اصلی نیز هست
                    include 'megamenu.php'; 
                    // رندر کردن آیتم‌های منوی اصلی
                    foreach ($main_menu_tree as $menu_item) {
                        render_main_menu_item($menu_item);
                    }
                ?>
            </ul>
            <ul class="nav">
                <!-- <li class="nav-item"><a href="https://seller.digibile.com/login.php" class="nav-link" target="_blank" rel="noopener noreferrer">فروشنده شو</a></li> -->
            </ul>
        </nav>
    </div>
</header>

<!-- Mobile Footer Navigation -->
<div class="mobile-footer d-lg-none">
    <a href="<?php echo BASE_URL; ?>" class="footer-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
        <i class="bi bi-house"></i>
        <span>خانه</span>
    </a>
    <a href="#" class="footer-item" onclick="event.preventDefault(); event.stopPropagation(); document.getElementById('mega-menu-toggle').click();">
        <i class="bi bi-grid"></i>
        <span>دسته‌بندی</span>
    </a>
    <a href="<?php echo BASE_URL; ?>cart.php" class="footer-item position-relative <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">
        <i class="bi bi-cart"></i>
        <span>سبد خرید</span>
        <?php if ($cart_item_count > 0): ?>
            <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem; padding: 0.25em 0.4em; margin-top: 5px; margin-left: 10px;">
                <?php echo to_persian_num($cart_item_count); ?>
            </span>
        <?php endif; ?>
    </a>
    <a href="<?php echo BASE_URL; ?>search.php" class="footer-item <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : ''; ?>">
        <i class="bi bi-search"></i>
        <span>جستجو</span>
    </a>
    <a href="<?php echo isset($_SESSION['customer_id']) ? BASE_URL . 'profile/profile.php' : BASE_URL . 'login.php'; ?>" class="footer-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php' || basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>">
        <i class="bi bi-person"></i>
        <span><?php echo isset($_SESSION['customer_id']) ? 'پروفایل' : 'ورود'; ?></span>
    </a>
</div>
