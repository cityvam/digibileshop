<?php
include 'inc/header.php';

/**
 * تابع برای ترجمه نام رنگ فارسی به انگلیسی (برای CSS)
 * @param string $persian_color نام رنگ به فارسی
 * @return string|null نام رنگ به انگلیسی یا null اگر یافت نشد
 */
function translate_color_to_english($persian_color) {
    // لیست ترجمه رنگ‌ها - می‌توانید این لیست را کامل‌تر کنید
    $color_map = [
        'سبز' => '#22a148',
        'قرمز' => '#e03131',
        'آبی' => '#3479bf',
        'مشکی' => '#1a1a1a',
        'سفید' => '#ffffff',
        'زرد' => '#ffe554ff',
        'نارنجی' => '#ffa600',
        'بنفش' => '#b030b0',
        'صورتی' => '#f76ccdff',
        'نقره‌ای' => '#cfcfcf',
        'خاکستری' => 'gray',
        'طلایی' => '#d4a54c',
        'کرم' => '#F5F5DC', // Beige/Cream
        'بژ' => '#f7c770ff',
        'بنفش تیتانیوم' => '#a98ead',
        'مشکی تیتانیوم' => '#383838',
        'فیروزه ای' => '#00bbbb',
        'طوسی' => '#c4c7ce',
        'سرمه‌ای' => '#001c9bff',
        'زیتونی' => '#84831c',
        'خاکستری روشن' => '#d3d3d3',
    ];

    return $color_map[trim($persian_color)] ?? null;
}

// 1. گرفتن و اعتبارسنجی ID محصول از URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo "<div class='alert alert-danger'>شناسه محصول نامعتبر است.</div>";
    include 'inc/footer.php';
    exit;
}
$product_id = (int)$_GET['id'];

// 2. واکشی اطلاعات اصلی محصول به همراه نام برند و دسته بندی
$stmt = $conn->prepare("
    SELECT p.*, b.name as brand_name, c.name as category_name
    FROM products p
    LEFT JOIN product_brands b ON p.brand_id = b.id
    LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='alert alert-warning'>محصول مورد نظر یافت نشد.</div>";
    include 'inc/footer.php';
    exit;
}
$product = $result->fetch_assoc();

// (جدید) افزودن محصول به لیست آخرین بازدیدها در سشن
if (isset($_SESSION['customer_id'])) { // فقط برای کاربران وارد شده
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    // حذف محصول اگر از قبل در لیست وجود دارد تا به ابتدای لیست منتقل شود
    if (($key = array_search($product_id, $_SESSION['recently_viewed'])) !== false) {
        unset($_SESSION['recently_viewed'][$key]);
    }
    // افزودن محصول به ابتدای آرایه
    array_unshift($_SESSION['recently_viewed'], $product_id);
    // نگه داشتن تنها ۱۰ محصول آخر
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
}

// 3. واکشی تمام تصاویر محصول
$stmt_images = $conn->prepare("SELECT image_filename FROM product_images WHERE product_id = ?");
$stmt_images->bind_param("i", $product_id);
$stmt_images->execute();
$images_result = $stmt_images->get_result();
$images = [];
// تصویر اصلی محصول را به عنوان اولین تصویر اضافه می‌کنیم
if (!empty($product['image'])) {
    $images[] = $product['image'];
}
while ($row = $images_result->fetch_assoc()) {
    $images[] = $row['image_filename'];
}

// 4. واکشی متغیرها (variants) و پیشنهادها (offers)
$stmt_variants = $conn->prepare("
    SELECT
        pv.id as variant_id,
        pv.variant_description,
        pv.color_code,
        po.id as offer_id, 
        po.price,
        po.old_price,
        po.stock,
        g.name as guarantee_name,
        g.duration_months as guarantee_duration,
        s.id as seller_id,
        s.shop_name as seller_name
    FROM product_variants pv
    JOIN product_offers po ON pv.id = po.variant_id
    JOIN sellers s ON po.seller_id = s.id
    LEFT JOIN guarantees g ON po.guarantee_id = g.id
    WHERE pv.product_id = ? AND po.is_active = 1 AND po.stock > 0
    ORDER BY po.price ASC
");
$stmt_variants->bind_param("i", $product_id);
$stmt_variants->execute();
$variants_result = $stmt_variants->get_result();

$variants_data = [];
while ($row = $variants_result->fetch_assoc()) {
    $variants_data[$row['variant_id']]['description'] = $row['variant_description'];
    $variants_data[$row['variant_id']]['color_code'] = $row['color_code'];
    $variants_data[$row['variant_id']]['offers'][] = [
        'offer_id' => $row['offer_id'],
        'price' => $row['price'],
        'stock' => $row['stock'],
        'seller_id' => $row['seller_id'],
        'seller_name' => $row['seller_name'],
        'old_price' => $row['old_price'],
        'guarantee_name' => $row['guarantee_name'],
        'guarantee_duration' => $row['guarantee_duration']
    ];
}

// 5. (تغییر) خواندن و پردازش مشخصات فنی از فیلد JSON در جدول products
$specs = [];
if (!empty($product['specifications'])) {
    $decoded_specs = json_decode($product['specifications'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // (اصلاح) پردازش ساختار JSON جدید و تبدیل آن به فرمت مسطح برای بخش خلاصه
        // و ساختار گروه‌بندی شده برای بخش کامل مشخصات
        $spec_groups = [];
        foreach ($decoded_specs as $group) {
            if (isset($group['title']) && isset($group['attributes']) && is_array($group['attributes'])) {
                foreach ($group['attributes'] as $attribute) {
                    $spec_value = is_array($attribute['values']) ? implode('، ', $attribute['values']) : $attribute['values'];
                    $flat_spec = ['spec_key' => $attribute['title'], 'spec_value' => $spec_value];
                    $specs[] = $flat_spec; // برای بخش خلاصه مشخصات
                    $spec_groups[$group['title']][] = $flat_spec; // برای بخش کامل مشخصات
                }
            }
        }
    }
}

// 6. واکشی نظرات کاربران و محاسبه میانگین امتیاز
$stmt_reviews = $conn->prepare("
    SELECT r.*, c.first_name, c.last_name 
    FROM product_reviews r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$stmt_reviews->bind_param("i", $product_id);
$stmt_reviews->execute();
$reviews_result = $stmt_reviews->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$total_reviews = count($reviews);
$avg_rating = 0;
if ($total_reviews > 0) {
    $total_rating_sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($total_rating_sum / $total_reviews, 1);
}

// 7. (جدید) بررسی اینکه آیا کاربر لاگین کرده محصول را خریده است یا نه
$user_has_purchased = false;
if (isset($_SESSION['customer_id'])) {
    $stmt_purchase_check = $conn->prepare("
        SELECT COUNT(*) as purchase_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN product_variants pv ON oi.variant_id = pv.id
        WHERE o.customer_id = ? AND pv.product_id = ?
    ");
    $stmt_purchase_check->bind_param("ii", $_SESSION['customer_id'], $product_id);
    $stmt_purchase_check->execute();
    $purchase_result = $stmt_purchase_check->get_result()->fetch_assoc();
    if ($purchase_result['purchase_count'] > 0) {
        $user_has_purchased = true;
    }
}

// (جدید) بررسی اینکه آیا محصول در لیست علاقه‌مندی‌های کاربر هست یا نه
$is_in_wishlist = false;
if (isset($_SESSION['customer_id'])) {
    $stmt_wishlist = $conn->prepare("SELECT product_id FROM customer_wishlist WHERE customer_id = ? AND product_id = ?");
    $stmt_wishlist->bind_param("ii", $_SESSION['customer_id'], $product_id);
    $stmt_wishlist->execute();
    $wishlist_result = $stmt_wishlist->get_result();
    if ($wishlist_result->num_rows > 0) {
        $is_in_wishlist = true;
    }
}

// (جدید) بررسی اینکه آیا محصول در لیست مقایسه هست یا نه
$is_in_compare_list = false;
if (isset($_SESSION['compare_list']) && in_array($product_id, $_SESSION['compare_list'])) {
    $is_in_compare_list = true;
}


// 8. (جدید) واکشی محصولات مشابه (از همان دسته بندی)
$related_products = [];
if (isset($product['category_id'])) {
    $stmt_related = $conn->prepare("
        SELECT
            p.id, p.name, p.image, MIN(po.price) as min_price
        FROM products AS p
        JOIN product_variants AS pv ON p.id = pv.product_id
        JOIN product_offers AS po ON pv.id = po.variant_id
        WHERE p.category_id = ? AND p.id != ? AND po.is_active = 1 AND po.stock > 0
        GROUP BY p.id, p.name, p.image
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt_related->bind_param("ii", $product['category_id'], $product_id);
    $stmt_related->execute();
    $related_result = $stmt_related->get_result();
    $related_products = $related_result->fetch_all(MYSQLI_ASSOC);
}

// 9. (جدید) واکشی پرسش و پاسخ‌ها
$qna_stmt = $conn->prepare("
    SELECT qna.*, c.first_name, c.last_name 
    FROM product_qna qna
    JOIN customers c ON qna.customer_id = c.id
    WHERE qna.product_id = ? AND qna.status = 'approved'
    ORDER BY qna.parent_id ASC, qna.created_at ASC
");
$qna_stmt->bind_param("i", $product_id);
$qna_stmt->execute();
$qna_result = $qna_stmt->get_result();
$qna_items = [];
while ($row = $qna_result->fetch_assoc()) {
    if ($row['parent_id'] === null) {
        // این یک سوال است
        $qna_items[$row['id']]['question'] = $row;
    } else {
        // این یک پاسخ است
        $qna_items[$row['parent_id']]['answers'][] = $row;
    }
}

// 10. (جدید) واکشی بیمه‌های فعال اختصاص یافته به این محصول
$stmt_insurances = $conn->prepare("
    SELECT i.id, i.name, i.description, i.price_percentage, i.duration_months 
    FROM insurances i
    JOIN product_insurances pi ON i.id = pi.insurance_id
    WHERE pi.product_id = ? AND i.status = 'active'
");
$stmt_insurances->bind_param("i", $product_id);
$stmt_insurances->execute();
$insurances = $stmt_insurances->get_result()->fetch_all(MYSQLI_ASSOC);

// 11. (جدید) واکشی گارانتی‌های محصول
$stmt_guarantees = $conn->prepare("
    SELECT g.id, g.name, g.duration_months
    FROM guarantees g
    JOIN product_guarantees pg ON g.id = pg.guarantee_id
    WHERE pg.product_id = ?
");
$stmt_guarantees->bind_param("i", $product_id);
$stmt_guarantees->execute();
$product_guarantees = $stmt_guarantees->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<style>
    /* (جدید) محدود کردن عرض آیتم فعال بردکرامب در موبایل */
    @media (max-width: 767.98px) {
        .breadcrumb .breadcrumb-item.active {
            max-width: 150px; /* عرض را می‌توانید برای طراحی خود تنظیم کنید */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle; /* برای هم‌ترازی عمودی با سایر آیتم‌ها */
        }
    }
</style>

<main class="container-xl mt-4">
    <div class="bg-white p-3 p-md-4 rounded-3 shadow-sm">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>index.php">فروشگاه</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>products.php?category_id=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <div class="row g-4"> 
            <div class="col-lg-8" style="border: 1px solid #0000002d;border-radius: 8px; padding: 20px 25px 10px 10px;">
                <div class="row">
                    <div class="col-lg-12 order-2 order-lg-1">
                        <h1 class="h4 fw-bold" style="font-weight: 500!important;font-size: 13px; line-height: 22px;"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <?php if (!empty($product['name_en'])): ?>
                            <p class="text-muted small" style="color: #9f9f9f !important; font-size: 12px;"><?php echo htmlspecialchars($product['name_en']); ?></p>
                        <?php endif; ?>
                    </div>
                    <!-- ستون وسط: اطلاعات و انتخاب متغیر -->
                    <div class="col-lg-7 order-3 order-lg-2">

                        <!-- انتخاب متغیر (مثلا رنگ) -->
                        <?php if (!empty($variants_data)): ?>
                            <div class="mb-4" style="border: 1px solid #0000002d;border-radius: 8px;padding: 15px 26px 10px 34px;">
                                <label class="form-label fw-bold">رنگ: <span id="selected-variant-name" class="fw-normal"></span></label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($variants_data as $variant_id => $variant): ?>
                                        <input type="radio" class="btn-check" name="variant_option" id="variant-<?php echo $variant_id; ?>" value="<?php echo $variant_id; ?>" autocomplete="off" <?php echo array_key_first($variants_data) === $variant_id ? 'checked' : ''; ?> data-variant-name="<?php echo htmlspecialchars($variant['description']); ?>">
                                        <label class="btn btn-outline-secondary variant-btn d-flex align-items-center" for="variant-<?php echo $variant_id; ?>">
                                            <?php
                                            // (تغییر) ترجمه نام رنگ فارسی به انگلیسی برای استفاده در CSS
                                            $css_color = translate_color_to_english($variant['description']);
                                            // اگر ترجمه موجود بود و یا کد رنگ در دیتابیس وجود داشت، مربع را نمایش بده
                                            $final_color = $css_color ?? $variant['color_code'];
                                            ?>
                                            <?php if (!empty($final_color)): ?>
                                                <span class="variant-color" style="background-color: <?php echo htmlspecialchars($final_color); ?>"></span>
                                            <?php endif; ?>
                                            <span class="variant-description"><?php echo htmlspecialchars($variant['description']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- ویژگی‌های اصلی -->
                        <?php if (!empty($specs)): ?>
                            <style>
                                .spec-list-summary li {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    padding: 0.75rem 0.25rem;
                                    direction: rtl; /* راست چین کردن */
                                    font-size: 14px; /* اندازه فونت 14px */
                                    border-bottom: 1px dotted #e0e0e0;
                                }
                                .spec-list-summary li:last-child {
                                    border-bottom: none;
                                }
                                .spec-list-summary .spec-key {
                                    color: #6c757d; /* text-muted */
                                }
                            </style>
                            <div class="mt-4" style="border: 1px solid #0000002d;border-radius: 8px;padding: 15px 26px 10px 34px;">
                                <ul class="list-unstyled spec-list-summary">
                                    <?php foreach (array_slice($specs, 0, 6) as $spec): ?>
                                        <li>
                                            <span class="spec-value fw-bold"><?php echo is_array($spec['spec_value']) ? '-' : htmlspecialchars($spec['spec_value']); ?></span>
                                            <span class="spec-key"><?php echo htmlspecialchars($spec['spec_key']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (count($specs) > 6): ?>
                                    <a href="#full-specs-section" class="btn btn-sm btn-outline-secondary mt-2">مشاهده همه مشخصات</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- ستون سمت راست: گالری تصاویر -->
                    <div class="col-lg-5 order-1 order-lg-3">
                        <div class="d-flex justify-content-center align-items-center gap-3 mb-3"> 
                            <button id="wishlist-btn" class="btn btn-sm btn-outline-secondary product-action-btn action-icon <?php echo $is_in_wishlist ? 'active text-danger' : ''; ?>" 
                                    data-product-id="<?php echo $product_id; ?>" 
                                    title="<?php echo $is_in_wishlist ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'; ?>">
                                <i class="fa<?php echo $is_in_wishlist ? 's' : 'r'; ?> fa-heart"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary product-action-btn action-icon compare-btn <?php echo $is_in_compare_list ? 'active text-primary' : ''; ?>" 
                                    data-product-id="<?php echo $product_id; ?>"
                                    title="<?php echo $is_in_compare_list ? 'حذف از لیست مقایسه' : 'افزودن به مقایسه'; ?>">
                                <i class="fa fa-sliders"></i>
                            </button>
                            <a href="#" class="btn btn-sm btn-outline-secondary product-action-btn action-icon" title="اطلاع‌رسانی شگفت‌انگیز"><i class="far fa-bell"></i></a>
                            <a href="#" class="btn btn-sm btn-outline-secondary product-action-btn action-icon" title="اشتراک‌گذاری"><i class="fa fa-share-alt"></i></a>
                        </div>

                        <div class="position-sticky" style="top: 20px;">
                            <div id="product-carousel" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner border rounded-3">
                                    <?php foreach ($images as $index => $img): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo htmlspecialchars($img ? IMAGE_BASE_URL . 'products/' . $img : 'https://via.placeholder.com/400'); ?>" class="d-block w-100" alt="تصویر محصول">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#product-carousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                                <button class="carousel-control-next" type="button" data-bs-target="#product-carousel" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                            </div>
                            <!-- Thumbnails -->
                            <div class="d-flex justify-content-center mt-2">
                                <?php
                                $total_images = count($images);
                                $thumbnail_limit = 5;
                                $display_thumbnails = ($total_images > $thumbnail_limit) ? array_slice($images, 0, $thumbnail_limit - 1) : $images;

                                foreach ($display_thumbnails as $index => $img):
                                ?>
                                    <button type="button" data-bs-target="#product-carousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?> border-0 rounded-2 p-0 mx-1 mb-1" style="width: 60px; height: 60px;">
                                        <img src="<?php echo htmlspecialchars($img ? IMAGE_BASE_URL . 'products/' . $img : 'https://via.placeholder.com/60'); ?>" class="d-block w-100 h-100 rounded-2" style="object-fit: cover;" alt="thumbnail">
                                    </button>
                                <?php
                                endforeach;

                                if ($total_images > $thumbnail_limit):
                                ?>
                                    <button type="button" class="border-0 rounded-2 p-0 mx-1 mb-1" style="width: 60px; height: 60px;" data-bs-toggle="modal" data-bs-target="#galleryModal">
                                        <img src="<?php echo IMAGE_BASE_URL; ?>image.jpg" class="d-block w-100 h-100 rounded-2" style="object-fit: cover;" alt="نمایش همه تصاویر">
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ستون سمت چپ: باکس خرید -->
            <div class="col-lg-4">
                <div class="card purchase-box shadow-sm">
                    <div class="card-body" id="purchase-card-body">
                        <!-- این بخش با جاوااسکریپت بر اساس متغیر انتخابی تغییر می‌کند -->
                        <!-- محتوای فروشندگان و قیمت به صورت داینامیک اینجا قرار می‌گیرد -->
                    </div>
                    <!-- (جدید) بخش بیمه -->
                    <?php if (!empty($insurances)): ?>
                    <div class="card-footer bg-light">
                        <label class="form-label fw-bold small">خدمات بیمه (اختیاری)</label>
                        <?php foreach ($insurances as $insurance): ?>
                            <div class="form-check small">
                                <input class="form-check-input insurance-checkbox" type="checkbox" name="insurance_id[]" value="<?php echo $insurance['id']; ?>" id="insurance-<?php echo $insurance['id']; ?>" data-percentage="<?php echo $insurance['price_percentage']; ?>">
                                <label class="form-check-label" for="insurance-<?php echo $insurance['id']; ?>">
                                    <?php echo htmlspecialchars($insurance['name']); ?>
                                    <span class="text-muted">(<?php echo to_persian_num($insurance['duration_months']); ?> ماهه)</span>
                                </label>
                                <!-- (تغییر) تبدیل تولتیپ به مودال -->
                                <a href="#" class="ms-1 insurance-info-btn" 
                                   data-bs-toggle="modal" 
                                   data-bs-target="#insuranceDetailsModal" 
                                   data-name="<?php echo htmlspecialchars($insurance['name']); ?>" 
                                   data-description="<?php echo htmlspecialchars($insurance['description']); ?>"><i class="fa fa-info-circle text-muted"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- (جدید) مودال گالری کامل تصاویر -->
    <div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="height: 90vh;">
                <div class="modal-header justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">عکس</button>
                    <div>
                        <h5 class="modal-title" id="galleryModalLabel">تصاویر رسمی</h5>
                        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($product['name']); ?></p>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row h-100">
                        <div class="col-md-8 h-100 d-flex flex-column align-items-center justify-content-center order-md-2">
                            <div id="modal-product-carousel" class="carousel slide w-100" data-bs-ride="carousel">
                                <div class="carousel-inner border rounded-3">
                                    <?php foreach ($images as $index => $img): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo htmlspecialchars($img ? IMAGE_BASE_URL . 'products/' . $img : 'https://via.placeholder.com/800'); ?>" class="d-block w-100" style="object-fit: contain; max-height: 75vh;" alt="تصویر محصول">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#modal-product-carousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#modal-product-carousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                            <p class="mt-3 text-center small"><?php echo htmlspecialchars($product['name']); ?></p>
                        </div>
                        <div class="col-md-4 order-md-1">
                            <div class="d-flex flex-row flex-wrap align-content-start gap-2 modal-thumbnails" style="max-height: 75vh; overflow-y: auto;">
                                <?php foreach ($images as $index => $img): ?> 
                                    <button type="button" data-bs-target="#modal-product-carousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?> border rounded-2 p-0" style="width: 80px; height: 80px;">
                                        <img src="<?php echo htmlspecialchars($img ? IMAGE_BASE_URL . 'products/' . $img : 'https://via.placeholder.com/80'); ?>" class="d-block w-100 h-100 rounded-2" style="object-fit: cover;" alt="thumbnail">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- (جدید) بخش سایر فروشندگان -->
    <div id="other-sellers-section" class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4" style="display: none;">
        <h2 class="h5 border-bottom pb-3 mb-0">لیست فروشندگان این کالا</h2>
        <div id="other-sellers-list">
            <!-- لیست سایر فروشندگان با جاوااسکریپت اینجا قرار می‌گیرد -->
        </div>
    </div>

    <!-- (جدید) بخش توضیحات محصول -->
    <?php 
    $description_text = $product['description'] ?? '';
    if (!empty($description_text)): 
        $is_long_description = mb_strlen($description_text, 'UTF-8') > 500; // اگر بیش از ۵۰۰ کاراکتر بود، طولانی محسوب می‌شود
    ?>
    <div class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4">
        <h2 class="h5 border-bottom pb-3 mb-3">نقد و بررسی</h2>
        <div class="product-description-wrapper <?php echo $is_long_description ? 'collapsible' : ''; ?>">
            <div class="product-description-content lh-lg">
                <?php echo nl2br(htmlspecialchars($description_text)); ?>
            </div>
            <?php if ($is_long_description): ?>
                <div class="description-overlay"></div>
                <button class="btn btn-sm btn-outline-primary show-more-btn">نمایش بیشتر</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- (جدید) بخش مشخصات فنی کامل -->
    <?php if (!empty($specs)): ?>
    <div id="full-specs-section" class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4">
        <h2 class="h5 border-bottom pb-3 mb-3">مشخصات فنی</h2>
        <?php
        ?>
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="specTabs" role="tablist">
            <?php $first = true; foreach ($spec_groups as $group_name => $group_specs): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php if($first) { echo 'active'; $first = false; } ?>" id="tab-<?php echo md5($group_name); ?>" data-bs-toggle="tab" data-bs-target="#pane-<?php echo md5($group_name); ?>" type="button" role="tab"><?php echo htmlspecialchars($group_name); ?></button>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content" id="specTabsContent">
            <?php $first = true; foreach ($spec_groups as $group_name => $group_specs): ?>
                <div class="tab-pane fade <?php if($first) { echo 'show active'; $first = false; } ?>" id="pane-<?php echo md5($group_name); ?>" role="tabpanel">
                    <ul class="list-unstyled spec-list mt-3">
                        <?php foreach ($group_specs as $spec): ?>
                            <li>
                                <span class="spec-key"><?php echo htmlspecialchars($spec['spec_key']); ?>:</span>
                                <span class="spec-value fw-bold"><?php echo is_array($spec['spec_value']) ? 'N/A' : htmlspecialchars($spec['spec_value']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- (جدید) بخش محصولات مشابه -->
    <?php if (!empty($related_products)): ?>
    <div class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4">
        <h2 class="h5 border-bottom pb-3 mb-3">محصولات مشابه</h2>
        <div class="related-products-container">
            <?php foreach ($related_products as $related_product): ?>
                <div class="related-product-item">
                    <div class="card h-100 shadow-sm border">
                        <a href="<?php echo BASE_URL . 'product-' . $related_product['id'] . '/' . slugify($related_product['name']); ?>"><img src="<?php echo htmlspecialchars($related_product['image'] ? IMAGE_BASE_URL . 'products/' . $related_product['image'] : 'https://via.placeholder.com/300'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($related_product['name']); ?>"></a>
                        <div class="card-body p-2">
                            <a href="<?php echo BASE_URL . 'product-' . $related_product['id'] . '/' . slugify($related_product['name']); ?>" class="text-decoration-none text-dark stretched-link">
                                <p class="card-title small text-truncate mb-1"><?php echo htmlspecialchars($related_product['name']); ?></p>
                            </a>
                            <small class="fw-bold d-block text-start"><?php echo to_persian_num($related_product['min_price']); ?> ت</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- (جدید) بخش پرسش و پاسخ -->
    <div id="qna" class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4">
        <h2 class="h4 border-bottom pb-3 mb-4">پرسش و پاسخ</h2>

        <!-- فرم ثبت پرسش جدید -->
        <?php if (isset($_SESSION['customer_id'])): ?>
            <form action="qna_process.php" method="POST" class="mb-4">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <div class="mb-2">
                    <label for="question_text" class="form-label small">پرسش خود را در مورد این کالا مطرح کنید</label>
                    <textarea name="question_text" id="question_text" rows="3" class="form-control" placeholder="متن پرسش شما..." required></textarea>
                </div>
                <button type="submit" name="submit_question" class="btn btn-sm btn-primary" style="background-color: #0754ed; border-color: #0754ed;">ارسال پرسش</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info small">برای ثبت پرسش، لطفاً ابتدا <a href="login.php" class="alert-link">وارد شوید</a>.</div>
        <?php endif; ?>

        <!-- لیست پرسش و پاسخ‌ها -->
        <?php if (!empty($qna_items)): ?>
            <?php foreach ($qna_items as $qna_thread): ?>
                <div class="qna-item border-bottom pb-3 mb-3">
                    <!-- سوال -->
                    <div class="d-flex">
                        <span class="qna-icon question-icon"><i class="fa fa-question"></i></span>
                        <div>
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($qna_thread['question']['qna_text'])); ?></p>
                            <small class="text-muted">پرسش توسط <?php echo htmlspecialchars($qna_thread['question']['first_name']); ?></small>
                        </div>
                    </div>
                    <!-- پاسخ‌ها -->
                    <?php if (isset($qna_thread['answers'])): ?>
                        <?php foreach ($qna_thread['answers'] as $answer): ?>
                            <div class="d-flex mt-3 ms-5">
                                <span class="qna-icon answer-icon"><i class="fa fa-store"></i></span>
                                <div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($answer['qna_text'])); ?></p>
                                    <small class="text-muted">پاسخ توسط فروشنده</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted small">هنوز پرسشی برای این کالا ثبت نشده است. شما اولین نفر باشید!</p>
        <?php endif; ?>
    </div>

    <!-- بخش نظرات کاربران -->
    <div id="reviews" class="bg-white p-3 p-md-4 rounded-3 shadow-sm mt-4">
        <h2 class="h4 border-bottom pb-3 mb-4">نظرات کاربران</h2>

        <?php
        // نمایش پیام‌های موفقیت یا خطا بعد از ثبت نظر
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>

        <!-- فرم ثبت نظر (فقط برای کاربران لاگین کرده) -->
        <?php if (isset($_SESSION['customer_id'])): ?>
            <?php if ($user_has_purchased): ?>
                <div class="card mb-4">
                    <div class="card-header">ثبت نظر جدید</div>
                    <div class="card-body">
                        <form action="review_process.php" method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <div class="mb-3">
                                <label class="form-label">امتیاز شما:</label>
                                <div class="rating-stars">
                                    <input type="radio" name="rating" id="rating-5" value="5" required><label for="rating-5"><i class="fa fa-star"></i></label>
                                    <input type="radio" name="rating" id="rating-4" value="4"><label for="rating-4"><i class="fa fa-star"></i></label>
                                    <input type="radio" name="rating" id="rating-3" value="3"><label for="rating-3"><i class="fa fa-star"></i></label>
                                    <input type="radio" name="rating" id="rating-2" value="2"><label for="rating-2"><i class="fa fa-star"></i></label>
                                    <input type="radio" name="rating" id="rating-1" value="1"><label for="rating-1"><i class="fa fa-star"></i></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="review-title" class="form-label">عنوان نظر:</label>
                                <input type="text" name="title" id="review-title" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="review-comment" class="form-label">متن نظر:</label>
                                <textarea name="comment" id="review-comment" rows="4" class="form-control" required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary" style="background-color: #0754ed; border-color: #0754ed;">ارسال نظر</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">فقط کاربرانی که این محصول را خریداری کرده‌اند می‌توانند نظر ثبت کنند.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">برای ثبت نظر، لطفاً ابتدا <a href="login.php" class="alert-link">وارد شوید</a>.</div>
        <?php endif; ?>

        <!-- لیست نظرات ثبت شده -->
        <?php if ($total_reviews > 0): ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?php echo htmlspecialchars($review['title']); ?></h6>
                        <div class="text-warning">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?php echo $i <= $review['rating'] ? 's' : 'r'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="small text-muted mt-1">توسط <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?> در تاریخ <?php echo to_jalali_date($review['created_at']); ?></p>
                    <p class="text-break"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>هنوز نظری برای این محصول ثبت نشده است. شما اولین نفر باشید!</p>
        <?php endif; ?>
    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // (جدید) منطق دکمه افزودن به علاقه‌مندی‌ها
    const wishlistBtn = document.getElementById('wishlist-btn');
    if (wishlistBtn) {
        wishlistBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const icon = this.querySelector('i');

            // ارسال درخواست به سرور
            fetch('<?php echo BASE_URL; ?>profile/wishlist_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.action === 'added') {
                        // محصول اضافه شد
                        this.classList.add('active', 'text-danger');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        this.title = 'حذف از علاقه‌مندی‌ها';
                    } else if (data.action === 'removed') {
                        // محصول حذف شد
                        this.classList.remove('active', 'text-danger');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        this.title = 'افزودن به علاقه‌مندی‌ها';
                    }
                } else if (data.message === 'not_logged_in') {
                    // اگر کاربر لاگین نکرده بود، او را به صفحه ورود هدایت کن
                    window.location.href = '<?php echo BASE_URL; ?>login.php';
                } else {
                    // نمایش خطا در صورت بروز مشکل
                    console.error('Wishlist Error:', data.message);
                    alert('خطایی در پردازش درخواست شما رخ داد.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('خطای ارتباط با سرور.');
            });
        });
    }

    // (جدید) منطق دکمه افزودن به مقایسه
    document.querySelectorAll('.compare-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const icon = this.querySelector('i');

            fetch('<?php echo BASE_URL; ?>compare_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.action === 'added') {
                        this.classList.add('active', 'text-primary');
                    } else {
                        this.classList.remove('active', 'text-primary');
                    }
                    // به‌روزرسانی نوار مقایسه
                    if (typeof window.updateCompareBar === 'function') {
                        window.updateCompareBar(data.compare_list);
                    }
                } else {
                    alert(data.message); // نمایش پیام خطا (مثلا: لیست مقایسه پر است)
                }
            });
        });
    });

    const variantsData = <?php echo json_encode($variants_data, JSON_UNESCAPED_UNICODE); ?>;
    const purchaseCardBody = document.getElementById('purchase-card-body');
    const selectedVariantNameEl = document.getElementById('selected-variant-name');

    const insurancesData = <?php echo json_encode($insurances, JSON_UNESCAPED_UNICODE); ?>;
    const productGuarantees = <?php echo json_encode($product_guarantees, JSON_UNESCAPED_UNICODE); ?>;
    // (جدید) بخش‌های جدید برای سایر فروشندگان
    const otherSellersSection = document.getElementById('other-sellers-section');
    const otherSellersList = document.getElementById('other-sellers-list');

    function renderProductOffers(selectedVariantId) {
        let html = '';
        const variant = variantsData[selectedVariantId];

        if (!variant || !variant.offers || variant.offers.length === 0) {
            purchaseCardBody.innerHTML = '<div class="alert alert-warning small">این محصول در حال حاضر موجود نیست.</div>';
            otherSellersSection.style.display = 'none';
            return;
        }

        // بهترین پیشنهاد (ارزان‌ترین) برای بای‌باکس
        const bestOffer = variant.offers[0];

        // رندر کردن بای‌باکس اصلی
        let buyBoxHtml = '';
        let discountHtml = '';
        if (bestOffer.old_price && bestOffer.old_price > bestOffer.price) {
            const discountAmount = bestOffer.old_price - bestOffer.price;
            discountHtml = `<div class="discount-badge"><span>${new Intl.NumberFormat('fa-IR').format(discountAmount)}</span> تومان تخفیف</div>`;
        }

        let guaranteeHtml = '';
        if (productGuarantees && productGuarantees.length > 0) {
            const gNames = productGuarantees.map(g => `<strong>${g.name}</strong>`).join('، ');
            guaranteeHtml = `<div class="seller-info-item">
                <i class="fa fa-shield-alt fa-fw"></i>
                <span>گارانتی: ${gNames}</span>
            </div>`;
        }

        buyBoxHtml += `
            <div class="seller-info-item">
                <i class="fa fa-store fa-fw"></i>
                <span>فروشنده: <strong>${bestOffer.seller_name}</strong></span>
            </div>
            ${guaranteeHtml}
            <div class="seller-info-item">
                <i class="fa fa-check-circle text-success fa-fw"></i>
                <span>موجود در انبار</span>
            </div>
            <hr class="my-3">
            <div class="price-section my-3">
                ${discountHtml}
                <div class="text-start mt-2" id="final-price-container" data-base-price="${bestOffer.price}">
                    ${bestOffer.old_price ? `<del class="text-muted small me-2">${new Intl.NumberFormat('fa-IR').format(bestOffer.old_price)}</del>` : ''}
                    <div class="d-inline-block">
                        <span class="h4 fw-bold text-dark" id="final-price-amount">${new Intl.NumberFormat('fa-IR').format(bestOffer.price)}</span>
                        <small class="h6"> تومان</small>
                    </div>
                </div>
            </div>
        `;

        // (تغییر) افزودن فیلدهای مخفی بیمه به فرم
        buyBoxHtml += `
            <form action="<?php echo BASE_URL; ?>cart_process.php" method="POST" id="add-to-cart-form" class="ajax-add-to-cart">
                <input type="hidden" name="offer_id" id="selected_offer_id" value="${bestOffer.offer_id}"> 
                <input type="number" name="quantity" value="1" min="1" class="form-control mb-2">
                <input type="hidden" name="action" value="add">
                <div class="d-grid">
                    <div id="selected-insurances-container" class="selected-insurances-container">
                        <!-- فیلدهای مخفی برای بیمه‌های انتخابی اینجا قرار می‌گیرند -->
                    </div>
                <button type="submit" class="btn btn-primary btn-lg" name="add_to_cart" ${bestOffer.stock > 0 ? '' : 'disabled'} style="background-color: #0754ed; border-color: #0754ed;">
                        <i class="fa fa-cart-plus"></i> افزودن به سبد خرید
                    </button>
                </div>
            </form>
        `;
        purchaseCardBody.innerHTML = buyBoxHtml;

        // رندر کردن لیست سایر فروشندگان (اگر وجود داشته باشند)
        if (variant.offers.length > 0) {
            let otherSellersHtml = '<div class="table-responsive"><table class="table table-hover align-middle">';
            
            variant.offers.forEach(offer => {
                const isBestOffer = offer.offer_id === bestOffer.offer_id;
                otherSellersHtml += `
                    <tr class="${isBestOffer ? 'table-primary' : ''}">
                        <td class="w-25">
                            <i class="fa fa-store me-2 text-muted"></i>
                            <strong>${offer.seller_name}</strong>
                            ${isBestOffer ? '<span class="badge bg-success ms-2">پیشنهاد ما</span>' : ''}
                        </td>
                        <td class="w-25">
                            <i class="fa fa-shield-alt me-2 text-muted"></i>
                            <span>${offer.guarantee_name || 'گارانتی اصالت'}</span>
                        </td>
                        <td class="w-25 text-center">
                            <div class="d-flex flex-column align-items-center">
                                <span class="h5 fw-bold">${new Intl.NumberFormat('fa-IR').format(offer.price)} تومان</span>
                                ${offer.old_price && offer.old_price > offer.price ? `<del class="text-muted small">${new Intl.NumberFormat('fa-IR').format(offer.old_price)}</del>` : ''}
                            </div>
                        </td>
                        <td class="w-25 text-start">
                            <form action="<?php echo BASE_URL; ?>cart_process.php" method="POST" class="ajax-add-to-cart">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="offer_id" value="${offer.offer_id}">
                                <input type="hidden" name="quantity" value="1">
                                <div class="selected-insurances-container">
                                    <!-- (اصلاح) فیلدهای بیمه برای این پیشنهاد خاص -->
                                </div>
                                <button type="submit" name="add_to_cart" class="btn btn-sm ${isBestOffer ? 'btn-primary' : 'btn-outline-primary'}" ${offer.stock > 0 ? '' : 'disabled'}>
                                    افزودن به سبد
                                </button>
                            </form>
                        </td>
                    </tr>
                `;
            });

            otherSellersHtml += '</table></div>';
            otherSellersList.innerHTML = otherSellersHtml;
            otherSellersSection.style.display = 'block';
        } else {
            otherSellersList.innerHTML = '';
            otherSellersSection.style.display = 'none';
        }

        // بعد از رندر کردن، محاسبه قیمت را به‌روز کن
        updateFinalPrice();
    }

    function updateVariantSelection() {
        const selectedRadio = document.querySelector('input[name="variant_option"]:checked');
        if (selectedRadio) {
            const variantId = selectedRadio.value;
            const variantName = selectedRadio.getAttribute('data-variant-name');
            if (selectedVariantNameEl) {
                selectedVariantNameEl.textContent = variantName;
            }
            renderProductOffers(variantId);
        }
    }

    // (جدید) تابع برای محاسبه و به‌روزرسانی قیمت نهایی با احتساب بیمه
    function updateFinalPrice() {
        const priceContainer = document.getElementById('final-price-container');
        if (!priceContainer) return;

        const basePrice = parseFloat(priceContainer.dataset.basePrice);
        let insuranceCost = 0;
        const selectedInsurances = [];

        document.querySelectorAll('.insurance-checkbox:checked').forEach(checkbox => {
            const percentage = parseFloat(checkbox.dataset.percentage);
            insuranceCost += (basePrice * percentage) / 100;
            selectedInsurances.push(checkbox.value);
        });

        const finalPrice = basePrice + insuranceCost;
        document.getElementById('final-price-amount').textContent = new Intl.NumberFormat('fa-IR').format(finalPrice);

        // به‌روزرسانی فیلدهای مخفی در فرم‌ها
        document.querySelectorAll('.selected-insurances-container').forEach(container => {
            container.innerHTML = '';
            selectedInsurances.forEach(id => {
                container.innerHTML += `<input type="hidden" name="insurance[]" value="${id}">`;
            });
        });
    }

    document.querySelectorAll('input[name="variant_option"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateVariantSelection();
        });
    });

    // (جدید) افزودن event listener به چک‌باکس‌های بیمه
    document.querySelectorAll('.insurance-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateFinalPrice);
    });

    // (جدید) فعال‌سازی تولتیپ‌های بوت‌استرپ برای توضیحات بیمه
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // Initial load
    updateVariantSelection();
});
</script>

<!-- (جدید) اسکریپت برای دکمه نمایش بیشتر/کمتر توضیحات -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const showMoreBtn = document.querySelector('.show-more-btn');
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function() {
            const wrapper = this.closest('.product-description-wrapper');
            wrapper.classList.toggle('expanded');
            if (wrapper.classList.contains('expanded')) {
                this.textContent = 'نمایش کمتر';
            } else {
                this.textContent = 'نمایش بیشتر';
            }
        });
    }
});
</script>

<!-- (اصلاح) مخفی کردن لودر بیمه در مودال و نمایش آن فقط هنگام تغییر چک‌باکس -->
<style>
    #addToCartSuccessModal .insurance-loader {
        display: none;
    }
</style>
<script>
document.addEventListener('change', function(e) {
    if (e.target.matches('#addToCartSuccessModal input[type="checkbox"]')) {
        const loader = e.target.closest('.modal-content').querySelector('.insurance-loader');
        if (loader) {
            loader.style.display = 'inline-block';
            // (جدید) مخفی کردن اسپینر بعد از یک تاخیر کوتاه برای ایجاد حس پردازش
            setTimeout(() => {
                loader.style.display = 'none';
            }, 300); // 300 میلی‌ثانیه
        }
    }
});
</script>

<?php include 'inc/footer.php'; ?>