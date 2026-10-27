<?php
// inc/functions.php

// تابع کمکی برای تبدیل اعداد به فرمت فارسی
function to_persian_num($string) {
    // آرایه‌ای از اعداد فارسی
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    // آرایه‌ای از اعداد انگلیسی
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    // تبدیل ورودی به رشته و جایگزینی اعداد انگلیسی با فارسی
    $persian_string = str_replace($english_digits, $persian_digits, (string)$string);
    return $persian_string;
}

// تابع کمکی برای تبدیل تاریخ میلادی به شمسی (جلالی)
// نیازمند فعال بودن افزونه intl در php.ini است
function to_jalali_date($gregorian_date_string, $format = 'yyyy/MM/dd') {
    if (empty($gregorian_date_string)) {
        return '';
    }
    $timestamp = strtotime($gregorian_date_string);
    $formatter = new IntlDateFormatter(
        'fa_IR@calendar=persian',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        'Asia/Tehran', // تنظیم منطقه زمانی برای دقت بیشتر
        IntlDateFormatter::TRADITIONAL,
        $format
    );
    return $formatter->format($timestamp);
}

/**
 * (جدید) تابعی برای تبدیل یک رشته به فرمت اسلاگ (مناسب برای URL)
 * @param string $string رشته ورودی (مثلا: نام محصول)
 * @return string رشته تبدیل شده
 */
function slugify($string) {
    // جایگزینی فاصله‌ها و کاراکترهای خاص با خط تیره
    $string = preg_replace('/[\s_]+/', '-', $string);
    // حذف کاراکترهایی که مجاز نیستند (فقط حروف فارسی و انگلیسی، اعداد و خط تیره مجاز هستند)
    $string = preg_replace('/[^\p{L}\p{N}-]+/u', '', $string);
    // حذف خط تیره‌های اضافی
    $string = preg_replace('/-+/', '-', $string);
    // حذف خط تیره از ابتدا و انتهای رشته
    return trim($string, '-');
}

// (جدید) تابع برای رمزگشایی محتوای Base64 از جاوااسکریپت (btoa(unescape(encodeURIComponent(...))))
function js_b64_decode($encoded_string) {
    return rawurldecode(base64_decode($encoded_string));
}

/**
 * (جدید) تعداد کل آیتم‌ها در سبد خرید را محاسبه می‌کند.
 * این تابع تعداد واقعی محصولات را جمع می‌زند، نه فقط تعداد ردیف‌ها.
 *
 * @return int تعداد کل آیتم‌ها.
 */
function get_cart_item_count(): int {
    $total_items = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        // جمع کردن مقدار 'quantity' برای هر آیتم در سبد خرید
        $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    return $total_items;
}

/**
 * (جدید) کالاهای منقضی شده را از سبد خرید حذف می‌کند (یک ساعت مهلت).
 */
function cleanup_expired_cart_items() {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $expiry_time = 3600; // 1 hour in seconds
        $now = time();
        $cart_changed = false;

        foreach ($_SESSION['cart'] as $offer_id => $item) {
            // اگر زمان اضافه شدن ثبت نشده بود (برای آیتم‌های قدیمی)، زمان فعلی را ثبت کن
            if (!isset($item['added_at'])) {
                $_SESSION['cart'][$offer_id]['added_at'] = $now;
                continue;
            }

            if (($now - $item['added_at']) >= $expiry_time) {
                unset($_SESSION['cart'][$offer_id]);
                $cart_changed = true;
            }
        }

        if ($cart_changed) {
            // (جدید) همگام‌سازی با دیتابیس در صورت تغییر سشن (حذف کالاهای منقضی شده)
            if (isset($_SESSION['customer_id'])) {
                sync_cart_to_draft_order($_SESSION['customer_id'], $_SESSION['cart']);
            }

            $_SESSION['message'] = 'برخی از کالاهای سبد خرید شما به دلیل گذشت زمان حذف شدند.';
            $_SESSION['message_type'] = 'warning';
        }
    }
}

/**
 * (جدید) محاسبه زمان باقی‌مانده تافضای سبد خرید (بر اساس قدیمی‌ترین آیتم)
 * @return int زمان باقی‌مانده به ثانیه (صفر اگر سبد خالی باشد)
 */
function get_cart_expiry_seconds(): int {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }

    $expiry_time = 3600; // 1 hour
    $now = time();
    $min_added_at = null;

    foreach ($_SESSION['cart'] as $item) {
        if (!isset($item['added_at'])) continue;
        if ($min_added_at === null || $item['added_at'] < $min_added_at) {
            $min_added_at = $item['added_at'];
        }
    }

    if ($min_added_at === null) return 0;

    $remaining = $expiry_time - ($now - $min_added_at);
    return max(0, $remaining);
}

/**
 * (اصلاح شده) همگام‌سازی سبد خرید با سفارش پیش‌نویس در دیتابیس
 * @param int $customer_id شناسه مشتری
 * @param array $cart_items محتویات سبد خرید از سشن
 */
function sync_cart_to_draft_order($customer_id, $cart_items) {
    global $conn;
    
    if (empty($customer_id)) return;

    // شروع تراکنش
    $conn->begin_transaction();

    try {
        // ۱. پیدا کردن سفارش پیش‌نویس فعلی مشتری (status_id = 1)
        $stmt = $conn->prepare("SELECT id FROM orders WHERE customer_id = ? AND status_id = 1 LIMIT 1");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $order_id = $res->fetch_assoc()['id'] ?? null;

        // اگر سبد خالی است و سفارش پیش‌نویس وجود دارد، آن را حذف کن
        if (empty($cart_items)) {
            if ($order_id) {
                $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
                $conn->query("DELETE FROM orders WHERE id = $order_id");
            }
            $conn->commit();
            return;
        }

        // ۲. محاسبه مبلغ کل و واکشی اطلاعات مورد نیاز برای order_items (شامل product_id)
        $total_amount = 0;
        $items_data = [];
        $offer_ids = array_keys($cart_items);
        $placeholders = implode(',', array_fill(0, count($offer_ids), '?'));
        $types = str_repeat('i', count($offer_ids));

        // (اصلاح) اضافه کردن جوین برای گرفتن product_id که اجباری است
        $stmt_offers = $conn->prepare("
            SELECT po.id, pv.product_id, po.variant_id, po.seller_id, po.price 
            FROM product_offers po
            JOIN product_variants pv ON po.variant_id = pv.id
            WHERE po.id IN ($placeholders)
        ");
        $stmt_offers->bind_param($types, ...$offer_ids);
        $stmt_offers->execute();
        $offers_res = $stmt_offers->get_result();

        while ($row = $offers_res->fetch_assoc()) {
            $qty = $cart_items[$row['id']]['quantity'];
            $row['quantity'] = $qty;
            $row['subtotal'] = $row['price'] * $qty; // (جدید) محاسبه ساب‌توتال اجباری
            $items_data[] = $row;
            $total_amount += $row['subtotal'];
        }

        // ۳. ایجاد یا به‌روزرسانی سفارش (status_id = 1)
        if ($order_id) {
            $stmt_u = $conn->prepare("UPDATE orders SET total_amount = ?, updated_at = NOW() WHERE id = ?");
            $stmt_u->bind_param("di", $total_amount, $order_id);
            if (!$stmt_u->execute()) {
                throw new Exception("Update Order failed: " . $stmt_u->error);
            }

            // حذف آیتم‌های قدیمی
            $conn->query("DELETE FROM order_items WHERE order_id = $order_id");
        } else {
            // (اصلاح) استفاده از status_id = 1 برای پیش‌نویس
            $dummy_address = "ثبت اولیه در سبد خرید";
            $stmt_i = $conn->prepare("INSERT INTO orders (customer_id, total_amount, status_id, shipping_address) VALUES (?, ?, 1, ?)");
            $stmt_i->bind_param("ids", $customer_id, $total_amount, $dummy_address);
            if (!$stmt_i->execute()) {
                throw new Exception("Insert Order failed: " . $stmt_i->error);
            }
            $order_id = $stmt_i->insert_id;
        }

        // ۴. ثبت آیتم‌های جدید (با رعایت فیلدهای اجباری جدید)
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, variant_id, offer_id, seller_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items_data as $item) {
            $stmt_item->bind_param("iiiiiiid", $order_id, $item['product_id'], $item['variant_id'], $item['id'], $item['seller_id'], $item['quantity'], $item['price'], $item['subtotal']);
            if (!$stmt_item->execute()) {
                 error_log("Draft Sync Error (Items): " . $stmt_item->error);
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Draft Sync Exception: " . $e->getMessage());
    }
}

/**
 * (جدید) بررسی وجود سفارش پیش‌نویس در دیتابیس برای مشتری لاگین شده
 * اگر سفارش در دیتابیس (توسط ادمین) حذف شده باشد، سبد خرید محلی نیز پاک می‌شود.
 */
function validate_customer_cart_from_db($customer_id) {
    global $conn;
    
    // اگر مشتری لاگین نیست یا سبد خرید خالی است، نیازی به بررسی نیست
    if (empty($customer_id) || empty($_SESSION['cart'])) {
        return;
    }

    // بررسی وجود سفارش پیش‌نویس (status_id = 1)
    $stmt = $conn->prepare("SELECT id FROM orders WHERE customer_id = ? AND status_id = 1 LIMIT 1");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;

    // اگر در دیتابیس وجود ندارد، سبد خرید را پاک کن
    if (!$exists) {
        unset($_SESSION['cart']);
        // همچنین زمان انقضا را هم پاک کن (اگر وجود داشته باشد)
        // unset($_SESSION['cart_expiry']); // در صورت استفاده از سیستم انقضا
    }
}
