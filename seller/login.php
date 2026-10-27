<?php
require_once 'inc/config.php';

// اگر کاربر از قبل لاگین کرده و فروشنده است، او را به داشبورد هدایت کن
if (isset($_SESSION['seller_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// --- AJAX: ارسال کد یکبار مصرف برای ورود ---
if (isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    header('Content-Type: application/json');
    $mobile = $_POST['mobile'] ?? '';
    $response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

    if (empty($mobile)) {
        $response['message'] = 'شماره موبایل الزامی است.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ?");
            $stmt->execute([$mobile]);
            if ($stmt->fetch()) {
                $otp = random_int(10000, 99999);
                $_SESSION['login_otp_hash'] = password_hash((string)$otp, PASSWORD_DEFAULT);
                $_SESSION['login_otp_expiry'] = time() + (3 * 60); // 3 دقیقه انقضا
                $_SESSION['login_mobile'] = $mobile;

                // ارسال پیامک با کاوه‌نگار
                $api_key = $site_settings['kavenegar_api_key'] ?? '';
                $template = $site_settings['kavenegar_otp_template'] ?? 'verify';
                $url = "https://api.kavenegar.com/v1/{$api_key}/verify/lookup.json?receptor={$mobile}&token={$otp}&template={$template}";
                @file_get_contents($url);

                $response = ['success' => true, 'message' => 'کد یکبار مصرف به شماره شما ارسال شد.'];
            } else {
                $response['message'] = 'فروشنده‌ای با این شماره موبایل یافت نشد.';
            }
        } catch (Exception $e) {
            error_log("Login OTP Send Error: " . $e->getMessage());
            $response['message'] = 'خطا در ارسال کد. لطفاً مجدداً تلاش کنید.';
        }
    }
    echo json_encode($response);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'] ?? '';
    $password = $_POST['password'] ?? '';
    $otp = $_POST['otp'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    $login_method = $_POST['login_method'] ?? 'password';

    if ($login_method === 'password' && (empty($mobile) || empty($password))) {
        $error_message = 'شماره موبایل و رمز عبور الزامی است.';
        $mobile = ''; // پاک کردن شماره موبایل در صورت خطا
    } else {
        try {
            // جستجو در جدول sellers بر اساس شماره موبایل
            $stmt = $pdo->prepare("SELECT id, password, status, shop_name, first_name, registration_step FROM sellers WHERE mobile = ?");
            $stmt->execute([$mobile]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);

            $is_login_successful = false;
            if ($login_method === 'password' && $seller && password_verify($password, $seller['password'])) {
                $is_login_successful = true;
            } elseif ($login_method === 'otp') {
                if (empty($otp)) {
                    $error_message = 'کد یکبار مصرف را وارد کنید.';
                } elseif (isset($_SESSION['login_mobile']) && $_SESSION['login_mobile'] == $mobile && isset($_SESSION['login_otp_hash']) && password_verify($otp, $_SESSION['login_otp_hash'])) {
                    if (time() > $_SESSION['login_otp_expiry']) {
                        $error_message = 'کد یکبار مصرف منقضی شده است.';
                    } else {
                        $is_login_successful = true;
                        unset($_SESSION['login_otp_hash'], $_SESSION['login_otp_expiry'], $_SESSION['login_mobile']);
                    }
                } else {
                    $error_message = 'کد یکبار مصرف نامعتبر است.';
                }
            }

            if ($is_login_successful && $seller) {
                // اگر ثبت‌نام کامل نشده، به فرم تکمیل اطلاعات برو
                if ($seller['status'] === 'incomplete' || $seller['registration_step'] === 'info') {
                    $_SESSION['seller_id'] = $seller['id'];
                    header("Location: registerForm.php");
                    exit();
                // اگر در انتظار تایید است، به صفحه انتظار برو
                } elseif ($seller['status'] === 'pending') {
                    $_SESSION['seller_id'] = $seller['id'];
                    header("Location: pending_approval.php");
                    exit();
                // اگر تایید نشده (مثلا معلق شده)، پیام خطا بده
                } elseif ($seller['status'] !== 'approved') {
                    $error_message = 'حساب کاربری شما هنوز تایید نشده یا به حالت تعلیق درآمده است.';
                } else {
                    session_regenerate_id(true); // بازسازی session برای امنیت
                    $_SESSION['seller_id'] = $seller['id']; // ذخیره شناسه فروشنده در سشن
                    $_SESSION['seller_shop_name'] = $seller['shop_name']; // ذخیره نام فروشگاه در سشن

                    // اگر "مرا به خاطر بسپار" انتخاب شده بود
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $token);
                        $expiry_date = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 روز

                        $stmt_token = $pdo->prepare("UPDATE sellers SET remember_token = ?, remember_token_expires_at = ? WHERE id = ?");
                        $stmt_token->execute([$token_hash, $expiry_date, $seller['id']]);

                        // کوکی را با فرمت selector:validator تنظیم می‌کنیم
                        setcookie('remember_me', $seller['id'] . ':' . $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
                    }

                    header("Location: dashboard.php"); // هدایت به داشبورد
                    exit();
                } 
            } else {
                if (empty($error_message)) $error_message = 'شماره موبایل یا رمز عبور/کد یکبار مصرف اشتباه است.';
            }
        } catch (Exception $e) {
            $error_message = 'خطایی در سیستم رخ داده است. لطفاً بعداً تلاش کنید.';
            error_log($e->getMessage());
        } 
    }
}

$session_error = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
if ($session_error) {
    $error_message = $session_error;
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);


?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل فروشندگان</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL . 'img/favicon.png'; ?>">

    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.min.css">

    <!-- Bootstrap v5.3.2 CSS -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">

    <!-- Vazir Font CSS -->
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/style.css">

    <!-- Custom CSS -->
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
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4); /* لایه تیره برای خوانایی بهتر متن */
    }

    .branding-content {
        position: relative;
        z-index: 1;
    }

    .branding-content h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .branding-content p {
        font-size: 1.1rem;
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
        margin-bottom: 2rem;
    }

    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        outline: 0;
    }

    /* استایل اختصاصی برای اینپوت شماره موبایل */
    #mobile {
        direction: ltr;
        text-align: right;
    }

    .btn-primary {
        padding: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .login-container {
            flex-direction: column;
            min-height: auto;
            width: 90%;
            max-width: 450px;
        }
        .login-branding {
            display: none; /* مخفی کردن بخش تصویر در موبایل */
        }
        .login-form-container {
            padding: 30px;
        }
    }
 </style>
</head>

<body>
<div class="login-container">
    <!-- بخش فرم -->
    <div class="login-form-container">
        <div class="text-center mb-4">
            <img src="<?php echo BASE_URL . 'img/logo.png'; ?>" alt="Logo" class="logo">
            <h3>ورود به پنل فروشندگان</h3>
        </div>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm">
                <input type="hidden" name="login_method" id="login_method" value="password">

                <!-- بخش مشترک: شماره موبایل -->
                <div class="mb-3">
                    <label for="mobile" class="form-label">شماره موبایل</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="text" class="form-control" id="mobile" name="mobile" required autofocus>
                    </div>
                </div>

                <!-- بخش ورود با رمز عبور -->
                <div id="password-login-section">
                    <div class="mb-4">
                        <label for="password" class="form-label">رمز عبور</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password">
                            <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">مرا به خاطر بسپار</label>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">ورود</button>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" id="show-otp-login" class="btn btn-link text-decoration-none small">ورود با کد یکبار مصرف</button>
                    </div>
                </div>

                <!-- بخش ورود با کد یکبار مصرف -->
                <div id="otp-login-section" class="d-none">
                    <div class="mb-3 d-none" id="otp-input-container">
                        <label for="otp" class="form-label">کد یکبار مصرف</label>
                        <input type="text" class="form-control" id="otp" name="otp" placeholder="کد ۵ رقمی" style="text-align: center; letter-spacing: 0.5em;">
                    </div>
                    <div class="d-grid">
                        <button type="button" id="send-otp-btn" class="btn btn-secondary">ارسال کد یکبار مصرف</button>
                        <button type="submit" id="login-with-otp-btn" class="btn btn-primary d-none">ورود با کد</button>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" id="show-password-login" class="btn btn-link text-decoration-none small">ورود با رمز عبور</button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="register.php" class="text-decoration-none small">حساب کاربری ندارید؟ <strong>ثبت نام کنید</strong></a>
            </div>
    </div>
    <!-- بخش برندینگ و تصویر -->
    <div class="login-branding d-none d-md-flex">
        <div class="branding-content">
            <h1>به پنل خود خوش آمدید</h1>
            <p>سفارشات و محصولات خود را به سادگی مدیریت کنید.</p>
        </div>
    </div>
</div>

 <script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>
 <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileInput = document.getElementById('mobile');
        const loginForm = document.getElementById('loginForm');
        const loginMethodInput = document.getElementById('login_method');

        const passwordSection = document.getElementById('password-login-section');
        const otpSection = document.getElementById('otp-login-section');
        const showOtpBtn = document.getElementById('show-otp-login');
        const showPassBtn = document.getElementById('show-password-login');

        // تابع تبدیل اعداد انگلیسی به فارسی
        function convertToPersianDigits(inputElement) {
            const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            let value = inputElement.value;
            for (let i = 0; i < 10; i++) {
                value = value.replace(new RegExp(englishDigits[i], 'g'), persianDigits[i]);
            }
            inputElement.value = value;
        }

        // تابع تبدیل اعداد فارسی به انگلیسی
        function convertToEnglishDigits(value) {
            const englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            for (let i = 0; i < 10; i++) {
                value = value.replace(new RegExp(persianDigits[i], 'g'), englishDigits[i]);
            }
            return value;
        }

        // --- مدیریت تبدیل اعداد موبایل ---
        if (mobileInput) {
            // هنگام ورود اطلاعات، اعداد انگلیسی را به فارسی تبدیل کن
            mobileInput.addEventListener('input', function() {
                convertToPersianDigits(this);
            });

            // قبل از ارسال فرم، اعداد فارسی را به انگلیسی تبدیل کن
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    mobileInput.value = convertToEnglishDigits(mobileInput.value);
                });
            }

        }

        // --- مدیریت نمایش/مخفی کردن رمز عبور ---
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function () {
                const input = this.previousElementSibling;
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            });
        });

        // --- مدیریت جابجایی بین فرم‌های ورود ---
        showOtpBtn.addEventListener('click', () => {
            passwordSection.classList.add('d-none');
            otpSection.classList.remove('d-none');
            loginMethodInput.value = 'otp';
            document.getElementById('password').required = false;
        });

        showPassBtn.addEventListener('click', () => {
            otpSection.classList.add('d-none');
            passwordSection.classList.remove('d-none');
            loginMethodInput.value = 'password';
            document.getElementById('password').required = true;
        });

        // --- منطق ارسال و ورود با OTP ---
        const sendOtpBtn = document.getElementById('send-otp-btn');
        const loginWithOtpBtn = document.getElementById('login-with-otp-btn');
        const otpInputContainer = document.getElementById('otp-input-container');

        sendOtpBtn.addEventListener('click', () => {
            const mobile = convertToEnglishDigits(mobileInput.value);
            if (!/^09[0-9]{9}$/.test(mobile)) {
                alert('لطفاً شماره موبایل معتبر وارد کنید.');
                return;
            }

            sendOtpBtn.disabled = true;
            sendOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال ارسال...';

            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('mobile', mobile);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message); // یا استفاده از Toast
                    // نمایش بخش ورود کد
                    sendOtpBtn.classList.add('d-none');
                    loginWithOtpBtn.classList.remove('d-none');
                    otpInputContainer.classList.remove('d-none');
                    document.getElementById('otp').required = true;
                    mobileInput.readOnly = true; // جلوگیری از تغییر شماره موبایل
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('خطای شبکه. لطفاً اتصال خود را بررسی کنید.');
            })
            .finally(() => {
                if (!sendOtpBtn.classList.contains('d-none')) {
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerHTML = 'ارسال کد یکبار مصرف';
                }
            });
        });

    });
 </script>
</body>
</html>