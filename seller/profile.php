<?php
$page_title = 'پروفایل فروشگاه';
require_once 'inc/header.php';

$seller_id = $_SESSION['seller_id'];
$errors = [];
$success_message = '';

// --- پردازش فرم‌ها ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- به‌روزرسانی اطلاعات پروفایل ---
    if ($action === 'update_profile') {
        $shop_name = trim($_POST['shop_name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        if (empty($shop_name)) $errors[] = 'نام فروشگاه الزامی است.';
        if (empty($mobile)) $errors[] = 'شماره موبایل الزامی است.';

        // بررسی اینکه آیا شماره موبایل توسط فروشنده دیگری استفاده شده است
        if (empty($errors)) {
            $stmt_check = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ? AND id != ?");
            $stmt_check->execute([$mobile, $seller_id]);
            if ($stmt_check->fetch()) {
                $errors[] = 'این شماره موبایل قبلاً توسط فروشنده دیگری ثبت شده است.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE sellers SET shop_name = ?, mobile = ? WHERE id = ?");
                $stmt->execute([$shop_name, $mobile, $seller_id]);
                $success_message = 'اطلاعات فروشگاه با موفقیت به‌روزرسانی شد.';
            } catch (Exception $e) {
                $errors[] = 'خطا در به‌روزرسانی اطلاعات: ' . $e->getMessage();
            }
        }
    }

    // --- تغییر رمز عبور ---
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $errors[] = 'تمام فیلدهای رمز عبور باید پر شوند.';
        } elseif ($new_password !== $confirm_new_password) {
            $errors[] = 'رمز عبور جدید و تکرار آن مطابقت ندارند.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.';
        } else {
            try {
                // واکشی رمز عبور فعلی از دیتابیس
                $stmt_pass = $pdo->prepare("SELECT password FROM sellers WHERE id = ?");
                $stmt_pass->execute([$seller_id]);
                $current_hashed_password = $stmt_pass->fetchColumn();

                // بررسی صحت رمز عبور فعلی
                if (password_verify($current_password, $current_hashed_password)) {
                    // هش کردن رمز عبور جدید و به‌روزرسانی دیتابیس
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_update = $pdo->prepare("UPDATE sellers SET password = ? WHERE id = ?");
                    $stmt_update->execute([$new_hashed_password, $seller_id]);
                    $success_message = 'رمز عبور با موفقیت تغییر یافت.';
                } else {
                    $errors[] = 'رمز عبور فعلی شما اشتباه است.';
                }
            } catch (Exception $e) {
                $errors[] = 'خطا در تغییر رمز عبور: ' . $e->getMessage();
            }
        }
    }
}

// واکشی اطلاعات فعلی فروشنده برای نمایش در فرم
$stmt = $pdo->prepare("SELECT shop_name, mobile FROM sellers WHERE id = ?");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1>پروفایل فروشگاه</h1>
</div>

<div class="row g-4">
    <!-- فرم ویرایش اطلاعات فروشگاه -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">ویرایش اطلاعات فروشگاه</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">نام فروشگاه</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($seller['shop_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="mobile" class="form-label">شماره موبایل (برای ورود)</label>
                        <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($seller['mobile'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                </form>
            </div>
        </div>
    </div>

    <!-- فرم تغییر رمز عبور -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">تغییر رمز عبور</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">تکرار رمز عبور جدید</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                            <span class="input-group-text toggle-password" style="cursor: pointer;"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning">تغییر رمز عبور</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
<?php if ($success_message): ?>
    showToast('<?php echo addslashes(htmlspecialchars($success_message)); ?>', 'success');
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <?php
    $error_string = implode('<br>', array_map('htmlspecialchars', $errors));
    ?>
    showToast('<?php echo addslashes($error_string); ?>', 'danger');
<?php endif; ?>

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
<?php $page_scripts = ob_get_clean(); ?>

<?php require_once 'inc/footer.php'; ?>
```

### ۲. افزودن لینک پروفایل به منوی کاربر

برای دسترسی آسان به این صفحه، لینک آن را از قبل در منوی کشویی کاربر در `header.php` قرار داده بودیم. این لینک اکنون به درستی کار خواهد کرد.

**فایل: `d:\xampp3\htdocs\Seller\inc\header.php`** (بدون تغییر، فقط برای تأیید)

```php
// ... (inside the topbar)
<ul class="dropdown-menu dropdown-menu-start" aria-labelledby="userDropdown">
    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-fill me-2"></i>پروفایل</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>خروج</a></li>
</ul>
// ...
```

با ایجاد این صفحه، پنل فروشندگان شما یک بخش مدیریتی مهم و کاربردی دیگر به دست آورده است.

<!--
[PROMPT_SUGGESTION]در صفحه پروفایل، امکان آپلود لوگوی فروشگاه را اضافه کن.[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]در صفحه جزئیات سفارش، به فروشنده امکان تغییر وضعیت آیتم‌های خودش را بده (مثلا از "در حال پردازش" به "ارسال شده").[/PROMPT_SUGGESTION]
-->