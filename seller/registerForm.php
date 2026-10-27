<?php
require_once 'inc/config.php';

// بررسی اینکه آیا کاربر از مرحله قبل آمده یا با پروفایل ناقص لاگین کرده
if (isset($_SESSION['new_seller_id'])) {
    $seller_id = $_SESSION['new_seller_id'];
} elseif (isset($_SESSION['seller_id'])) {
    // اگر کاربر لاگین کرده ولی پروفایلش ناقص است
    $seller_id = $_SESSION['seller_id'];
} else {
    // اگر هیچکدام از شناسه‌ها وجود نداشت، به صفحه ثبت‌نام هدایت کن
    $_SESSION['error_message'] = 'برای دسترسی به این بخش، لطفاً ابتدا ثبت‌نام یا وارد شوید.';
    header("Location: login.php");
    exit();
}

$errors = [];
$success_message = '';

// واکشی اطلاعات فعلی فروشنده
$stmt_seller = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt_seller->execute([$seller_id]);
$seller_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);

if (!$seller_data) {
    $_SESSION['error_message'] = 'خطا در یافتن اطلاعات فروشنده. لطفاً مجدداً ثبت‌نام کنید.';
    unset($_SESSION['new_seller_id']);
    header("Location: register.php");
    exit();
}

// تعریف مرحله فعلی برای سایدبار بر اساس اطلاعات دیتابیس
$current_step = $seller_data['registration_step'] ?? 'info';

// فراخوانی لیست شهرها
$iran_cities_json = file_get_contents(__DIR__ . '/inc/iran_cities.json');
$iran_cities_data = json_decode($iran_cities_json, true);

// تبدیل ساختار JSON به آرایه انجمنی که کد انتظار دارد
$iran_cities = [];
if (is_array($iran_cities_data)) {
    foreach ($iran_cities_data as $province_data) {
        if (isset($province_data['name']) && isset($province_data['cities'])) {
            $iran_cities[$province_data['name']] = $province_data['cities'];
        }
    }
}

// مقادیر پیش‌فرض برای فرم
// این مقادیر از دیتابیس خوانده می‌شوند تا فرم با اطلاعات قبلی پر شود
$form_data = [
    'seller_type' => $seller_data['seller_type'] ?? 'individual',
    'first_name' => $seller_data['first_name'] ?? '',
    'last_name' => $seller_data['last_name'] ?? '',
    'national_id' => $seller_data['national_id'] ?? '',
    'father_name' => $seller_data['father_name'] ?? '',
    'birth_date' => $seller_data['birth_date'] ?? '',
    'guild_id' => $seller_data['guild_id'] ?? '',
    'economic_code' => $seller_data['economic_code'] ?? '',
    'shop_name_en' => $seller_data['shop_name_en'] ?? '',
    'credit_alias' => $seller_data['credit_alias'] ?? '',
    'installment_alias' => $seller_data['installment_alias'] ?? '',
    'shop_name' => $seller_data['shop_name'] ?? '',
    // فیلدهای جدید
    'account_holder_name' => $seller_data['account_holder_name'] ?? '', // نام صاحب حساب
    'payment_id' => $seller_data['payment_id'] ?? '', // شناسه پرداخت
    'account_number' => $seller_data['account_number'] ?? '',
    'shaba_number' => $seller_data['shaba_number'] ?? '',
    'card_number' => $seller_data['card_number'] ?? '',
    'province' => $seller_data['province'] ?? '',
    'phone' => $seller_data['phone'] ?? '', // تلفن ثابت
    'email' => $seller_data['email'] ?? '', // ایمیل
    'city' => $seller_data['city'] ?? '',
    'address' => $seller_data['address'] ?? '',
    'postal_code' => $seller_data['postal_code'] ?? '',
    'mobile' => $seller_data['mobile'] ?? '', // شماره موبایل (برای نمایش در تب آدرس)
];
// واکشی مسیر فایل‌های آپلود شده برای نمایش
$form_data['national_card_filename'] = $seller_data['national_card_path'] ? basename($seller_data['national_card_path']) : '';
$form_data['business_license_filename'] = $seller_data['business_license_path'] ? basename($seller_data['business_license_path']) : '';
$form_data['official_gazette_filename'] = $seller_data['official_gazette_path'] ? basename($seller_data['official_gazette_path']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- SERVER-SIDE DEBUGGING ---
    // این تابع خروجی را به کنسول مرورگر می‌فرستد
    function debug_to_console($data, $title = '') {
        $output = json_encode($data);
        echo "<script>console.log('PHP DEBUG: " . addslashes($title) . "', " . $output . ");</script>";
    }
    debug_to_console($_POST, 'Received POST Data');
    // --- END DEBUGGING ---
    
    $submitted_step = $_POST['submitted_step'] ?? null;

    // --- Step 1: Seller Info ---
    if ($submitted_step === 'info') {
        $form_data['seller_type'] = $_POST['seller_type'] ?? 'individual';
        $form_data['first_name'] = trim($_POST['first_name'] ?? '');
        $form_data['last_name'] = trim($_POST['last_name'] ?? '');
        $form_data['national_id'] = to_english_nums(trim($_POST['national_id'] ?? ''));
        $form_data['father_name'] = trim($_POST['father_name'] ?? '');
        $form_data['birth_date'] = to_gregorian(trim($_POST['birth_date'] ?? ''));
        $form_data['guild_id'] = trim($_POST['guild_id'] ?? '');
        $form_data['economic_code'] = trim($_POST['economic_code'] ?? '');
        $form_data['shop_name'] = trim($_POST['shop_name'] ?? '');
        $form_data['shop_name_en'] = trim($_POST['shop_name_en'] ?? '');
        $form_data['credit_alias'] = trim($_POST['credit_alias'] ?? '');
        $form_data['installment_alias'] = trim($_POST['installment_alias'] ?? '');

        if (empty($form_data['shop_name'])) $errors[] = 'نام فارسی فروشگاه الزامی است.';
        
        if ($form_data['seller_type'] === 'individual') { // اعتبارسنجی برای شخص حقیقی
            if (empty($form_data['first_name'])) $errors[] = 'نام الزامی است.';
            if (empty($form_data['last_name'])) $errors[] = 'نام خانوادگی الزامی است.';
            if (empty($form_data['national_id'])) $errors[] = 'کد ملی الزامی است.';
            if (empty($form_data['birth_date'])) $errors[] = 'تاریخ تولد الزامی است.';
        } elseif ($form_data['seller_type'] === 'legal') { // اعتبارسنجی برای شخص حقوقی
            if (empty($form_data['guild_id'])) $errors[] = 'شناسه صنفی الزامی است.';
            if (empty($form_data['economic_code'])) $errors[] = 'کد اقتصادی الزامی است.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE sellers SET seller_type=?, first_name=?, last_name=?, national_id=?, father_name=?, birth_date=?, guild_id=?, economic_code=?, shop_name=?, shop_name_en=?, credit_alias=?, installment_alias=?, registration_step='bank' WHERE id=?");
                $stmt->execute([$form_data['seller_type'], $form_data['first_name'], $form_data['last_name'], $form_data['national_id'], $form_data['father_name'], $form_data['birth_date'], $form_data['guild_id'], $form_data['economic_code'], $form_data['shop_name'], $form_data['shop_name_en'], $form_data['credit_alias'], $form_data['installment_alias'], $seller_id]);
                header("Location: registerForm.php");
                exit();
            } catch (Exception $e) {
                $errors[] = 'خطا در ذخیره اطلاعات فروشنده.';
                error_log("Step 1 Save Error: " . $e->getMessage());
            }
        }
    }

    // --- Step 2: Bank Info ---
    if ($submitted_step === 'bank') {
        $form_data['account_holder_name'] = trim($_POST['account_holder_name'] ?? '');
        $form_data['account_number'] = to_english_nums(trim($_POST['account_number'] ?? ''));
        $form_data['shaba_number'] = to_english_nums(trim($_POST['shaba_number'] ?? ''));
        $form_data['card_number'] = to_english_nums(trim($_POST['card_number'] ?? ''));
        $form_data['payment_id'] = trim($_POST['payment_id'] ?? '');

        if (empty($form_data['account_holder_name'])) $errors[] = 'نام صاحب حساب الزامی است.';
        if (empty($form_data['account_number'])) $errors[] = 'شماره حساب بانکی الزامی است.';
        if (empty($form_data['shaba_number'])) $errors[] = 'شماره شبا الزامی است.';
        // TODO: اعتبارسنجی فرمت شماره شبا

        if (empty($errors)) {
            debug_to_console('No validation errors for BANK step. Attempting to save...');
            try {
                $stmt = $pdo->prepare("UPDATE sellers SET account_holder_name=?, account_number=?, shaba_number=?, card_number=?, payment_id=?, registration_step='address' WHERE id=?");
                $stmt->execute([$form_data['account_holder_name'], $form_data['account_number'], $form_data['shaba_number'], $form_data['card_number'], $form_data['payment_id'], $seller_id]);
                header("Location: registerForm.php");
                exit();
            } catch (Exception $e) {
                $errors[] = 'خطا در ذخیره اطلاعات بانکی.';
                debug_to_console($e->getMessage(), 'DATABASE SAVE ERROR (Step 2)');
            }
        } else {
            // اگر خطای اعتبارسنجی وجود داشت، آن را هم به کنسول بفرست
            debug_to_console($errors, 'Validation Errors (Step 2)');
        }
    }

    // --- Step 3: Address Info ---
    if ($submitted_step === 'address') {
        $form_data['phone'] = to_english_nums(trim($_POST['phone'] ?? ''));
        $form_data['mobile'] = to_english_nums(trim($_POST['mobile'] ?? ''));
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['province'] = trim($_POST['province'] ?? '');
        $form_data['city'] = trim($_POST['city'] ?? '');
        $form_data['address'] = trim($_POST['address'] ?? '');
        $form_data['postal_code'] = to_english_nums(trim($_POST['postal_code'] ?? ''));

        if (empty($form_data['mobile'])) $errors[] = 'شماره تلفن همراه الزامی است.';
        // TODO: اعتبارسنجی فرمت ایمیل
        if (empty($form_data['province'])) $errors[] = 'استان الزامی است.';
        if (empty($form_data['city'])) $errors[] = 'شهر الزامی است.';
        if (empty($form_data['address'])) $errors[] = 'آدرس پستی الزامی است.';
        if (empty($form_data['postal_code'])) $errors[] = 'کد پستی الزامی است.';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE sellers SET phone=?, mobile=?, email=?, province=?, city=?, address=?, postal_code=?, registration_step='docs' WHERE id=?");
                $stmt->execute([$form_data['phone'], $form_data['mobile'], $form_data['email'], $form_data['province'], $form_data['city'], $form_data['address'], $form_data['postal_code'], $seller_id]);
                header("Location: registerForm.php");
                exit();
            } catch (Exception $e) {
                $errors[] = 'خطا در ذخیره اطلاعات آدرس.';
                error_log("Step 3 Save Error: " . $e->getMessage());
            }
        }
    }

    // --- Step 4: Documents ---
    if ($submitted_step === 'docs') {
        $upload_dir = __DIR__ . '/../upload/seller_docs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_fields = [
            'national_card_front' => 'national_card_path',
            'business_license' => 'business_license_path',
            'official_gazette' => 'official_gazette_path',
            'vat_certificate' => 'vat_certificate_path' // فیلد جدید مالیاتی
        ];
        
        $update_query_parts = [];
        $update_params = [];

        foreach ($file_fields as $input_name => $db_column) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES[$input_name]['tmp_name'];
                $file_name = $_FILES[$input_name]['name'];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                
                // ایجاد نام منحصر به فرد برای فایل
                $new_file_name = 'seller_' . $seller_id . '_' . $input_name . '_' . time() . '.' . $file_extension;
                $dest_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    $update_query_parts[] = "$db_column = ?";
                    $update_params[] = $new_file_name;
                } else {
                    $errors[] = "خطا در آپلود فایل: " . htmlspecialchars($file_name);
                }
            }
        }

        try {
            // فقط در صورتی که فایلی آپلود شده باشد، دیتابیس را آپدیت کن
            if (!empty($update_query_parts)) {
                $sql = "UPDATE sellers SET " . implode(', ', $update_query_parts) . " WHERE id = ?";
                $update_params[] = $seller_id;
                $stmt_files = $pdo->prepare($sql);
                $stmt_files->execute($update_params);
            }

            // در هر صورت به مرحله بعد برو
            $stmt_step = $pdo->prepare("UPDATE sellers SET registration_step='contract' WHERE id=?");
            $stmt_step->execute([$seller_id]);
            header("Location: registerForm.php"); // رفرش برای رفتن به تب بعدی
            exit();
        } catch (Exception $e) {
            $errors[] = 'خطا در پردازش مرحله مدارک.';
            error_log("Step 4 Save Error: " . $e->getMessage());
        }
    }

    // --- Step 5: Contract (Final Submission) ---
    if ($submitted_step === 'contract') {
        $agreement = isset($_POST['contract_agreement']);
        if (!$agreement) {
            $errors[] = 'شما باید قوانین و مقررات را بپذیرید.';
        }

        if (empty($errors)) {
            try {
                // وضعیت را به "در انتظار تایید" تغییر بده و مرحله ثبت‌نام را کامل کن
                $stmt = $pdo->prepare("UPDATE sellers SET status='pending', registration_step='completed' WHERE id=?");
                $stmt->execute([$seller_id]);

                // کاربر را لاگین نگه دار تا به صفحه انتظار هدایت شود
                unset($_SESSION['new_seller_id']);
                $_SESSION['seller_id'] = $seller_id;

                // هدایت به صفحه انتظار تایید
                header("Location: pending_approval.php");
                exit();
            } catch (Exception $e) {
                $errors[] = 'خطا در تکمیل نهایی ثبت‌نام.';
                error_log("Step 5 Save Error: " . $e->getMessage());
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
    <title>تکمیل اطلاعات ثبت‌نام - پنل فروشندگان</title>
    
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/assets/css/Vazirmatn-font-face.css">
    
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #fafcff;
        }
        .main-layout {
            display: flex;
            min-height: 100vh;
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            padding: 2rem;
        }
        .page-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        /* Custom Tab Styles to match nav.png */
        .nav-pills {
            background-color: #f8f9fa;
            border-radius: 50rem; /* Large radius for pill shape */
            padding: 0.4rem;
        }
        .nav-pills .nav-link {
            color: #6c757d;
            border: 1px solid transparent;
            border-radius: 50rem;
            transition: all 0.3s ease;
            padding: 0.6rem 1rem;
            font-weight: 500;
        }
        .nav-pills .nav-link.active, .nav-pills .show > .nav-link {
            color: #fff;
            background-color: #a18848; /* Gold/Brown color from image */
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        /* Custom Upload Box Styles */
        .upload-box {
            border: 2px dashed #dee2e6;
            border-radius: .375rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: background-color .2s ease, border-color .2s ease;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .upload-box:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }
        .upload-box .upload-icon {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }
        .upload-box .upload-title {
            font-weight: 500;
            color: #495057;
            margin-bottom: 1rem;
        }
        .upload-box .upload-btn {
            margin-top: auto;
        }
        .upload-box input[type="file"] { display: none; }
        /* Contract Box Styles */
        #contract-text-box {
            height: 300px;
            overflow-y: scroll;
            border: 1px solid #dee2e6;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: .375rem;
        }
    </style>
</head>
<body>

<div class="main-layout">
    <?php require_once __DIR__ . '/inc/register_sidebar.php'; ?>

    <div class="content-wrapper">
        <header class="page-header">
            <h1>تکمیل اطلاعات ثبت‌نام</h1>
        </header>

        <main>
                <!-- Tab Navigation -->
                <ul class="nav nav-pills nav-justified mb-4" id="registerTabs" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link" data-step-key="info" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1" type="button" role="tab">۱. اطلاعات فروشنده</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-step-key="bank" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2" type="button" role="tab">۲. اطلاعات حساب بانکی</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-step-key="address" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3" type="button" role="tab">۳. اطلاعات تماس و آدرس</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-step-key="docs" id="step4-tab" data-bs-toggle="tab" data-bs-target="#step4" type="button" role="tab">۴. بارگذاری مدارک</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-step-key="contract" id="step5-tab" data-bs-toggle="tab" data-bs-target="#step5" type="button" role="tab">۵. اطلاعات قرارداد</button></li>
                </ul>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <h5 class="alert-heading">خطا در اعتبارسنجی!</h5>
                        <?php foreach ($errors as $error): ?><p class="mb-1"><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Content -->
                <div class="tab-content card" id="registerTabsContent">
                    <div class="tab-pane fade" id="step1" role="tabpanel">
                        <form method="POST" action="registerForm.php" class="card-body p-0">
                            <div class="p-4">
                            <input type="hidden" name="submitted_step" value="info">
                            <!-- Card 1: Seller Information -->
                            <div class="card mb-4">
                                <div class="card-header"><h5 class="mb-0">اطلاعات فروشنده</h5></div>
                                <div class="card-body">
                                    <!-- ردیف اول -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label for="seller_type" class="form-label">نوع فروشنده <span class="text-danger">*</span></label>
                                            <select class="form-select" id="seller_type" name="seller_type">
                                                <option value="individual" <?php echo ($form_data['seller_type'] === 'individual') ? 'selected' : ''; ?>>حقیقی</option>
                                                <option value="legal" <?php echo ($form_data['seller_type'] === 'legal') ? 'selected' : ''; ?>>حقوقی</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4"><label for="first_name" class="form-label">نام <span class="text-danger individual-required-indicator">*</span></label><input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name']); ?>"></div>
                                        <div class="col-md-4"><label for="last_name" class="form-label">نام خانوادگی <span class="text-danger individual-required-indicator">*</span></label><input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name']); ?>"></div>
                                    </div>
                                    <!-- ردیف دوم: کدملی / نام پدر /تاریخ تولد -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label for="national_id" class="form-label">کد ملی <span class="text-danger individual-required-indicator">*</span></label>
                                            <input type="text" class="form-control" id="national_id" name="national_id" value="<?php echo htmlspecialchars(to_persian_nums($form_data['national_id'])); ?>">
                                            <div class="invalid-feedback">کد ملی وارد شده معتبر نیست.</div>
                                        </div>
                                        <div class="col-md-4"><label for="father_name" class="form-label">نام پدر</label><input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($form_data['father_name']); ?>"></div>
                                        <div class="col-md-4"><label for="birth_date" class="form-label">تاریخ تولد <span class="text-danger individual-required-indicator">*</span></label><input type="text" class="form-control" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars(to_jalali($form_data['birth_date'])); ?>" placeholder="مثال: ۱۳۷۰/۰۱/۰۱"></div>
                                    </div>
                                    <!-- ردیف سوم: شناسه صنفی / کد اقتصادی -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4"><label for="guild_id" class="form-label">شناسه صنفی <span class="text-danger legal-required-indicator">*</span></label><input type="text" class="form-control" id="guild_id" name="guild_id" value="<?php echo htmlspecialchars($form_data['guild_id']); ?>"></div>
                                        <div class="col-md-4"><label for="economic_code" class="form-label">کد اقتصادی <span class="text-danger legal-required-indicator">*</span></label><input type="text" class="form-control" id="economic_code" name="economic_code" value="<?php echo htmlspecialchars($form_data['economic_code']); ?>"></div>
                                        <div class="col-md-4"></div> <!-- ستون خالی برای حفظ تراز -->
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Shop Information -->
                            <div class="card">
                                <div class="card-header"><h5 class="mb-0">اطلاعات فروشگاه</h5></div>
                                <div class="card-body">
                                    <div class="row g-3 mb-3"><div class="col-md-6"><label for="shop_name" class="form-label">نام فارسی فروشگاه <span class="text-danger">*</span></label><input type="text" class="form-control" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($form_data['shop_name']); ?>"></div><div class="col-md-6"><label for="shop_name_en" class="form-label">نام انگلیسی فروشگاه</label><input type="text" class="form-control" id="shop_name_en" name="shop_name_en" value="<?php echo htmlspecialchars($form_data['shop_name_en']); ?>"></div></div>
                                    <div class="row g-3">
                                        <div class="col-md-6"><label for="credit_alias" class="form-label">نام مستعار اعتباری</label><input type="text" class="form-control" id="credit_alias" name="credit_alias" value="<?php echo htmlspecialchars($form_data['credit_alias']); ?>"></div>
                                        <div class="col-md-6"><label for="installment_alias" class="form-label">نام مستعار اقساطی</label><input type="text" class="form-control" id="installment_alias" name="installment_alias" value="<?php echo htmlspecialchars($form_data['installment_alias']); ?>"></div>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">بعدی و ادامه <i class="bi bi-arrow-left ms-2"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="step2" role="tabpanel">
                        <form method="POST" action="registerForm.php" class="card-body p-0">
                            <div class="p-4">
                                <input type="hidden" name="submitted_step" value="bank">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">اطلاعات حساب بانکی</h5></div>
                                    <div class="card-body">
                                        <div class="row g-3 mb-3"> <!-- ردیف اول -->
                                            <div class="col-md-4"><label for="account_holder_name" class="form-label">نام صاحب حساب <span class="text-danger">*</span></label><input type="text" class="form-control" id="account_holder_name" name="account_holder_name" value="<?php echo htmlspecialchars($form_data['account_holder_name']); ?>" required></div>
                                            <div class="col-md-4">
                                                <label for="shaba_number" class="form-label">شماره شبا (بدون IR) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="shaba_number" name="shaba_number" value="<?php echo htmlspecialchars($form_data['shaba_number']); ?>" required>
                                                <div class="invalid-feedback">شماره شبا نامعتبر است. باید ۲۴ رقم باشد.</div>
                                            </div>
                                            <div class="col-md-4"><label for="account_number" class="form-label">شماره حساب بانکی <span class="text-danger">*</span></label><input type="text" class="form-control" id="account_number" name="account_number" value="<?php echo htmlspecialchars($form_data['account_number']); ?>" required></div>
                                        </div>
                                        <div class="row g-3"> <!-- ردیف دوم -->
                                            <div class="col-md-6">
                                                <label for="card_number" class="form-label">شماره کارت</label>
                                                <input type="text" class="form-control" id="card_number" name="card_number" value="<?php echo htmlspecialchars($form_data['card_number']); ?>">
                                                <div class="invalid-feedback">شماره کارت نامعتبر است. باید ۱۶ رقم باشد.</div>
                                            </div>
                                            <div class="col-md-6"><label for="payment_id" class="form-label">شناسه پرداخت (اختیاری)</label><input type="text" class="form-control" id="payment_id" name="payment_id" value="<?php echo htmlspecialchars($form_data['payment_id']); ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between"><button type="button" class="btn btn-secondary prev-tab-btn"><i class="bi bi-arrow-right me-2"></i>قبلی</button><button type="submit" class="btn btn-primary">بعدی و ادامه <i class="bi bi-arrow-left ms-2"></i></button></div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="step3" role="tabpanel">
                        <form method="POST" action="registerForm.php" class="card-body p-0">
                            <div class="p-4">
                                <input type="hidden" name="submitted_step" value="address">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">اطلاعات تماس و آدرس</h5></div>
                                    <div class="card-body">
                                        <div class="row g-3 mb-3"> <!-- ردیف اول -->
                                            <div class="col-md-4"><label for="phone" class="form-label">شماره تلفن ثابت</label><input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($form_data['phone']); ?>"></div>
                                            <div class="col-md-4"><label for="mobile" class="form-label">شماره تلفن همراه <span class="text-danger">*</span></label><input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars(to_persian_nums($form_data['mobile'])); ?>" required></div>
                                            <div class="col-md-4">
                                                <label for="email" class="form-label">ایمیل</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>">
                                                <div class="invalid-feedback">فرمت ایمیل نامعتبر است.</div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3"> <!-- ردیف دوم -->
                                            <div class="col-md-4">
                                                <label for="province" class="form-label">استان <span class="text-danger">*</span></label>
                                                <select class="form-select" id="province" name="province" required>
                                                    <option value="">انتخاب کنید...</option>
                                                    <?php foreach (array_keys($iran_cities) as $province_name): ?>
                                                        <option value="<?php echo htmlspecialchars($province_name); ?>" <?php echo ($form_data['province'] === $province_name) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($province_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4"><label for="city" class="form-label">شهر <span class="text-danger">*</span></label><select class="form-select" id="city" name="city" required><option value="">ابتدا استان را انتخاب کنید</option></select></div>
                                            <div class="col-md-4">
                                                <label for="postal_code" class="form-label">کد پستی <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars(to_persian_nums($form_data['postal_code'])); ?>" required>
                                                <div class="invalid-feedback">کد پستی باید ۱۰ رقمی باشد.</div>
                                            </div>
                                        </div>
                                        <div class="row g-3"> <!-- ردیف سوم -->
                                            <div class="col-12"><label for="address" class="form-label">آدرس <span class="text-danger">*</span></label><textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($form_data['address']); ?></textarea></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between"><button type="button" class="btn btn-secondary prev-tab-btn"><i class="bi bi-arrow-right me-2"></i>قبلی</button><button type="submit" class="btn btn-primary">بعدی و ادامه <i class="bi bi-arrow-left ms-2"></i></button></div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="step4" role="tabpanel">
                        <form method="POST" action="registerForm.php" enctype="multipart/form-data" class="card-body p-0">
                            <div class="p-4">
                                <input type="hidden" name="submitted_step" value="docs">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">بارگذاری مدارک</h5></div>
                                    <div class="card-body">
                                        <p class="text-muted">در این بخش مدارک شناسایی و کسب‌وکار خود را بارگذاری کنید.</p>
                                        
                                        <!-- مدارک برای همه انواع فروشنده -->
                                        <div class="row g-4 mb-3">
                                            <div class="col-md-4">
                                                <label for="national_card_front" class="upload-box">
                                                    <i class="bi bi-person-vcard upload-icon"></i>
                                                    <span class="upload-title">کارت ملی / شناسنامه</span>
                                                    <input type="file" id="national_card_front" name="national_card_front" accept="image/*,application/pdf">
                                                    <span class="btn btn-outline-primary upload-btn"><i class="bi bi-upload me-2"></i>انتخاب فایل</span>
                                                    <small id="national_card_front_filename" class="form-text text-success mt-2"></small>
                                                </label>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="business_license" class="upload-box">
                                                    <i class="bi bi-briefcase upload-icon"></i>
                                                    <span class="upload-title">جواز کسب</span>
                                                    <input type="file" id="business_license" name="business_license" accept="image/*,application/pdf">
                                                    <span class="btn btn-outline-primary upload-btn"><i class="bi bi-upload me-2"></i>انتخاب فایل</span>
                                                    <small id="business_license_filename" class="form-text text-success mt-2"></small>
                                                </label>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="vat_certificate" class="upload-box">
                                                    <i class="bi bi-file-earmark-text upload-icon"></i>
                                                    <span class="upload-title">گواهی ارزش افزوده</span>
                                                    <input type="file" id="vat_certificate" name="vat_certificate" accept="image/*,application/pdf">
                                                    <span class="btn btn-outline-primary upload-btn"><i class="bi bi-upload me-2"></i>انتخاب فایل</span>
                                                    <small id="vat_certificate_filename" class="form-text text-success mt-2"></small>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- مدارک فقط برای فروشنده حقوقی -->
                                        <div id="legal_docs_uploads" class="d-none">
                                            <hr class="my-4">
                                            <h6 class="mb-3">مدارک شخص حقوقی</h6>
                                            <div class="row g-4">
                                                <div class="col-md-4">
                                                    <label for="official_gazette" class="upload-box">
                                                        <i class="bi bi-newspaper upload-icon"></i>
                                                        <span class="upload-title">روزنامه رسمی</span>
                                                        <input type="file" id="official_gazette" name="official_gazette" accept="image/*,application/pdf" class="legal-doc-input">
                                                        <span class="btn btn-outline-primary upload-btn"><i class="bi bi-upload me-2"></i>انتخاب فایل</span>
                                                        <small id="official_gazette_filename" class="form-text text-success mt-2"></small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between"><button type="button" class="btn btn-secondary prev-tab-btn"><i class="bi bi-arrow-right me-2"></i>قبلی</button><button type="submit" class="btn btn-primary">بعدی و ادامه <i class="bi bi-arrow-left ms-2"></i></button></div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="step5" role="tabpanel">
                        <form method="POST" action="registerForm.php" class="card-body p-0">
                            <div class="p-4">
                                <input type="hidden" name="submitted_step" value="contract">
                                <div class="card">
                                    <div class="card-header"><h5 class="mb-0">اطلاعات قرارداد</h5></div>
                                    <div class="card-body">
                                        <p class="text-muted">لطفاً شرایط و قوانین همکاری را با دقت مطالعه کرده و پس از اسکرول تا انتها، آن را تأیید نمایید.</p>
                                        
                                        <div id="contract-text-box" class="mb-3">
                                            <h6>قرارداد همکاری فروشندگان</h6>
                                            <p>این قرارداد بین فروشگاه آنلاین [نام فروشگاه شما] که در این قرارداد "پلتفرم" نامیده می‌شود، و فروشنده با مشخصات ثبت شده در پنل که "فروشنده" نامیده می‌شود، منعقد می‌گردد.</p>
                                            <p><strong>ماده ۱: موضوع قرارداد</strong><br>موضوع این قرارداد، همکاری فی‌مابین پلتفرم و فروشنده جهت عرضه و فروش محصولات فروشنده از طریق وب‌سایت و اپلیکیشن‌های پلتفرم می‌باشد.</p>
                                            <p><strong>ماده ۲: تعهدات فروشنده</strong><br>۲-۱- فروشنده متعهد است اطلاعات دقیق و صحیح محصولات خود، شامل نام، توضیحات، قیمت، موجودی و تصاویر باکیفیت را در پنل فروشندگان وارد نماید.<br>۲-۲- فروشنده مسئولیت کامل کیفیت، اصالت و گارانتی محصولات عرضه‌شده را بر عهده دارد.<br>۲-۳- فروشنده موظف است سفارشات ثبت‌شده را در بازه زمانی مشخص‌شده پردازش و برای ارسال آماده نماید.<br>۲-۴- قیمت‌گذاری محصولات باید رقابتی و مطابق با عرف بازار باشد. هرگونه گران‌فروشی یا قیمت‌گذاری نامتعارف منجر به جریمه یا تعلیق حساب کاربری خواهد شد.</p>
                                            <p><strong>ماده ۳: تعهدات پلتفرم</strong><br>۳-۱- پلتفرم متعهد به فراهم آوردن زیرساخت فنی لازم برای نمایش و فروش محصولات فروشنده می‌باشد.<br>۳-۲- پلتفرم مسئولیت بازاریابی، جذب مشتری و فرآیندهای پرداخت آنلاین را بر عهده دارد.<br>۳-۳- تسویه حساب با فروشنده بر اساس مبالغ فروش پس از کسر کمیسیون، طبق زمان‌بندی اعلام‌شده در پنل انجام خواهد شد.</p>
                                            <p><strong>ماده ۴: کمیسیون و هزینه‌ها</strong><br>میزان کمیسیون پلتفرم از فروش هر محصول، بر اساس دسته‌بندی کالا تعیین و در پنل فروشندگان به اطلاع فروشنده رسانده می‌شود. فروشنده با ثبت محصول در هر دسته‌بندی، موافقت خود را با نرخ کمیسیون آن اعلام می‌دارد.</p>
                                            <p><strong>ماده ۵: فسخ قرارداد</strong><br>هر یک از طرفین می‌توانند با اعلام کتبی ۳۰ روزه، این قرارداد را فسخ نمایند. در صورت تخطی فروشنده از هر یک از مفاد این قرارداد، پلتفرم مجاز است حساب کاربری وی را به صورت یک‌طرفه و بدون اطلاع قبلی مسدود نماید.</p>
                                            <p><strong>ماده ۶: حل اختلاف</strong><br>کلیه اختلافات ناشی از اجرای این قرارداد، ابتدا از طریق مذاکره حل و فصل خواهد شد و در صورت عدم توافق، مراجع قضایی صالح در شهر [نام شهر شما] به موضوع رسیدگی خواهند کرد.</p>
                                            <p>این قرارداد در ۶ ماده تنظیم و به امضای الکترونیکی طرفین رسیده و از تاریخ تأیید در پنل فروشندگان، برای هر دو طرف لازم‌الاجرا می‌باشد.</p>
                                            <p class="text-center fw-bold mt-4">-- پایان متن قرارداد --</p>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" name="contract_agreement" id="contract_agreement" required disabled>
                                            <label class="form-check-label" for="contract_agreement">
                                                متن کامل قرارداد همکاری را مطالعه کرده و تمامی مفاد آن را می‌پذیرم.
                                            </label>
                                        </div>
                                        <div class="d-grid mt-4">
                                            <button type="submit" id="final_submit_btn" class="btn btn-success btn-lg" disabled>تکمیل ثبت‌نام و ورود به پنل</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between"><button type="button" class="btn btn-secondary prev-tab-btn"><i class="bi bi-arrow-right me-2"></i>قبلی</button></div>
                        </form>
                    </div>
                </div>
        </main>
    </div>
</div>

<script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('#registerTabs .nav-link');
    const tabPanes = document.querySelectorAll('#registerTabsContent .tab-pane');

    // --- Logic for Seller Type (Individual/Legal) ---
    const sellerTypeSelect = document.getElementById('seller_type');
    const individualRequiredFields = document.querySelectorAll('#first_name, #last_name, #national_id, #birth_date');
    const legalRequiredFields = document.querySelectorAll('#guild_id, #economic_code');
    const individualRequiredIndicators = document.querySelectorAll('.individual-required-indicator');
    const legalRequiredIndicators = document.querySelectorAll('.legal-required-indicator');
    const legalDocsUploads = document.getElementById('legal_docs_uploads');

    // --- Logic for Contract Scroll and Agreement ---
    const contractBox = document.getElementById('contract-text-box');
    const agreementCheckbox = document.getElementById('contract_agreement');
    const finalSubmitBtn = document.getElementById('final_submit_btn');

    if (contractBox && agreementCheckbox && finalSubmitBtn) {
        contractBox.addEventListener('scroll', function() {
            // Check if user has scrolled to the bottom (with a 5px tolerance)
            if (this.scrollHeight - this.scrollTop - this.clientHeight < 5) {
                agreementCheckbox.disabled = false;
            }
        });

        agreementCheckbox.addEventListener('change', function() {
            if (this.checked) {
                finalSubmitBtn.disabled = false;
            } else {
                finalSubmitBtn.disabled = true;
            }
        });
    }

    // --- Live Validation for Bank Info ---
    const shabaInput = document.getElementById('shaba_number');
    const cardInput = document.getElementById('card_number');

    // تابع اعتبارسنجی شماره شبا (IBAN)
    function isValidShaba(shaba) {
        shaba = toEnglishDigits(shaba);
        if (!/^\d{24}$/.test(shaba)) return false;
        
        // الگوریتم اعتبارسنجی شبا
        const shabaWithIR = shaba + '242700'; // 24 for I, 27 for R
        let remainder = '';
        for (let i = 0; i < shabaWithIR.length; i++) {
            remainder = (remainder + shabaWithIR[i]) % 97;
        }
        return remainder === 1;
    }

    // تابع اعتبارسنجی شماره کارت (Luhn algorithm)
    function isValidCardNumber(cardNumber) {
        cardNumber = toEnglishDigits(cardNumber);
        if (!/^\d{16}$/.test(cardNumber)) return false;

        let sum = 0;
        let isSecond = false;
        for (let i = cardNumber.length - 1; i >= 0; i--) {
            let digit = parseInt(cardNumber.charAt(i), 10);
            if (isSecond) {
                digit = digit * 2;
                if (digit > 9) {
                    digit = digit - 9;
                }
            }
            sum += digit;
            isSecond = !isSecond;
        }
        return (sum % 10) === 0;
    }

    if (shabaInput) {
        shabaInput.addEventListener('input', function() {
            const value = this.value;
            if (value === '') {
                this.classList.remove('is-valid', 'is-invalid');
                return;
            }
            if (isValidShaba(value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }

    if (cardInput) {
        cardInput.addEventListener('input', function() {
            const value = this.value;
            if (value === '' || isValidCardNumber(value)) { // شماره کارت اختیاری است
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
            }
        });
    }

    // --- Live Validation for National ID ---
    const nationalIdInput = document.getElementById('national_id');

    function isValidNationalID(id) {
        if (!/^\d{10}$/.test(id)) return false;
        const check = +id[9];
        let sum = 0;
        for (let i = 0; i < 9; ++i) {
            sum += +id[i] * (10 - i);
        }
        const remainder = sum % 11;
        const expectedCheck = remainder < 2 ? remainder : 11 - remainder;
        return check === expectedCheck;
    }

    if (nationalIdInput) {
        nationalIdInput.addEventListener('input', function() {
            // ابتدا اعداد فارسی را به انگلیسی تبدیل می‌کنیم
            const englishValue = toEnglishDigits(this.value);
            
            // فقط در صورتی که طول کد ۱۰ رقم باشد، اعتبارسنجی را انجام می‌دهیم
            if (englishValue.length === 10) {
                if (isValidNationalID(englishValue)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                // اگر طول کد ۱۰ رقم نباشد، کلاس‌های اعتبارسنجی را حذف می‌کنیم
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    }

    // --- Live Validation for Email and Postal Code ---
    const emailInput = document.getElementById('email');
    const postalCodeInput = document.getElementById('postal_code');

    function toEnglishDigits(str) {
        if (!str) return '';
        return str.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
    }

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            // A simple regex for email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            // ایمیل می‌تواند خالی باشد (اختیاری)
            if (this.value === '' || emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
            }
        });
    }

    if (postalCodeInput) {
        postalCodeInput.addEventListener('input', function() {
            const englishValue = toEnglishDigits(this.value);
            // Iranian postal code is 10 digits
            const postalCodeRegex = /^[0-9]{10}$/;
            if (postalCodeRegex.test(englishValue)) {
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
            }
        });
    }

    // --- Logic for Dependent Province/City Dropdowns ---
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('city');
    const iranCities = <?php echo json_encode($iran_cities); ?>;
    const initialCity = '<?php echo addslashes($form_data['city']); ?>';

    function updateCities() {
        const selectedProvince = provinceSelect.value;
        citySelect.innerHTML = '<option value="">انتخاب کنید...</option>'; // Reset cities

        if (selectedProvince && iranCities[selectedProvince]) {
            iranCities[selectedProvince].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (city === initialCity) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        }
    }

    provinceSelect.addEventListener('change', updateCities);

    // اگر یک استان از قبل انتخاب شده بود، شهرها را در بارگذاری اولیه صفحه پر کن
    if (provinceSelect.value) {
        updateCities();
    }
    // --- End of Province/City Logic ---


    function toggleSellerTypeFields() {
        const isIndividual = sellerTypeSelect.value === 'individual';

        individualRequiredFields.forEach(input => input.required = isIndividual);
        individualRequiredIndicators.forEach(span => span.style.display = isIndividual ? '' : 'none');

        legalRequiredFields.forEach(input => input.required = !isIndividual);
        legalRequiredIndicators.forEach(span => span.style.display = !isIndividual ? '' : 'none');

        // مدارک حقوقی را الزامی یا غیرالزامی کن
        document.querySelectorAll('#legal_docs_uploads .legal-doc-input').forEach(input => {
            input.required = !isIndividual;
        });

        // Show/hide legal documents section
        if (legalDocsUploads) {
            legalDocsUploads.classList.toggle('d-none', isIndividual);
        }
    }

    // Map tab panes to sidebar step keys
    const stepKeys = ['info', 'bank', 'address', 'docs', 'contract'];
    let currentTab = 0;

    // Function to disable tabs that are not yet accessible
    function updateTabStates() {
        const currentStep = '<?php echo $current_step; ?>';
        const currentStepIndex = stepKeys.indexOf(currentStep);

        tabButtons.forEach((button, index) => {
            if (index > currentStepIndex) {
                button.disabled = true;
            }
        });
    }

    function goToTab(tabIndex) {
        if (tabIndex >= 0 && tabIndex < tabButtons.length) {
            const tab = new bootstrap.Tab(tabButtons[tabIndex]);
            tab.show();
            // Call toggleSellerTypeFields when tab changes, in case it's the first tab
            if (tabIndex === 0) {
                toggleSellerTypeFields();
            }
        }
    }

    // Function to update the sidebar based on the active tab index
    function updateSidebar(activeIndex) {
        stepKeys.forEach((key, index) => {
            const sidebarItem = document.getElementById(`sidebar-step-${key}`);
            if (!sidebarItem) return;
            sidebarItem.classList.remove('active', 'completed');
            if (index < activeIndex) {
                sidebarItem.classList.add('completed');
            } else if (index === activeIndex) {
                sidebarItem.classList.add('active');
            }
        });
    }

    // افزودن رویداد کلیک به تمام دکمه‌های "قبلی"
    document.querySelectorAll('.prev-tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            goToTab(currentTab - 1);
        });
    });

    // Update currentTab when a tab is shown
    tabPanes.forEach((pane, index) => {
        pane.addEventListener('shown.bs.tab', function() {
            currentTab = index;
            updateSidebar(index); // Update sidebar status
        });
    });

    // Determine which tab to show on page load
    const initialStep = '<?php echo $current_step; ?>';
    let initialTabIndex = stepKeys.indexOf(initialStep);
    if (initialTabIndex === -1 || initialTabIndex >= stepKeys.length) {
        initialTabIndex = 0; // Default to first tab
    }

    // Handle validation errors by switching to the first tab with an error
    <?php if (!empty($errors)): ?>
        // If there are errors, stay on the tab that was submitted
        const submittedStepOnError = '<?php echo $_POST['submitted_step'] ?? $current_step; ?>';
        initialTabIndex = stepKeys.indexOf(submittedStepOnError);
        if (initialTabIndex === -1) initialTabIndex = 0;
    <?php endif; ?>

    sellerTypeSelect.addEventListener('change', toggleSellerTypeFields);
    toggleSellerTypeFields(); // Initial call to set required attributes and indicators

    // --- Logic for Image Preview in Docs Tab ---
    function setupFileUploadDisplay(inputId) {
        const inputElement = document.getElementById(inputId);
        const filenameDisplay = document.getElementById(inputId + '_filename');
        
        if (inputElement && filenameDisplay) {
            // نمایش نام فایل موجود در بارگذاری اولیه صفحه
            const initialFilename = "<?php echo $form_data['national_card_filename'] ?? ''; ?>"; // مثال برای یک فیلد
            if (inputElement.id === 'national_card_front' && initialFilename) {
                 filenameDisplay.textContent = initialFilename;
            }
            // این بخش باید برای همه فیلدها تکرار شود

            inputElement.addEventListener('change', function(event) {
                if (event.target.files && event.target.files[0]) {
                    filenameDisplay.textContent = event.target.files[0].name;
                }
            });
        }
    }

    setupFileUploadDisplay('national_card_front');
    setupFileUploadDisplay('business_license');
    setupFileUploadDisplay('official_gazette');
    setupFileUploadDisplay('vat_certificate');

    // Disable future tabs on page load
    updateTabStates();

    // Initial state setup
    goToTab(initialTabIndex);
});
</script>
<?php $page_scripts = ob_get_clean(); ?>

<?php if (isset($page_scripts)) echo $page_scripts; ?>
</body>
</html>