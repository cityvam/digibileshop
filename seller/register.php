<?php
require_once 'inc/config.php';

// اگر کاربر لاگین کرده، او را به داشبورد هدایت کن
if (isset($_SESSION['seller_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'] ?? '';

    // تبدیل اعداد فارسی به انگلیسی قبل از اعتبارسنجی
    $mobile = to_english_nums($mobile);

    if (empty($mobile)) {
        $error_message = 'لطفاً شماره موبایل خود را وارد کنید.';
        $mobile = ''; // پاک کردن شماره موبایل در صورت خطا
    } elseif (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $error_message = 'فرمت شماره موبایل صحیح نیست. (مثال: 09123456789)';
        $mobile = ''; // پاک کردن شماره موبایل در صورت خطا
    } else {
        try {
            // بررسی اینکه آیا شماره موبایل قبلاً ثبت شده است
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ?");
            $stmt->execute([$mobile]);
            if ($stmt->fetch()) {
                $error_message = 'این شماره موبایل قبلاً در سیستم ثبت شده است. لطفاً وارد شوید یا رمز عبور خود را بازیابی کنید.';
                $mobile = ''; // پاک کردن شماره موبایل در صورت خطا
            } else {
                // ایجاد کد OTP
                $otp = random_int(10000, 99999);
                $otp_hash = password_hash((string)$otp, PASSWORD_DEFAULT);
                $otp_expiry = date('Y-m-d H:i:s', time() + (5 * 60)); // 5 دقیقه انقضا

                // ذخیره اطلاعات در سشن برای مرحله بعد
                $_SESSION['register_mobile'] = $mobile;
                $_SESSION['register_otp_hash'] = $otp_hash;
                $_SESSION['register_otp_expiry'] = $otp_expiry;

                // ارسال پیامک با کاوه‌نگار
                try {
                    $api_key = $site_settings['kavenegar_api_key'] ?? '';
                    $otp_template = $site_settings['kavenegar_otp_template'] ?? 'verify'; // 'verify' یک نام پیش‌فرض است
                    $receptor = $mobile;
                    $sender = "10008663"; // شماره ارسال‌کننده شما

                    // استفاده از lookup API برای ارسال کد با قالب
                    $url = "https://api.kavenegar.com/v1/{$api_key}/verify/lookup.json?receptor={$receptor}&token={$otp}&template={$otp_template}";
                    @file_get_contents($url);

                } catch (Exception $sms_e) {
                    error_log("Kavenegar SMS Error on Register: " . $sms_e->getMessage());
                    // در صورت بروز خطا در ارسال پیامک، فرآیند را متوقف می‌کنیم
                    $error_message = 'خطا در ارسال کد تایید. لطفاً لحظاتی دیگر تلاش کنید.'; // پاک کردن شماره موبایل در صورت خطا
                    $mobile = '';
                }

                if (empty($error_message)) {
                    // هدایت به صفحه تایید کد
                    $mobile = ''; // پاک کردن شماره موبایل پس از ارسال موفقیت‌آمیز
                    header("Location: verify.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            $error_message = 'خطایی در سیستم رخ داده است. لطفاً بعداً تلاش کنید.';
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
    <title>ثبت‌نام در پنل فروشندگان</title>
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .login-form-container .logo {
            max-width: 150px;
            margin-bottom: 1.5rem;
        }

        .login-form-container h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        #mobile { direction: ltr; text-align: right; }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                min-height: auto;
                width: 90%;
                max-width: 450px;
            }
            .login-branding {
                display: none;
            }
            .login-form-container {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-container">
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL . 'img/logo.png'; ?>" alt="Logo" class="logo">
                <h3>ثبت نام در پنل فروشندگان</h3>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <div class="mb-3">
                    <label for="mobile" class="form-label">شماره موبایل خود را وارد کنید</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="text" class="form-control" id="mobile" name="mobile" required autofocus>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary" id="submitButton">تایید و ادامه</button>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none small">ورود به پنل فروشندگان</a>
                </div>
            </form>
        </div>
        <div class="login-branding d-none d-md-flex"></div>
    </div>
    <script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileInput = document.getElementById('mobile');
            const registerForm = document.getElementById('registerForm');
            const submitButton = document.getElementById('submitButton');

            function toPersian(str) {
                const p = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                return str.replace(/[0-9]/g, w => p[+w]);
            }

            function toEnglish(str) {
                const p = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
                const e = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                for (let i = 0; i < 10; i++) {
                    str = str.replace(p[i], e[i]);
                }
                return str;
            }

            if (mobileInput) {
                mobileInput.addEventListener('input', () => {
                    mobileInput.value = toPersian(mobileInput.value);
                });
            }

            if (registerForm) {
                registerForm.addEventListener('submit', () => {
                    if (mobileInput) {
                        mobileInput.value = toEnglish(mobileInput.value);
                    }
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML =
                            `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> در حال بررسی...`;
                    }
                });
            }
        });
    </script>
</body>
</html>