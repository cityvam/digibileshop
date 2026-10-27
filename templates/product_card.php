<?php
// templates/product_card.php

// واکشی اطلاعات تکمیلی برای کارت محصول جدید
$product_id = $product['id'];

// واکشی رنگ‌ها
$stmt_colors = $conn->prepare("SELECT DISTINCT pv.color_code FROM product_variants pv JOIN product_offers po ON pv.id = po.variant_id WHERE pv.product_id = ? AND pv.color_code IS NOT NULL AND po.stock > 0 AND po.is_active = 1 LIMIT 5");
$stmt_colors->bind_param("i", $product_id);
$stmt_colors->execute();
$colors_result = $stmt_colors->get_result();
$available_colors = $colors_result->fetch_all(MYSQLI_ASSOC);

// واکشی امتیاز
$stmt_rating = $conn->prepare("SELECT AVG(rating) as avg_rating FROM product_reviews WHERE product_id = ? AND status = 'approved'");
$stmt_rating->bind_param("i", $product_id);
$stmt_rating->execute();
$rating_result = $stmt_rating->get_result()->fetch_assoc();
$avg_rating = !empty($rating_result['avg_rating']) ? round($rating_result['avg_rating'], 1) : null;

// واکشی قیمت قدیمی برای محاسبه تخفیف
$stmt_price = $conn->prepare("SELECT price, old_price FROM product_offers WHERE variant_id IN (SELECT id FROM product_variants WHERE product_id = ?) AND is_active = 1 AND stock > 0 ORDER BY price ASC LIMIT 1");
$stmt_price->bind_param("i", $product_id);
$stmt_price->execute();
$price_details = $stmt_price->get_result()->fetch_assoc();
$old_price = $price_details['old_price'] ?? null;
$discount_percentage = 0;
if (!empty($old_price) && $old_price > $product['min_price']) {
    $discount_percentage = round((($old_price - $product['min_price']) / $old_price) * 100);
}

// (مهم) واکشی مشخصات کلیدی برای نمایش آیکون‌ها
// نکته: این کوئری برای هر محصول اجرا می‌شود که ممکن است روی عملکرد تاثیر بگذارد.
// برای بهینه‌سازی در آینده، می‌توان این اطلاعات را در کوئری اصلی صفحه محصولات واکشی کرد.
$stmt_specs = $conn->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id = ? AND spec_key IN ('ظرفیت باتری', 'رزولوشن عکس', 'اندازه', 'حافظه داخلی') LIMIT 4");
$stmt_specs->bind_param("i", $product_id);
$stmt_specs->execute();
$specs_result = $stmt_specs->get_result();
$key_specs = [];
while($row = $specs_result->fetch_assoc()) {
    $key_specs[$row['spec_key']] = $row['spec_value'];
}
?>
<div class="col">
    <div class="card product-card h-100">
        <?php 
        // (جدید) شبیه‌سازی زمان پایان تخفیف ویژه به صورت داینامیک
        // در آینده این مقدار از دیتابیس خوانده خواهد شد
        $offer_end_time = null;
        // برای مثال، فقط برای محصولاتی که تخفیف دارند و ID آنها زوج است، تایمر نمایش می‌دهیم
        if ($discount_percentage > 5 && $product['id'] % 2 == 0) {
            // یک زمان تصادفی در 24 ساعت آینده ایجاد می‌کنیم
            $offer_end_time = date('Y-m-d H:i:s', time() + rand(3600, 86400));
        }
        if ($discount_percentage > 5 && !empty($offer_end_time) && strtotime($offer_end_time) > time()): 
        ?>
        <div class="product-card-banner">
            <div class="countdown" data-end-time="<?php echo $offer_end_time; ?>"></div>
            <span class="ms-2">تکنوآف</span>
        </div>
        <?php endif; ?>

        <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none d-flex flex-column h-100">
            <div class="product-card-img-container">
                <img src="<?php echo htmlspecialchars($product['image'] ? 'upload/products/' . $product['image'] : 'https://via.placeholder.com/300'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php if (!empty($available_colors)): ?>
                <div class="product-card-colors">
                    <?php foreach($available_colors as $color): ?>
                        <span class="product-card-color-swatch" style="background-color: <?php echo htmlspecialchars($color['color_code']); ?>"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="product-card-features">
                <div class="feature-item" title="ظرفیت باتری"><i class="fa fa-battery-full"></i><span><?php echo to_persian_num(preg_replace('/[^0-9]/', '', $key_specs['ظرفیت باتری'] ?? '')); ?></span></div>
                <div class="feature-item" title="رزولوشن دوربین"><i class="fa fa-camera"></i><span><?php echo to_persian_num(preg_replace('/[^0-9]/', '', $key_specs['رزولوشن عکس'] ?? '')); ?></span></div>
                <div class="feature-item" title="اندازه صفحه"><i class="fa fa-mobile-alt"></i><span><?php echo to_persian_num($key_specs['اندازه'] ?? ''); ?></span></div>
                <div class="feature-item" title="حافظه داخلی"><i class="fa fa-memory"></i><span><?php echo to_persian_num(preg_replace('/[^0-9]/', '', $key_specs['حافظه داخلی'] ?? '')); ?></span></div>
            </div>

            <div class="card-body d-flex flex-column">
                <h2 class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></h2>
                <div class="mt-auto">
                    <?php if ($avg_rating): ?><div class="product-card-rating"><i class="fa fa-star"></i><span><?php echo to_persian_num($avg_rating); ?></span></div><?php endif; ?>
                    <div class="product-card-price">
                        <?php if ($discount_percentage > 0): ?><span class="discount-badge"><?php echo to_persian_num($discount_percentage); ?>%</span><?php endif; ?>
                        <span class="price-amount"><?php echo to_persian_num($product['min_price']); ?></span>
                        <span class="price-currency">تومان</span>
                        <?php if ($old_price): ?><del class="old-price"><?php echo to_persian_num($old_price); ?></del><?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>