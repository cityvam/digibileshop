<?php
require_once 'inc/config.php';

// اگر کاربر لاگین نکرده، او را به صفحه لاگین بفرست
if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];

// واکشی نام فروشگاه برای نمایش
$stmt = $pdo->prepare("SELECT shop_name FROM sellers WHERE id = ?");
$stmt->execute([$seller_id]);
$shop_name = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام شما در حال بررسی است</title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/Vazirmatn-font-face.css">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .approval-container {
            width: 100%;
            max-width: 550px;
            padding: 40px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .approval-icon {
            font-size: 5rem;
            color: #198754; /* Bootstrap success color */
        }
        .approval-container h3 {
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .approval-container p {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="approval-container">
        <i class="bi bi-check-circle-fill approval-icon"></i>
        <h3><?php echo htmlspecialchars($shop_name ?? 'فروشنده محترم'); ?>، ثبت‌نام شما با موفقیت انجام شد!</h3>
        <p>
            اطلاعات شما برای کارشناسان ما ارسال گردید. پس از بررسی و تایید نهایی، نتیجه از طریق پیامک به شما اطلاع‌رسانی خواهد شد.
            <br>
            از شکیبایی شما سپاسگزاریم.
        </p>
        <div class="mt-4">
            <a href="logout.php" class="btn btn-outline-secondary">خروج از حساب</a>
        </div>
    </div>
</body>
</html>