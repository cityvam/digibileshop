<?php
require_once 'inc/config.php';

$token = $_GET['token'] ?? '';
$seller_id = $_GET['id'] ?? '';

$message = '';
$message_type = 'danger'; // پیش‌فرض خطا
$is_token_valid = false;

if (empty($token) || empty($seller_id)) {
    $message = 'لینک بازیابی نامعتبر یا منقضی شده است.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT reset_token, reset_token_expires_at FROM sellers WHERE id = ?");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($seller && !empty($seller['reset_token']) && strtotime($seller['reset_token_expires_at']) > time()) {
            // توکن را با هش ذخیره شده در دیتابیس مقایسه می‌کنیم
            if (hash_equals($seller['reset_token'], hash('sha256', $token))) {
                $is_token_valid = true;
            } else {
                $message = 'توکن بازیابی نامعتبر است.';
            }
        } else {
            $message = 'لینک بازیابی نامعتبر یا منقضی شده است.';
        }
    } catch (Exception $e) {
        $message = 'خطایی در سیستم رخ داده است.';
        error_log($e->getMessage());
    }
}

// پردازش فرم تغییر رمز عبور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = 'لطفاً هر دو فیلد رمز عبور را پر کنید.';
    } elseif (strlen($new_password) < 6) {
        $message = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
    } elseif ($new_password !== $confirm_password) {
        $message = 'رمز عبور جدید و تکرار آن مطابقت ندارند.';
    } else {
        try {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // به‌روزرسانی رمز عبور و باطل کردن توکن
            $stmt_update = $pdo->prepare("UPDATE sellers SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
            $stmt_update->execute([$new_hashed_password, $seller_id]);

            $message = 'رمز عبور شما با موفقیت تغییر یافت. اکنون می‌توانید با رمز جدید وارد شوید.';
            $message_type = 'success';
            $is_token_valid = false; // فرم را پس از موفقیت مخفی کن

        } catch (Exception $e) {
            $message = 'خطا در تغییر رمز عبور.';
            error_log($e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیم رمز عبور جدید</title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/style.css">
    <style>
        body {
            font-family: 'Vazir', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .form-container {
            width: 100%;
            max-width: 450px;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="text-center mb-4">
            <h3>تنظیم رمز عبور جدید</h3>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_token_valid): ?>
            <form method="POST" action="reset-password.php?id=<?php echo htmlspecialchars($seller_id); ?>&token=<?php echo htmlspecialchars($token); ?>">
                <div class="mb-3">
                    <label for="new_password" class="form-label">رمز عبور جدید</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">تکرار رمز عبور جدید</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">تغییر رمز عبور</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none small">بازگشت به صفحه ورود</a>
        </div>
    </div>
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
        });
    </script>
</body>
</html>