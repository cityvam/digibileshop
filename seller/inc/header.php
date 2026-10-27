<?php
require_once __DIR__ . '/config.php'; // این فایل شامل توابع و jdf نیز می‌باشد
require_once __DIR__ . '/seller_auth.php'; // کنترل دسترسی

// TODO: این کوئری باید بر اساس ساختار جدول اعلانات شما تکمیل شود
// مثال: واکشی تعداد اعلانات خوانده نشده
// $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE seller_id = ? AND is_read = 0");
// $stmt_notif->execute([$_SESSION['seller_id']]);
// $unread_notifications_count = $stmt_notif->fetchColumn();
$unread_notifications_count = 3; // مقدار نمونه برای نمایش

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل فروشندگان</title>
    
    <!-- استفاده از استایل‌های پروژه اصلی -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/Vazirmatn-font-face.css">
    
    <!-- ** فایل CSS جدید سایدبار ** -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/sidebar.css">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #fafcff; /* رنگ پس‌زمینه جدید */
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
    </style>

    <?php if (isset($page_styles)) echo $page_styles; ?>
</head>
<body>

<div class="main-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="content-wrapper">
        <!-- Topbar -->
        <header class="topbar">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- بخش سمت راست: جستجو و فیلترها -->
                    <div class="d-flex align-items-center">
                        <!-- <div class="topbar-search">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" placeholder="جستجو کنید ...">
                        </div>
                        <div class="dropdown ms-3">
                            <a class="btn btn-outline-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                پروژه
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">پروژه آلفا</a></li>
                                <li><a class="dropdown-item" href="#">پروژه بتا</a></li>
                            </ul>
                        </div>
                        <div class="dropdown ms-2">
                            <a class="btn btn-outline-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                شعبه
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">شعبه مرکزی</a></li>
                                <li><a class="dropdown-item" href="#">شعبه اصفهان</a></li>
                            </ul>
                        </div> -->
                    </div>

                    <!-- بخش سمت چپ: آیکن&zwnj;ها و پروفایل کاربر -->
                    <div class="d-flex align-items-center">
                        <div class="topbar-icons">
                            <!-- آیکن وضعیت اتصال به اینترنت -->
                            <a href="#" id="network-status-icon" class="topbar-icon-btn" title="آنلاین">
                                <i class="bi bi-reception-4 text-success"></i>
                            </a>
                            <!-- آیکن جدید برای حالت حریم خصوصی -->
                            <a href="#" id="privacy-toggle-btn" class="topbar-icon-btn" title="حالت حریم خصوصی"><i class="bi bi-eye"></i></a>
                            <a href="<?php echo BASE_URL; ?>settings.php" class="topbar-icon-btn" title="تنظیمات"><i class="bi bi-gear"></i></a>
                            <a href="#" class="topbar-icon-btn"><i class="bi bi-bell"></i></a>
                            <!-- آیکن تقویم با نمایش روز جاری -->
                            <a href="#" class="topbar-icon-btn position-relative" title="تقویم">
                                <i class="bi bi-calendar"></i>
                                <span class="position-absolute top-50 start-50 translate-middle" style="font-size: 0.7em; font-weight: bold; margin-top: 0.5px;">۱۶</span>
                            </a>
                        </div>
                        <div class="vr mx-3"></div>
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo BASE_URL; ?>img/topbar_avatar.jpg" alt="Avatar" width="40" height="40" class="rounded-circle">
                                <div class="me-2 text-end">
                                    <div class="fw-bold">فروشنده محترم</div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['seller_shop_name'] ?? 'مدیر مجموعه'); ?></div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-start">
                                <li><a class="dropdown-item" href="#">پروفایل</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">خروج</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="main-content p-3 p-md-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>