<?php
require_once 'inc/config.php';
require_once 'inc/functions.php'; // (جدید) برای استفاده از تابع slugify

header('Content-Type: application/json; charset=utf-8');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// اگر عبارت جستجو کمتر از ۲ کاراکتر بود، نتیجه‌ای برنگردان
if (mb_strlen($query, 'UTF-8') < 2) {
    echo json_encode([]);
    exit();
}

$search_term = "%" . $query . "%";

// کوئری برای یافتن محصولات منطبق
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.image,
        p.name_en,
        MIN(po.price) as min_price
    FROM products p
    JOIN product_variants pv ON p.id = pv.product_id
    JOIN product_offers po ON pv.id = po.variant_id
    WHERE 
        (p.name LIKE ? OR p.name_en LIKE ?)
        AND po.is_active = 1 AND po.stock > 0
    GROUP BY p.id
    ORDER BY 
        CASE 
            WHEN p.name LIKE ? THEN 1
            ELSE 2
        END, p.name
    LIMIT 7
");
$stmt->bind_param("sss", $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

// (جدید) افزودن اسلاگ به هر محصول
foreach ($products as &$product) {
    $product['slug'] = slugify($product['name']);
}
unset($product); // پاک کردن رفرنس

echo json_encode($products);
?>