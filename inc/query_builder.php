<?php
function build_products_query($filters, $sort_order, $calc_found_rows = false) {
    // کوئری پایه با اتصال به جداول مورد نیاز
    $sql = "SELECT " . ($calc_found_rows ? "SQL_CALC_FOUND_ROWS " : "") . "p.*, pb.name as brand_name, pb.name_en as brand_name_en,
            (SELECT MIN(po.price) FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id AND po.is_active = 1) as min_price,
            (SELECT MAX(po.price) FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id AND po.is_active = 1) as max_price
            FROM products p
            LEFT JOIN product_brands pb ON p.brand_id = pb.id
            LEFT JOIN product_variants pv ON p.id = pv.product_id";

    $params = [];
    $types = "";
    $where = ["p.status = 'active'"]; // (اصلاح) شرط فعال بودن محصول به عنوان پایه اضافه شد

    // فیلتر دسته‌بندی
    if (!empty($filters['category_id'])) {
        $where[] = "p.category_id = ?";
        $params[] = $filters['category_id'];
        $types .= "i";
    }

    // فیلتر برندها
    if (!empty($filters['selected_brands'])) {
        $placeholders = implode(',', array_fill(0, count($filters['selected_brands']), '?'));
        $where[] = "p.brand_id IN ($placeholders)";
        foreach ($filters['selected_brands'] as $id) {
            $params[] = $id;
            $types .= "i";
        }
    }

    // فیلتر رنگ‌ها (با استفاده از TRIM برای حذف فاصله‌های اضافی احتمالی)
    if (!empty($filters['selected_colors'])) {
        $placeholders = implode(',', array_fill(0, count($filters['selected_colors']), '?'));
        $where[] = "TRIM(pv.color_code) IN ($placeholders)";
        foreach ($filters['selected_colors'] as $color) {
            $params[] = $color;
            $types .= "s";
        }
    }

    // جستجو (در نام محصول، توضیحات، نام فارسی و انگلیسی برند)
    if (!empty($filters['search_query'])) {
        // (اصلاح) تقسیم عبارت جستجو به کلمات جداگانه برای جستجوی دقیق‌تر
        $search_terms = explode(' ', $filters['search_query']);
        $search_conditions = [];
        
        foreach ($search_terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;

            // (جدید) بهبود جستجو برای کلمات تک‌حرفی انگلیسی (مثل A, S, Z)
            // جلوگیری از پیدا شدن این حروف در وسط کلمات دیگر (مثلا a در Galaxy)
            if (strlen($term) === 1 && preg_match('/^[a-zA-Z]$/', $term)) {
                // (اصلاح) برای حروف تنها، از REGEXP برای جستجوی کلمه کامل یا کلمه + عدد استفاده می‌کنیم
                // این کار از نتایج غیرمرتبط (مثل S در Samsung یا SM) جلوگیری می‌کند
                $search_conditions[] = "(p.name REGEXP ?)";
                
                // (بهبود) استفاده از الگوی سازگارتر برای تمام نسخه‌های MySQL/MariaDB
                // این الگو S یا S25 را پیدا می‌کند اما Samsung یا SM را نادیده می‌گیرد
                $regexp_term = '(^|[^a-zA-Z0-9])' . $term . '([0-9]+)?($|[^a-zA-Z0-9])';
                
                $params[] = $regexp_term;
                $types .= "s";
            } else {
                $term_like = "%" . $term . "%";
                // برای هر کلمه، یک گروه از OR ها ایجاد می‌کنیم که در یکی از فیلدها وجود داشته باشد
                $search_conditions[] = "(p.name LIKE ? OR pb.name LIKE ? OR pb.name_en LIKE ?)";
                $params[] = $term_like; $params[] = $term_like; $params[] = $term_like;
                $types .= "sss";
            }
        }

        if (!empty($search_conditions)) {
            // تمام گروه‌های OR را با AND به هم متصل می‌کنیم تا همه کلمات جستجو شده باید وجود داشته باشند
            $where[] = "(" . implode(' AND ', $search_conditions) . ")";
        }
    }

    // فیلتر امتیاز
    if (!empty($filters['min_rating'])) {
        $where[] = "p.rating >= ?";
        $params[] = $filters['min_rating'];
        $types .= "d";
    }

    // فیلتر قیمت و موجودی (با استفاده از EXISTS برای عملکرد بهتر)
    if ($filters['min_price'] !== '' || $filters['max_price'] !== '') {
        $min = $filters['min_price'] !== '' ? $filters['min_price'] : 0;
        $max = $filters['max_price'] !== '' ? $filters['max_price'] : 999999999999;
        $where[] = "EXISTS (SELECT 1 FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id AND po.is_active = 1 AND po.price BETWEEN ? AND ?)";
        $params[] = $min;
        $params[] = $max;
        $types .= "ii";
    }

    if ($filters['in_stock_only']) {
        $where[] = "EXISTS (SELECT 1 FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id AND po.is_active = 1 AND po.stock > 0)";
    }

    $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " GROUP BY p.id"; // جلوگیری از تکرار محصولات به دلیل Join با variants

    // مرتب‌سازی
    switch ($sort_order) {
        case 'price_asc': $sql .= " ORDER BY min_price ASC"; break;
        case 'price_desc': $sql .= " ORDER BY min_price DESC"; break;
        case 'newest': $sql .= " ORDER BY p.created_at DESC"; break;
        case 'discount_desc': $sql .= " ORDER BY (max_price - min_price) DESC"; break;
        default: $sql .= " ORDER BY p.id DESC";
    }

    return ['sql' => $sql, 'params' => $params, 'types' => $types];
}
?>