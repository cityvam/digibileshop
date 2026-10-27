<?php
// (بهبود) استفاده از require_once برای فایل‌های ضروری
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/query_builder.php';

// تعداد محصولات برای نمایش در هر صفحه
$products_per_page = 30;

// 1. گرفتن و پاک‌سازی پارامترهای جستجو و فیلتر از URL
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$selected_brands = isset($_GET['brands']) && is_array($_GET['brands']) ? $_GET['brands'] : [];
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (int)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (int)$_GET['max_price'] : '';
$selected_colors = isset($_GET['colors']) && is_array($_GET['colors']) ? $_GET['colors'] : [];
$in_stock_only = !isset($_GET['in_stock']) || $_GET['in_stock'] == '1'; // به طور پیش‌فرض فعال است
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// محاسبه offset برای کوئری
$offset = ($page - 1) * $products_per_page;

// 2. (جدید) ساخت کوئری با استفاده از تابع مشترک
$filters = [
    'category_id' => null,
    'selected_brands' => $selected_brands,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'selected_colors' => $selected_colors,
    'in_stock_only' => $in_stock_only,
    'min_rating' => null,
    'search_query' => $search_query
];

$query_parts = build_products_query($filters, $sort_order, false); // false چون نیازی به SQL_CALC_FOUND_ROWS نداریم
$sql = $query_parts['sql'];
$params = $query_parts['params'];
$types = $query_parts['types'];

// افزودن LIMIT و OFFSET برای صفحه‌بندی
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

if ($result && $result->num_rows > 0) {
    while($product = $result->fetch_assoc()) {
        // استفاده از قالب برای نمایش هر محصول
        include 'product_card.php';
    }
}
// اگر نتیجه‌ای نباشد، این فایل هیچ خروجی‌ای نخواهد داشت
?>