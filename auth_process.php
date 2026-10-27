<?php
require_once 'inc/config.php';

// --- منطق ثبت نام ---
if (isset($_POST['register'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $organization_id = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : 1;

    // تبدیل اعداد فارسی/عربی به انگلیسی در سمت سرور
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $mobile = str_replace($persian_digits, $english_digits, $mobile);

    // اعتبارسنجی فرمت شماره موبایل (شروع با 09 و ۱۱ رقم)
    if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $_SESSION['message'] = "شماره موبایل نامعتبر است. لطفاً شماره‌ای با فرمت 09xxxxxxxxx وارد کنید.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }

    // اعتبارسنجی ساده
    if (empty($first_name) || empty($last_name) || empty($mobile) || empty($password) || empty($confirm_password)) {
        $_SESSION['message'] = "لطفاً تمام فیلدها را پر کنید.";
        $_SESSION['message_type'] = "danger";
        header("Location: register.php");
        exit();
    }

    // بررسی حداقل طول رمز عبور
    if (strlen($password) < 8) {
        $_SESSION['message'] = "رمز عبور باید حداقل ۸ کاراکتر باشد.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }

    // بررسی یکسان بودن رمز عبور و تکرار آن
    if ($password !== $confirm_password) {
        $_SESSION['message'] = "رمز عبور و تکرار آن با هم مطابقت ندارند.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }

    // بررسی پیچیدگی رمز عبور (حداقل یک حرف، یک عدد و یک کاراکتر خاص)
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W_]/', $password)) {
        $_SESSION['message'] = "رمز عبور باید شامل حداقل یک حرف، یک عدد و یک کاراکتر خاص (مانند @، #، $) باشد.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }

    // اعتبارسنجی نام و نام خانوادگی (فقط حروف فارسی)
    // بررسی می‌کند که فقط شامل حروف بازه فارسی/عربی و فاصله باشد و عدد نداشته باشد
    if (!preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $first_name) || !preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $last_name) || preg_match('/\p{N}/u', $first_name . $last_name)) {
        $_SESSION['message'] = "نام و نام خانوادگی باید فقط شامل حروف فارسی باشند.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }

    // بررسی اینکه آیا موبایل قبلاً ثبت شده است
    $stmt = $conn->prepare("SELECT id FROM customers WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['message'] = "این شماره موبایل قبلاً ثبت نام کرده است.";
        $_SESSION['message_type'] = "warning";
        header("Location: register.php");
        exit();
    }
    $stmt->close();

    // هش کردن رمز عبور برای امنیت
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // درج کاربر جدید در دیتابیس
    $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, mobile, password, organization_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $first_name, $last_name, $mobile, $hashed_password, $organization_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "ثبت نام با موفقیت انجام شد. اکنون می‌توانید وارد شوید.";
        $_SESSION['message_type'] = "success";
        header("Location: login.php");
    } else {
        $_SESSION['message'] = "خطایی در هنگام ثبت نام رخ داد.";
        $_SESSION['message_type'] = "danger";
        header("Location: register.php");
    }
    $stmt->close();
    exit();
}

// --- منطق ورود ---
if (isset($_POST['login'])) {
    $mobile = $_POST['mobile'];
    $password = $_POST['password'];

    // تبدیل اعداد فارسی/عربی به انگلیسی در سمت سرور
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $mobile = str_replace($persian_digits, $english_digits, $mobile);

    // پیدا کردن کاربر با شماره موبایل وارد شده
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password FROM customers WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // بررسی تطابق رمز عبور وارد شده با رمز عبور هش شده در دیتابیس
        if (password_verify($password, $user['password'])) {
            // (پیشنهاد امنیتی) بازسازی شناسه نشست برای جلوگیری از حملات Session Fixation
            session_regenerate_id(true);

            // ورود موفقیت آمیز: ذخیره اطلاعات کاربر در سشن
            $_SESSION['customer_id'] = $user['id'];
            $_SESSION['customer_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
                header("Location: " . $_POST['redirect']);
            } else {
                header("Location: index.php"); // انتقال به صفحه اصلی
            }
            exit();
        }
    }

    // اگر ورود ناموفق بود
    $_SESSION['message'] = "شماره موبایل یا رمز عبور اشتباه است.";
    $_SESSION['message_type'] = "danger";
    header("Location: login.php");
    exit();
}

?>