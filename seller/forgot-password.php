<?php
require_once 'inc/config.php';

// اگر کاربر لاگین کرده، او را به داشبورد هدایت کن
if (isset($_SESSION['seller_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'] ?? '';

    if (empty($mobile)) {
        $message = 'لطفاً شماره موبایل خود را وارد کنید.';
        $message_type = 'danger';
        $mobile = ''; // پاک کردن شماره موبایل در صورت خطای اعتبارسنجی
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ? AND status = 'approved'");
            $stmt->execute([$mobile]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($seller) {
                // ایجاد توکن امن و یکتا
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                // تنظیم تاریخ انقضا برای ۱۵ دقیقه بعد
                $expiry_date = date('Y-m-d H:i:s', time() + (15 * 60));

                // ذخیره توکن در دیتابیس
                $stmt_update = $pdo->prepare("UPDATE sellers SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
                $stmt_update->execute([$token_hash, $expiry_date, $seller['id']]);

                // ایجاد لینک بازیابی
                // در یک پروژه واقعی، این لینک باید از طریق SMS ارسال شود.
                // در اینجا ما لینک را برای تست نمایش می‌دهیم.
                $reset_link = rtrim(BASE_URL, '/') . '/reset-password.php?id=' . $seller['id'] . '&token=' . $token;
                
                // ارسال پیامک با استفاده از کاوه‌نگار
                try {
                    $api_key = $site_settings['kavenegar_api_key'] ?? '';
                    $receptor = $mobile;
                    $message_text = "برای بازیابی رمز عبور پنل فروشندگان، روی لینک زیر کلیک کنید:\n" . $reset_link;
                    $sender = "10008663"; // شماره ارسال‌کننده شما در کاوه‌نگار

                    $url = "https://api.kavenegar.com/v1/{$api_key}/sms/send.json?receptor={$receptor}&sender={$sender}&message=" . urlencode($message_text);
                    
                    // استفاده از file_get_contents برای سادگی (در محیط پروداکشن بهتر است از cURL استفاده شود)
                    @file_get_contents($url);

                } catch (Exception $sms_e) {
                    error_log("Kavenegar SMS Error: " . $sms_e->getMessage());
                    // حتی اگر ارسال پیامک خطا داد، به کاربر پیام عمومی نمایش می‌دهیم تا از وجود شماره موبایل مطلع نشود
                }
                $message = 'اگر شماره موبایل وارد شده در سیستم موجود باشد، دستورالعمل بازیابی رمز عبور برای شما ارسال خواهد شد.';
                $message_type = 'info';
                $mobile = ''; // پاک کردن شماره موبایل پس از ارسال (پیام عمومی)
            } else {
                // برای امنیت، حتی اگر شماره موبایل وجود نداشته باشد، پیام موفقیت عمومی نمایش می‌دهیم
                $message = 'اگر شماره موبایل وارد شده در سیستم موجود باشد، دستورالعمل بازیابی رمز عبور برای شما ارسال خواهد شد.';
                $message_type = 'info';
                $mobile = ''; // پاک کردن شماره موبایل برای پیام عمومی
            }
        } catch (Exception $e) {
            $message = 'خطایی در سیستم رخ داده است. لطفاً بعداً تلاش کنید.';
            $message_type = 'danger';
            error_log($e->getMessage());
            $mobile = ''; // پاک کردن شماره موبایل در صورت خطای سیستمی
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بازیابی رمز عبور</title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Vazir', sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            background-color: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .login-branding {
            flex: 1;
            background-image: url('<?php echo BASE_URL; ?>img/sellerLoginBg.18e56434.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
        }

        .login-branding::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.4);
        }

        .branding-content {
            position: relative;
            z-index: 1;
        }

        .branding-content h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; }
        .branding-content p { font-size: 1.1rem; }

        .login-form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
        }

        .login-form-container .logo { max-width: 150px; margin-bottom: 1.5rem; }
        .login-form-container h3 { font-size: 1.8rem; font-weight: 700; color: #333; margin-bottom: 0.5rem; }
        #mobile { direction: ltr; text-align: right; }

        @media (max-width: 768px) {
            .login-container { flex-direction: column; min-height: auto; width: 90%; max-width: 450px; }
            .login-branding { display: none; }
            .login-form-container { padding: 30px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-container">
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL . 'img/logo.png'; ?>" alt="Logo" class="logo">
                <h3>بازیابی رمز عبور</h3>
                <p class="text-muted">شماره موبایل خود را وارد کنید تا لینک بازیابی برایتان ارسال شود.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php">
                <div class="mb-3">
                    <label for="mobile" class="form-label">شماره موبایل</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="text" class="form-control" id="mobile" name="mobile" required autofocus>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">ارسال لینک بازیابی</button>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none small">بازگشت به صفحه ورود</a>
                </div>
            </form>
        </div>
        <div class="login-branding d-none d-md-flex">
            <div class="branding-content">
                <h1>به پنل خود خوش آمدید</h1>
                <p>سفارشات و محصولات خود را به سادگی مدیریت کنید.</p>
            </div>
        </div>
    </div>
</body>
</html>