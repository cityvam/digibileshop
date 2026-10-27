<?php
// (بهبود) استفاده از require_once برای فایل‌های ضروری جهت جلوگیری از خطاهای احتمالی
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/query_builder.php'; // (جدید) فراخوانی فایل سازنده کوئری
// این فایل مشابه ajax_search_handler.php است اما برای صفحه products.php استفاده می‌شود

$products_per_page = 9;

// 1. گرفتن و پاک‌سازی پارامترهای فیلتر از URL
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$selected_brands = [];
if (isset($_GET['brands'])) {
    if (is_array($_GET['brands'])) {
        // Handles standard format from AJAX: brands[]=1&brands[]=2
        $selected_brands = $_GET['brands'];
    } else if (!empty($_GET['brands'])) {
        // Handles comma-separated format from pretty URL: brands=1,2,3
        // (اصلاح) حذف براکت‌ها برای پشتیبانی از هر دو فرمت
        $selected_brands = explode(',', str_replace(['[', ']'], '', $_GET['brands']));
    }
    // (بهبود) استفاده از array_filter برای حذف مقادیر خالی احتمالی
    $selected_brands = array_filter(array_map('intval', $selected_brands));
}
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (int)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (int)$_GET['max_price'] : '';
$selected_colors = isset($_GET['colors']) && is_array($_GET['colors']) ? $_GET['colors'] : [];
$in_stock_only = !isset($_GET['in_stock']) || $_GET['in_stock'] == '1'; // به طور پیش‌فرض فعال است
$min_rating = isset($_GET['rating']) && is_numeric($_GET['rating']) ? (float)$_GET['rating'] : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['q']) ? trim($_GET['q']) : ''; // (جدید) خواندن پارامتر جستجو
$get_total = isset($_GET['get_total']) && $_GET['get_total'] == '1'; // (جدید) پارامتر برای دریافت تعداد کل

// محاسبه offset برای کوئری
$offset = ($page - 1) * $products_per_page;

// 2. (جدید) ساخت کوئری با استفاده از تابع مشترک
$filters = [
    'category_id' => $category_id,
    'selected_brands' => $selected_brands,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'selected_colors' => $selected_colors,
    'in_stock_only' => $in_stock_only,
    'min_rating' => $min_rating,
    'search_query' => $search_query // (اصلاح) ارسال پارامتر جستجو به تابع
];

$query_parts = build_products_query($filters, $sort_order, $get_total); // (تغییر) فعال کردن SQL_CALC_FOUND_ROWS در صورت نیاز
$sql = $query_parts['sql'];
$params = $query_parts['params'];
$types = $query_parts['types'];

$sql .= " LIMIT ? OFFSET ?";
$params[] = $products_per_page;
$params[] = $offset;
$types .= 'ii';

// 3. اجرای کوئری و نمایش نتایج
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// (جدید) دریافت تعداد کل محصولات اگر درخواست شده باشد
$total_products = 0;
if ($get_total) {
    $total_products = $conn->query("SELECT FOUND_ROWS()")->fetch_row()[0];
}

$html = '';
if ($result && $result->num_rows > 0) {
    ob_start(); // شروع بافر خروجی
    while($product = $result->fetch_assoc()) {
        echo '<div class="col">';
        include 'product_card.php';
        echo '</div>';
    }
    $html = ob_get_clean(); // دریافت محتوای بافر و پاک کردن آن
}

// (جدید) ارسال پاسخ به صورت JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html, 
    'total_products' => (int)$total_products
]);

?>