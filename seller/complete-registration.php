<?php
require_once 'inc/config.php';

// حالت دیباگ: برای محیط توسعه روی true تنظیم شود تا خطای واقعی نمایش داده شود. در محیط پروداکشن باید false باشد.
define('DEBUG_MODE', true); // برای دیباگ روی هاست، این را موقتا true کنید

// اگر کاربر مراحل قبل را طی نکرده، او را به صفحه ثبت‌نام برگردان
if (!isset($_SESSION['is_mobile_verified']) || $_SESSION['is_mobile_verified'] !== true) {
    header("Location: register.php");
    exit();
}

$error_message = '';
$debug_error = ''; // متغیر جدید برای ذخیره خطای دیباگ
$mobile = $_SESSION['register_mobile'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = $mobile;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error_message = 'تمام فیلدها الزامی هستند.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W_]/', $password)) {
        $error_message = 'رمز عبور باید حداقل ۸ کاراکتر و شامل حروف بزرگ و کوچک، اعداد و نمادها باشد.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'رمز عبور و تکرار آن مطابقت ندارند.';
    } else {
        try {
            // بررسی مجدد برای جلوگیری از ثبت شماره تکراری (در موارد رقابتی یا race condition)
            $stmt_check = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ?");
            $stmt_check->execute([$mobile]);
            if ($stmt_check->fetch()) {
                $error_message = 'این شماره موبایل لحظاتی پیش ثبت‌نام شده است. لطفاً وارد شوید.';
                // پاک کردن سشن برای جلوگیری از تلاش مجدد با همین اطلاعات
                unset($_SESSION['register_mobile'], $_SESSION['register_otp_hash'], $_SESSION['register_otp_expiry'], $_SESSION['is_mobile_verified']);
                throw new Exception("Duplicate mobile number detected just before final insert.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // ایجاد فروشنده جدید با وضعیت "ناقص" تا اطلاعات خود را تکمیل کند
            $stmt = $pdo->prepare("INSERT INTO sellers (shop_name, mobile, password, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$shop_name, $mobile, $hashed_password]);
            $seller_id = $pdo->lastInsertId();

            // پاک کردن سشن‌های ثبت‌نام و ذخیره seller_id برای مرحله بعدی
            unset($_SESSION['register_mobile'], $_SESSION['register_otp_hash'], $_SESSION['register_otp_expiry'], $_SESSION['is_mobile_verified']);
            $_SESSION['new_seller_id'] = $seller_id;

            // هدایت به صفحه تکمیل اطلاعات
            header("Location: registerForm.php");
            exit();

        } catch (PDOException $e) { // به طور خاص خطاهای PDO را دریافت می‌کنیم
            // پاک کردن سشن برای جلوگیری از تلاش مجدد با همین اطلاعات در صورت بروز هر خطایی
            unset($_SESSION['register_mobile'], $_SESSION['register_otp_hash'], $_SESSION['register_otp_expiry'], $_SESSION['is_mobile_verified']);

            // ثبت خطا در لاگ سرور
            error_log("Registration PDO Error: " . $e->getMessage() . " | Mobile: " . $mobile);

            // نمایش پیام خطا به کاربر
            if ($e->getCode() == '23000') { // Integrity constraint violation (مثلا شماره موبایل تکراری)
                $error_message = 'این شماره موبایل قبلاً در سیستم ثبت شده است. لطفاً وارد شوید.';
            } else {
                $error_message = 'خطا در ثبت اطلاعات. لطفاً مجدداً تلاش کنید.';
                // اگر حالت دیباگ فعال است، خطای اصلی را برای نمایش در کنسول ذخیره کن
                if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                    $debug_error = "PDO Error: " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            // پاک کردن سشن برای جلوگیری از تلاش مجدد با همین اطلاعات در صورت بروز هر خطایی
            unset($_SESSION['register_mobile'], $_SESSION['register_otp_hash'], $_SESSION['register_otp_expiry'], $_SESSION['is_mobile_verified']);

            // ثبت خطا در لاگ سرور
            error_log("Registration Generic Error: " . $e->getMessage() . " | Mobile: " . $mobile);

            $error_message = 'خطایی در سیستم رخ داده است. لطفاً بعداً تلاش کنید.';
            if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                $debug_error = "Generic Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تکمیل ثبت‌نام</title>
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

        @media (max-width: 768px) {
            .login-container { flex-direction: column; min-height: auto; width: 90%; max-width: 450px; }
            .login-branding { display: none; }
            .login-form-container { padding: 30px; }
        }

        .password-strength-requirements {
            list-style-type: none;
            padding-right: 0;
            font-size: 0.875em;
            margin-top: 0.5rem;
            color: #6c757d;
            /* Items will now be block-level and appear line-by-line */
        }
        .requirement {
            transition: color 0.3s ease;
        }
        .requirement.valid {
            color: #198754; /* Bootstrap success color */
        }
        .requirement.invalid {
            color: #dc3545; /* Bootstrap danger color */
        }
        .requirement i {
            margin-left: 5px;
        }
        .progress-bar {
            transition: width 0.4s ease, background-color 0.4s ease;
        }
        .password-popover {
            display: none; /* Hidden by default */
            position: absolute;
            top: 100%; /* Position it right below the parent */
            left: 0;
            right: 0;
            width: 100%;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem; /* Rounded corners only at the bottom */
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 1rem;
            z-index: 10;
        }
        @media (max-width: 768px) {
            /* The new style works for mobile by default, so this can be removed or kept for other overrides */
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-container">
            <div class="text-center mb-4">
                <h3 class="mt-3">تکمیل اطلاعات</h3>
                <p class="text-muted">اطلاعات فروشگاه خود را وارد کنید.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($debug_error): ?>
                <script>
                    console.error("Server-side debug error:", <?php echo json_encode($debug_error, JSON_UNESCAPED_UNICODE); ?>);
                </script>
            <?php endif; ?>

            <form method="POST" action="complete-registration.php">
                <div class="mb-3 position-relative">
                    <label for="password" class="form-label">رمز عبور</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                    </div>
                    <div id="password-popover" class="password-popover">
                        <p class="fw-bold mb-2" style="font-size: 0.9em;">رمز عبور باید شامل موارد زیر باشد:</p>
                        <ul id="password-strength-requirements" class="password-strength-requirements">
                            <li id="length" class="requirement invalid"><i class="bi bi-x-circle"></i> حداقل ۸ کاراکتر</li>
                            <li id="uppercase" class="requirement invalid"><i class="bi bi-x-circle"></i> شامل حروف بزرگ (A-Z)</li>
                            <li id="lowercase" class="requirement invalid"><i class="bi bi-x-circle"></i> شامل حروف کوچک (a-z)</li>
                            <li id="number" class="requirement invalid"><i class="bi bi-x-circle"></i> شامل اعداد (0-9)</li>
                            <li id="symbol" class="requirement invalid"><i class="bi bi-x-circle"></i> شامل نمادها (!@#)</li>
                        </ul>
                        <div class="progress mt-2" style="height: 8px;">
                            <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">تکرار رمز عبور</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                    </div>
                    <div id="confirm-password-message" class="form-text"></div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" id="submit-button" class="btn btn-success" disabled>تکمیل و ثبت‌نام نهایی</button>
                </div>
            </form>
        </div>
        <div class="login-branding d-none d-md-flex"></div>
    </div>
    <script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');

            togglePasswordButtons.forEach(button => {
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

            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const confirmPasswordMessage = document.getElementById('confirm-password-message');
            const submitButton = document.getElementById('submit-button');
            const passwordPopover = document.getElementById('password-popover');
            const progressBar = document.getElementById('password-strength-bar');
            const requirements = {
                length: document.getElementById('length'),
                lowercase: document.getElementById('lowercase'),
                uppercase: document.getElementById('uppercase'),
                number: document.getElementById('number'),
                symbol: document.getElementById('symbol')
            };

            const validations = {
                length: val => val.length >= 8,
                lowercase: val => /[a-z]/.test(val),
                uppercase: val => /[A-Z]/.test(val),
                number: val => /[0-9]/.test(val),
                symbol: val => /[\W_]/.test(val) // Matches non-word characters or underscore
            };

            function checkPasswordStrength(password) {
                let score = 0;

                for (const [key, validator] of Object.entries(validations)) {
                    const requirementElement = requirements[key];
                    const icon = requirementElement.querySelector('i');

                    if (validator(password)) {
                        requirementElement.classList.remove('invalid');
                        requirementElement.classList.add('valid');
                        icon.classList.remove('bi-x-circle');
                        icon.classList.add('bi-check-circle-fill');
                        score++;
                    } else {
                        requirementElement.classList.remove('valid');
                        requirementElement.classList.add('invalid');
                        icon.classList.remove('bi-check-circle-fill');
                        icon.classList.add('bi-x-circle');
                    }
                }

                const totalRequirements = Object.keys(validations).length;
                const strengthPercentage = (score / totalRequirements) * 100;

                progressBar.style.width = strengthPercentage + '%';
                progressBar.setAttribute('aria-valuenow', strengthPercentage);

                progressBar.classList.remove('bg-danger', 'bg-warning', 'bg-success');

                if (score <= 2) {
                    progressBar.classList.add('bg-danger');
                } else if (score <= 4) {
                    progressBar.classList.add('bg-warning');
                } else {
                    progressBar.classList.add('bg-success');
                }
                return score;
            }

            function checkFormValidity() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const score = checkPasswordStrength(password);
                const totalRequirements = Object.keys(validations).length;

                const isPasswordStrong = score === totalRequirements;
                const doPasswordsMatch = password === confirmPassword && confirmPassword.length > 0;

                if (confirmPassword.length > 0) {
                    if (doPasswordsMatch) {
                        confirmPasswordMessage.textContent = 'رمزهای عبور مطابقت دارند.';
                        confirmPasswordMessage.classList.remove('text-danger');
                        confirmPasswordMessage.classList.add('text-success');
                    } else {
                        confirmPasswordMessage.textContent = 'رمزهای عبور مطابقت ندارند.';
                        confirmPasswordMessage.classList.remove('text-success');
                        confirmPasswordMessage.classList.add('text-danger');
                    }
                } else {
                    confirmPasswordMessage.textContent = '';
                }

                submitButton.disabled = !(isPasswordStrong && doPasswordsMatch);
            }

            passwordInput.addEventListener('input', checkFormValidity);
            confirmPasswordInput.addEventListener('input', checkFormValidity);

            passwordInput.addEventListener('focus', function() {
                passwordPopover.style.display = 'block';
            });

            passwordInput.addEventListener('blur', function() {
                passwordPopover.style.display = 'none';
            });
        });
    </script>
</body>
</html>