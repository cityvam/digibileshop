<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';

// اطمینان از اینکه session شروع شده و سبد خرید به عنوان یک آرایه وجود دارد
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// (اصلاح) تشخیص اکشن از POST یا GET برای انعطاف‌پذیری بیشتر
$action = $_POST['action'] ?? $_GET['action'] ?? '';
// (جدید) تشخیص اینکه آیا درخواست از طریق AJAX ارسال شده است
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// (جدید) تشخیص درخواست آزمایشی (فقط برای گرفتن اطلاعات بدون افزودن به سبد)
$dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] == '1';

// (اصلاح کامل) منطق افزودن محصول به سبد خرید با بررسی موجودی انبار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $offer_id = (int)($_POST['offer_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $insurance_ids = isset($_POST['insurance']) && is_array($_POST['insurance']) ? $_POST['insurance'] : [];

    // (اصلاح) شرط quantity > 0 حذف شد تا امکان آپدیت بیمه با ارسال quantity=0 فراهم شود.
    if ($offer_id > 0) {
        // بررسی موجودی انبار
        $stmt = $conn->prepare("SELECT stock FROM product_offers WHERE id = ?");
        $stmt->bind_param("i", $offer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $offer = $result->fetch_assoc();
            $current_stock = $offer['stock'];
            $current_quantity_in_cart = $_SESSION['cart'][$offer_id]['quantity'] ?? 0;
            
            // (اصلاح) بررسی موجودی فقط زمانی انجام شود که تعداد در حال افزایش است
            if (($current_quantity_in_cart + $quantity) > $current_stock) {
                $message = 'متاسفانه موجودی این محصول برای تعداد درخواستی شما کافی نیست.';
                $status = 'error';
            } else {
                // (جدید) اگر dry_run باشد، تغییری در سشن ایجاد نمی‌کنیم
                if (!$dry_run) {
                    // اگر محصول قبلا در سبد بوده، فقط تعداد را اضافه کن
                    if (isset($_SESSION['cart'][$offer_id])) {
                        $_SESSION['cart'][$offer_id]['quantity'] += $quantity;
                        // (جدید) بیمه‌ها را جایگزین می‌کنیم
                        $_SESSION['cart'][$offer_id]['insurance_ids'] = $insurance_ids;
                    } elseif ($quantity > 0) { // (اصلاح) محصول جدید فقط در صورتی اضافه شود که تعداد بیشتر از صفر باشد
                        // در غیر این صورت، محصول جدید را با تعداد مشخص شده اضافه کن
                        $_SESSION['cart'][$offer_id] = [
                            'quantity' => $quantity,
                            'insurance_ids' => $insurance_ids,
                            'added_at' => time() // (جدید) زمان اضافه شدن محصول
                        ];
                    }
                }
                $message = 'محصول با موفقیت به سبد خرید اضافه شد.';
                $status = 'success';

                // (اصلاح) واکشی تمام بیمه‌های فعال و اطلاعات محصول برای ارسال در پاسخ ایجکس
                $response_data = [];
                if ($is_ajax) {
                    // (جدید) واکشی product_id از offer_id برای پیدا کردن بیمه‌های مرتبط
                    $stmt_prod_id = $conn->prepare("
                        SELECT pv.product_id 
                        FROM product_offers po
                        JOIN product_variants pv ON po.variant_id = pv.id
                        WHERE po.id = ?
                    ");
                    $stmt_prod_id->bind_param("i", $offer_id);
                    $stmt_prod_id->execute();
                    $product_id_res = $stmt_prod_id->get_result()->fetch_assoc();
                    $product_id = $product_id_res['product_id'] ?? null;

                    // واکشی قیمت پایه محصول
                    $stmt_offer_price = $conn->prepare("SELECT price FROM product_offers WHERE id = ?");
                    $stmt_offer_price->bind_param("i", $offer_id);
                    $stmt_offer_price->execute();
                    $offer_price_result = $stmt_offer_price->get_result()->fetch_assoc();
                    $response_data['base_price'] = $offer_price_result['price'] ?? 0;
                    
                    // (اصلاح) واکشی بیمه‌های فعال مرتبط با محصول، نه همه بیمه‌ها
                    $available_insurances = [];
                    if ($product_id) {
                        $insurances_stmt = $conn->prepare("
                            SELECT i.id, i.name, i.description, i.price_percentage, i.duration_months 
                            FROM insurances i
                            JOIN product_insurances pi ON i.id = pi.insurance_id
                            WHERE pi.product_id = ? AND i.status = 'active'
                        ");
                        $insurances_stmt->bind_param("i", $product_id);
                        $insurances_stmt->execute();
                        $available_insurances = $insurances_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    }
                    $response_data['available_insurances'] = $available_insurances;

                    // مشخص کردن بیمه‌هایی که همین الان انتخاب شده‌اند
                    $response_data['selected_insurance_ids'] = $insurance_ids;
                    $response_data['offer_id'] = $offer_id; // ارسال offer_id برای آپدیت‌های بعدی
                }
            }
        } else {
            $message = 'محصولی با این مشخصات یافت نشد.';
            $status = 'error';
        }
    } else {
        $message = 'اطلاعات ارسال شده نامعتبر است.';
        $status = 'error';
    }

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id']) && $status === 'success' && !$dry_run) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }

    // (جدید) بر اساس نوع درخواست، پاسخ مناسب را ارسال کن
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'cart_item_count' => get_cart_item_count(), // تابع کمکی برای شمارش آیتم‌ها
            'data' => $response_data ?? [] // (اصلاح) ارسال تمام داده‌های مورد نیاز به صورت یکجا
        ]);
        exit();
    } else {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = ($status === 'success') ? 'success' : 'danger';
        // بازگشت به صفحه‌ای که کاربر از آن آمده است
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit();
    }
}

// (اصلاح) منطق افزایش تعداد محصول از صفحه سبد خرید
if ($action === 'increase') {
    $offer_id = (int)($_GET['offer_id'] ?? 0);

    if ($offer_id > 0 && isset($_SESSION['cart'][$offer_id])) {
        // TODO: می‌توان موجودی انبار را قبل از افزایش چک کرد
        $_SESSION['cart'][$offer_id]['quantity']++;
        $_SESSION['cart'][$offer_id]['added_at'] = time(); // (جدید) به‌روزرسانی زمان فعالیت
        $_SESSION['message'] = 'سبد خرید شما به‌روزرسانی شد.';
        $_SESSION['message_type'] = 'success';
    }

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id'])) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }

    // بازگشت به صفحه سبد خرید
    header("Location: cart.php");
    exit();
}

// (اصلاح) منطق کاهش تعداد محصول از صفحه سبد خرید
if ($action === 'decrease') {
    $offer_id = (int)($_GET['offer_id'] ?? 0);
    if ($offer_id > 0 && isset($_SESSION['cart'][$offer_id])) {
        // TODO: می‌توان موجودی انبار را قبل از کاهش چک کرد (اگر نیاز باشد)
        $_SESSION['cart'][$offer_id]['quantity']--;
        $_SESSION['cart'][$offer_id]['added_at'] = time(); // (جدید) به‌روزرسانی زمان فعالیت
        // اگر تعداد به صفر رسید، محصول را حذف کن
        if ($_SESSION['cart'][$offer_id]['quantity'] <= 0) {
            unset($_SESSION['cart'][$offer_id]);
            $_SESSION['message'] = 'محصول از سبد خرید شما حذف شد.';
            $_SESSION['message_type'] = 'info';
        } else { // اگر تعداد هنوز مثبت بود
            $_SESSION['message'] = 'سبد خرید شما به‌روزرسانی شد.';
            $_SESSION['message_type'] = 'success';
        }
    }

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id'])) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }
    // بازگشت به صفحه سبد خرید
    header("Location: cart.php");
    exit();
}

// (جدید) منطق خالی کردن کامل سبد خرید
if ($action === 'clear') {
    unset($_SESSION['cart']);
    $_SESSION['message'] = 'سبد خرید شما با موفقیت خالی شد.';
    $_SESSION['message_type'] = 'success';

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id'])) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }

    header("Location: cart.php");
    exit();
}

// (اصلاح کامل) منطق به‌روزرسانی سبد خرید از صفحه cart.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $quantities = $_POST['quantities'] ?? [];
    foreach ($quantities as $offer_id => $quantity) {
        $offer_id = (int)$offer_id;
        $quantity = (int)$quantity;

        if ($offer_id > 0 && isset($_SESSION['cart'][$offer_id])) {
            if ($quantity > 0) {
                // TODO: در اینجا هم می‌توان موجودی انبار را چک کرد
                $_SESSION['cart'][$offer_id]['quantity'] = $quantity;
            } else {
                // اگر تعداد صفر یا کمتر بود، محصول را حذف کن
                unset($_SESSION['cart'][$offer_id]);
            }
        }
    }
    $_SESSION['message'] = 'سبد خرید شما به‌روزرسانی شد.';
    $_SESSION['message_type'] = 'success';

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id'])) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }
    // بازگشت به صفحه سبد خرید
    header("Location: cart.php");
    exit();
}

// (اصلاح کامل) منطق حذف یک محصول از سبد خرید
if ($action === 'remove') {
    $offer_id = (int)($_GET['remove'] ?? 0); // (اصلاح) خواندن offer_id از پارامتر 'remove'
    if ($offer_id > 0 && isset($_SESSION['cart'][$offer_id])) {
        unset($_SESSION['cart'][$offer_id]);
        $_SESSION['message'] = 'محصول از سبد خرید شما حذف شد.';
        $_SESSION['message_type'] = 'info';
    }

    // (جدید) همگام‌سازی با دیتابیس اگر کاربر لاگین است
    if (isset($_SESSION['customer_id'])) {
        sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
    }
    // بازگشت به صفحه سبد خرید
    header("Location: cart.php");
    exit();
}

// اگر هیچ اکشنی مشخص نشده بود، به صفحه اصلی برگرد
header("Location: index.php");
exit();
?>