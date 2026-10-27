<?php
/**
 * تبدیل اعداد انگلیسی به فارسی
 * @param string $string
 * @return string
 */
function to_persian_nums($string) {
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($english_digits, $persian_digits, $string);
}

/**
 * تبدیل اعداد فارسی و جداکننده‌ها به عدد انگلیسی خالص
 * @param string $string
 * @return string
 */
function to_english_nums($string) {
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $cleaned_string = str_replace($persian_digits, $english_digits, $string);
    return preg_replace('/[^0-9.]/', '', $cleaned_string);
}

// فراخوانی کتابخانه تاریخ شمسی
require_once __DIR__ . '/jdf.php';

/**
 * تبدیل تاریخ میلادی به شمسی (نیاز به کتابخانه جلالی دارد)
 * این یک تابع ساده برای نمایش است. برای محاسبات پیچیده از کتابخانه استفاده کنید.
 * @param string $gregorian_date (YYYY-MM-DD)
 * @param string $format
 * @return string
 */
function to_jalali($gregorian_date, $format = 'Y/m/d') {
    if (empty($gregorian_date) || substr($gregorian_date, 0, 10) === '0000-00-00') return '';
    $time = strtotime($gregorian_date);
    return jdate($format, $time); // استفاده از تابع کتابخانه jdf
}

/**
 * تبدیل تاریخ شمسی (YYYY/MM/DD) به میلادی (YYYY-MM-DD)
 * @param string|null $jalali_date
 * @return string|null
 */
function to_gregorian($jalali_date) {
    if (empty($jalali_date)) {
        return null;
    }
    // ابتدا اعداد فارسی را به انگلیسی تبدیل می‌کنیم
    $jalali_date = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $jalali_date);
    
    // تاریخ را به سال، ماه و روز تقسیم می‌کنیم
    $parts = preg_split('/[-\/]/', $jalali_date);
    if (count($parts) !== 3) {
        return null; // فرمت نامعتبر
    }
    list($j_y, $j_m, $j_d) = $parts;

    // استفاده از تابع jmktime برای تبدیل تاریخ شمسی به timestamp
    $timestamp = jmktime(0, 0, 0, (int)$j_m, (int)$j_d, (int)$j_y);
    // تبدیل timestamp به تاریخ میلادی با فرمت استاندارد
    return date('Y-m-d', $timestamp);
}

/**
 * تاریخ را به صورت "N دقیقه/ساعت/روز پیش" نمایش می‌دهد.
 * @param string|null $timestamp تاریخ و زمان با فرمت 'YYYY-MM-DD HH:MM:SS'
 * @return string
 */
function time_ago_in_persian($timestamp) {
    if (!$timestamp) {
        return '-';
    }

    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;

    if ($seconds < 60) { // زیر ۱ دقیقه
        return to_persian_nums($seconds) . " ثانیه پیش";
    }

    $minutes = round($seconds / 60);
    if ($minutes < 60) { // زیر ۱ ساعت
        return to_persian_nums($minutes) . " دقیقه پیش";
    }

    $hours = floor($seconds / 3600);
    if ($hours < 24) { // زیر ۲۴ ساعت
        $rem_minutes = round(($seconds % 3600) / 60);
        $hour_part = to_persian_nums($hours) . " ساعت";
        if ($rem_minutes > 0) {
            return $hour_part . " و " . to_persian_nums($rem_minutes) . " دقیقه پیش";
        }
        return $hour_part . " پیش";
    }

    if ($hours < 72) { // زیر ۷۲ ساعت
        return to_persian_nums($hours) . " ساعت پیش";
    }

    $days = floor($seconds / 86400);
    if ($days < 30) { // زیر ۳۰ روز
        return to_persian_nums($days) . " روز پیش";
    }

    // برای بیشتر از ۳۰ روز، تاریخ کامل شمسی را نمایش بده
    return to_jalali($timestamp, 'Y/m/d');
}

/**
 * بررسی اینکه آیا کاربر وارد شده است یا خیر
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * بررسی سطح دسترسی کاربر برای یک عمل خاص
 * @param string $permission_key کلید دسترسی (مثلا 'manage_users')
 * @param PDO $pdo آبجکت اتصال به دیتابیس
 * @return bool
 */
function has_permission($permission_key, $pdo) {
    // اگر کاربر لاگین نکرده باشد، هیچ دسترسی‌ای ندارد
    if (!is_logged_in()) {
        return false;
    }

    // سوپر ادمین همیشه به همه چیز دسترسی دارد (role_id = 1)
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        return true;
    }

    // بررسی دسترسی برای سایر نقش‌ها
    try {
        $sql = "SELECT COUNT(*)
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id AND p.permission_key = :permission_key";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':role_id' => $_SESSION['role_id'],
            ':permission_key' => $permission_key
        ]);
        
        return $stmt->fetchColumn() > 0;

    } catch (PDOException $e) {
        // در صورت بروز خطا، دسترسی را قطع کن
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * اگر کاربر دسترسی نداشته باشد، او را به صفحه دیگری هدایت می‌کند
 * @param string $permission_key
 * @param PDO $pdo
 */
function require_permission($permission_key, $pdo) {
    if (!has_permission($permission_key, $pdo)) {
        // می‌توانید به صفحه "دسترسی غیرمجاز" هدایت کنید
        // یا به داشبورد با یک پیام خطا
        $_SESSION['error_message'] = "شما دسترسی لازم برای مشاهده این صفحه را ندارید.";
        header("Location: " . BASE_URL . "dashboard.php");
        exit();
    }
}

/**
 * تابع بازگشتی برای نمایش گزینه‌های دسته‌بندی به صورت درختی در یک select box
 * @param array $categories آرایه‌ای از تمام دسته‌بندی‌ها
 * @param int|null $parent_id شناسه والد برای شروع
 * @param string $prefix پیشوند برای نمایش تو در تو بودن
 * @param int|null $current_category_id شناسه دسته‌بندی فعلی (برای جلوگیری از انتخاب خودش به عنوان والد)
 * @param int|null $selected_id شناسه گزینه انتخاب شده (برای selected کردن)
 */
function display_categories_options($categories, $parent_id = null, $prefix = '', $current_category_id = null, $selected_id = null) {
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parent_id) {
            // یک دسته نمی‌تواند والد خودش باشد (مربوط به صفحه ویرایش دسته‌بندی)
            if ($current_category_id !== null && $category['id'] == $current_category_id) {
                continue;
            }
            
            $selected_attr = ($selected_id !== null && (int)$category['id'] === (int)$selected_id) ? 'selected' : '';
            
            echo '<option value="' . $category['id'] . '" ' . $selected_attr . '>' . $prefix . htmlspecialchars($category['name']) . '</option>';
            display_categories_options($categories, $category['id'], $prefix . '— ', $current_category_id, $selected_id);
        }
    }
}

/**
 * یک رشته HTML برای نمایش دکمه‌های صفحه‌بندی (Pagination) ایجاد می‌کند.
 * @param string $base_url آدرس پایه صفحه (مثلا 'users.php')
 * @param int $current_page شماره صفحه فعلی
 * @param int $total_pages تعداد کل صفحات
 * @param array $extra_params پارامترهای اضافی برای query string (مثلا برای جستجو)
 * @return string
 */
function render_pagination($base_url, $current_page, $total_pages, $extra_params = []) {
    if ($total_pages <= 1) {
        return '';
    }

    $query_string = http_build_query(array_filter($extra_params));
    $query_string = $query_string ? '&' . $query_string : '';

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';

    // دکمه "قبلی"
    $prev_disabled = ($current_page <= 1) ? 'disabled' : '';
    $html .= "<li class='page-item {$prev_disabled}'><a class='page-link' href='{$base_url}?page=" . ($current_page - 1) . "{$query_string}'>قبلی</a></li>";

    // منطق نمایش شماره صفحات
    $max_visible_pages = 7;
    $start_page = max(1, $current_page - floor($max_visible_pages / 2));
    $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
    if ($end_page - $start_page + 1 < $max_visible_pages) {
        $start_page = max(1, $end_page - $max_visible_pages + 1);
    }

    if ($start_page > 1) {
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}?page=1{$query_string}'>1</a></li>";
        if ($start_page > 2) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
    }

    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $current_page) ? 'active' : '';
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$base_url}?page={$i}{$query_string}'>" . to_persian_nums($i) . "</a></li>";
    }

    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        $html .= "<li class='page-item'><a class='page-link' href='{$base_url}?page={$total_pages}{$query_string}'>" . to_persian_nums($total_pages) . "</a></li>";
    }

    // دکمه "بعدی"
    $next_disabled = ($current_page >= $total_pages) ? 'disabled' : '';
    $html .= "<li class='page-item {$next_disabled}'><a class='page-link' href='{$base_url}?page=" . ($current_page + 1) . "{$query_string}'>بعدی</a></li>";

    $html .= '</ul></nav>';
    return $html;
}