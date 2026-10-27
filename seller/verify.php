<?php
require_once 'inc/config.php';

// اگر اطلاعات مرحله قبل در سشن موجود نیست، کاربر را به صفحه ثبت‌نام برگردان
if (!isset($_SESSION['register_mobile'])) {
    header("Location: register.php");
    exit();
}

$error_message = '';
$mobile = $_SESSION['register_mobile'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    $otp = to_english_nums($otp);

    if (empty($otp)) {
        $error_message = 'لطفاً کد تایید را وارد کنید.';
    } elseif (strtotime($_SESSION['register_otp_expiry']) < time()) {
        $error_message = 'کد تایید منقضی شده است. لطفاً مجدداً تلاش کنید.';
        // پاک کردن سشن برای شروع مجدد
        unset($_SESSION['register_mobile'], $_SESSION['register_otp_hash'], $_SESSION['register_otp_expiry']);
    } elseif (!password_verify($otp, $_SESSION['register_otp_hash'])) {
        $error_message = 'کد تایید وارد شده صحیح نیست.';
    } else {
        // کد صحیح است، کاربر را به مرحله نهایی هدایت کن
        $_SESSION['is_mobile_verified'] = true;
        header("Location: complete-registration.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید کد</title>
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
        }

        .login-form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
        }

        .login-form-container h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        #otp { direction: ltr; text-align: center; letter-spacing: 0.5em; }

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
                <h3 class="mt-3">تایید شماره موبایل</h3>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="verify.php">
                <div class="mb-3">
                    <label for="otp" class="form-label">کد ارسال شده را وارد کنید</label>
                    <input type="text" class="form-control" id="otp" name="otp" required autofocus maxlength="5">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">تایید و ادامه</button>
                </div>
                <div class="text-center mt-3">
                    <div id="resend-container">
                        <a href="register.php" class="text-decoration-none small d-none" id="resend-link">ارسال مجدد کد / تغییر شماره</a>
                        <span id="timer-text" class="small text-muted">ارسال مجدد کد تا <span id="timer"></span> ثانیه دیگر</span>
                    </div>
                </div>
            </form>
        </div>
        <div class="login-branding d-none d-md-flex"></div>
    </div>
    <script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timerElement = document.getElementById('timer');
            const timerText = document.getElementById('timer-text');
            const resendLink = document.getElementById('resend-link');

            let timeLeft = 120; // 2 دقیقه

            const countdown = setInterval(function() {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    timerText.classList.add('d-none');
                    resendLink.classList.remove('d-none');
                } else {
                    const minutes = Math.floor(timeLeft / 60);
                    let seconds = timeLeft % 60;
                    seconds = seconds < 10 ? '0' + seconds : seconds;
                    
                    // تبدیل اعداد به فارسی
                    const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    const timeString = `${persianDigits[minutes]}:${String(seconds).split('').map(s => persianDigits[s]).join('')}`;

                    timerElement.textContent = timeString;
                }
                timeLeft--;
            }, 1000);
        });
    </script>
</body>
</html>