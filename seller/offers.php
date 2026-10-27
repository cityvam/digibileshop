<?php
require_once 'inc/config.php';
require_once 'inc/seller_auth.php';

$seller_id = $_SESSION['seller_id'];

// --- AJAX Endpoint: به‌روزرسانی پیشنهاد ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_offer') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'درخواست نامعتبر.'];

    $variant_id = $_POST['variant_id'] ?? null;
    $price = $_POST['price'] ?? null;
    $stock = $_POST['stock'] ?? null;

    // اعتبارسنجی ورودی‌ها
    if (!$variant_id || !is_numeric($variant_id)) {
        $response['message'] = 'شناسه محصول نامعتبر است.';
        echo json_encode($response);
        exit;
    }

    // اگر قیمت و موجودی هر دو خالی باشند، پیشنهاد را حذف می‌کنیم
    if (trim($price) === '' && trim($stock) === '') {
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM product_offers WHERE variant_id = ? AND seller_id = ?");
            $stmt_delete->execute([$variant_id, $seller_id]);
            $response = ['success' => true, 'message' => 'پیشنهاد با موفقیت حذف شد.'];
        } catch (Exception $e) {
            $response['message'] = 'خطا در حذف پیشنهاد: ' . $e->getMessage();
        }
    } else {
        // در غیر این صورت، پیشنهاد را ثبت یا ویرایش می‌کنیم
        $price_val = (trim($price) === '') ? null : to_english_nums($price);
        $stock_val = (trim($stock) === '') ? null : to_english_nums($stock);

        if (($price_val !== null && !is_numeric($price_val)) || ($stock_val !== null && !is_numeric($stock_val))) {
            $response['message'] = 'مقادیر قیمت و موجودی باید عددی باشند.';
            echo json_encode($response);
            exit;
        }

        try {
            // این کوئری هوشمندانه عمل می‌کند: اگر رکوردی با این variant_id و seller_id وجود داشته باشد،
            // آن را آپدیت می‌کند، در غیر این صورت یک رکورد جدید می‌سازد.
            $stmt = $pdo->prepare("
                INSERT INTO product_offers (seller_id, variant_id, price, stock)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE price = VALUES(price), stock = VALUES(stock)
            ");
            $stmt->execute([$seller_id, $variant_id, $price_val, $stock_val]);
            $response = ['success' => true, 'message' => 'پیشنهاد با موفقیت ذخیره شد.'];
        } catch (Exception $e) {
            $response['message'] = 'خطا در ذخیره پیشنهاد: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
    exit;
}

// --- AJAX Endpoint: دریافت لیست متغیرهای یک محصول (برای مودال) ---
if (isset($_GET['action']) && $_GET['action'] === 'get_product_variants') {
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT
                v.id as variant_id, v.variant_description, ref_po.price as reference_price, p.image, p.name as product_name,
                po.price, po.stock
            FROM product_variants v
            JOIN products p ON v.product_id = p.id
            LEFT JOIN product_offers po ON v.id = po.variant_id AND po.seller_id = ?
            LEFT JOIN product_offers ref_po ON v.id = ref_po.variant_id AND ref_po.seller_id = 1
            WHERE v.product_id = ?
            ORDER BY v.id ASC
        ");
        $stmt->execute([$seller_id, $product_id]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // اگر متغیری یافت نشد
        if (empty($variants)) {
            echo json_encode(['success' => false, 'message' => 'هیچ تنوعی برای این محصول یافت نشد.']);
            exit;
        }

        // بازگرداندن داده‌ها به صورت JSON برای رندر در فرانت
        echo json_encode(['success' => true, 'variants' => $variants]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
    }
    exit;
}

$page_title = 'مدیریت پیشنهادها';
require_once 'inc/header.php';

// --- منطق صفحه‌بندی و جستجو ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_term = trim($_GET['search'] ?? '');
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// واکشی دسته‌بندی‌ها و برندها برای فیلتر
$all_categories = [];
$all_brands = [];
try {
    $all_categories = $pdo->query("SELECT * FROM product_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // جدول دسته‌بندی وجود ندارد یا خطا دارد
}
try {
    if ($category_id > 0) {
        // واکشی برندهایی که در این دسته‌بندی محصول دارند
        $stmt_brands = $pdo->prepare("
            SELECT DISTINCT b.* 
            FROM product_brands b 
            JOIN products p ON b.id = p.brand_id 
            WHERE p.category_id = ? 
            ORDER BY b.name ASC
        ");
        $stmt_brands->execute([$category_id]);
        $all_brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_brands = $pdo->query("SELECT * FROM product_brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // جدول برند وجود ندارد یا خطا دارد
}

$conditions = [];
$params = [];

if (!empty($search_term)) {
    $conditions[] = "p.name LIKE :search";
    $params[':search'] = '%' . $search_term . '%';
}
if ($category_id > 0) {
    $conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if ($brand_id > 0) {
    $conditions[] = "p.brand_id = :brand_id";
    $params[':brand_id'] = $brand_id;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // شمارش کل محصولات
    $count_sql = "SELECT COUNT(p.id) FROM products p " . $where_clause;
    $total_stmt = $pdo->prepare($count_sql);
    $total_stmt->execute($params);
    $total_products = $total_stmt->fetchColumn();
    $total_pages = ceil($total_products / $per_page);

    // واکشی محصولات
    $sql = "
        SELECT p.id as product_id, p.name as product_name, p.image
        FROM products p
        $where_clause
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => &$value) {
        $stmt->bindParam($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    $total_pages = 0;
    $db_error = $e->getMessage();
}

?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1>مدیریت پیشنهادها</h1>
</div>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger">خطا در پایگاه داده: <?php echo htmlspecialchars($db_error); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form action="offers.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label">جستجو بر اساس نام محصول:</label>
                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="مثلا: آیفون ۱۳ یا آبی">
            </div>
            <div class="col-md-3">
                <label for="category_id" class="form-label">دسته‌بندی:</label>
                <select name="category_id" id="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="">همه دسته‌ها</option>
                    <?php display_categories_options($all_categories, null, '', null, $category_id); ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="brand_id" class="form-label">برند:</label>
                <select name="brand_id" id="brand_id" class="form-select">
                    <option value="">همه برندها</option>
                    <?php foreach ($all_brands as $brand): ?>
                        <option value="<?php echo $brand['id']; ?>" <?php echo ($brand_id == $brand['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($brand['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>جستجو</button>
            </div>
            <?php if (!empty($search_term) || $category_id > 0 || $brand_id > 0): ?>
            <div class="col-md-2"><a href="offers.php" class="btn btn-outline-secondary w-100">حذف فیلتر</a></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">لیست محصولات</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 100px;">تصویر</th>
                        <th>نام محصول</th>
                        <th class="text-center" style="width: 200px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="3" class="text-center text-muted">محصولی یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="text-center">
                                    <?php $image_url = $product['image'] ? STATIC_ASSETS_URL . '/products/' . htmlspecialchars($product['image']) : rtrim(BASE_URL, '/') . '/img/placeholder.png'; ?>
                                    <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" width="60" height="60" style="object-fit: cover;" class="rounded">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm manage-variants-btn" data-product-id="<?php echo $product['product_id']; ?>" data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                        <i class="bi bi-list-ul me-1"></i> مدیریت تنوع‌ها
                                    </button>
                                    <?php $slug = str_replace(' ', '-', $product['product_name']); ?>
                                    <a href="<?php echo MAIN_SITE_URL; ?>/product-<?php echo $product['product_id']; ?>/<?php echo $slug; ?>" target="_blank" class="btn btn-outline-secondary btn-sm ms-1" title="مشاهده در سایت">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <?php echo render_pagination('offers.php', $page, $total_pages, ['search' => $search_term, 'category_id' => $category_id, 'brand_id' => $brand_id]); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal for Variants -->
<div class="modal fade" id="variantsModal" tabindex="-1" aria-labelledby="variantsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variantsModalLabel">مدیریت تنوع‌ها: <span id="modal-product-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">در حال بارگذاری...</p>
                </div>
                <div class="table-responsive" id="modal-variants-table" style="display: none;">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>تنوع (گارانتی / رنگ / ...)</th>
                                <th>قیمت مرجع</th>
                                <th style="width: 30%;">قیمت (تومان)</th>
                                <th style="width: 20%;">موجودی</th>
                                <th style="width: 10%;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="modal-variants-body">
                            <!-- Rows will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const variantsModal = new bootstrap.Modal(document.getElementById('variantsModal'));
    const modalProductName = document.getElementById('modal-product-name');
    const modalBody = document.getElementById('modal-variants-body');
    const modalLoading = document.getElementById('modal-loading');
    const modalTable = document.getElementById('modal-variants-table');

    // باز کردن مودال و بارگذاری داده‌ها
    document.querySelectorAll('.manage-variants-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;

            modalProductName.textContent = productName;
            modalLoading.style.display = 'block';
            modalTable.style.display = 'none';
            modalBody.innerHTML = '';
            
            variantsModal.show();

            fetch(`offers.php?action=get_product_variants&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let rows = '';
                        data.variants.forEach(variant => {
                            const price = variant.price !== null ? variant.price : '';
                            const stock = variant.stock !== null ? variant.stock : '';
                            const referencePrice = variant.reference_price ? parseInt(variant.reference_price).toLocaleString('fa-IR') : '-';
                            
                            rows += `
                                <tr data-variant-id="${variant.variant_id}">
                                    <td>${variant.variant_description}</td>
                                    <td>${referencePrice} تومان</td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm price-input" placeholder="قیمت" value="${price}">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm stock-input" placeholder="موجودی" value="${stock}" min="0">
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-success save-offer-btn" title="ذخیره">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        modalBody.innerHTML = rows;
                        modalLoading.style.display = 'none';
                        modalTable.style.display = 'block';
                    } else {
                        modalBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${data.message}</td></tr>`;
                        modalLoading.style.display = 'none';
                        modalTable.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">خطا در بارگذاری اطلاعات. (جزئیات در کنسول)</td></tr>`;
                    modalLoading.style.display = 'none';
                    modalTable.style.display = 'block';
                });
        });
    });

    // ذخیره تغییرات (Delegation روی بادی مودال)
    modalBody.addEventListener('click', function(e) {
        if (e.target.classList.contains('save-offer-btn') || e.target.closest('.save-offer-btn')) {
            const saveButton = e.target.closest('.save-offer-btn');
            const row = saveButton.closest('tr');
            const variantId = row.dataset.variantId;
            const price = row.querySelector('.price-input').value;
            const stock = row.querySelector('.stock-input').value;

            // نمایش حالت لودینگ
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            const formData = new FormData();
            formData.append('action', 'update_offer');
            formData.append('variant_id', variantId);
            formData.append('price', price);
            formData.append('stock', stock);

            fetch('offers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                } else {
                    showToast(result.message || 'خطایی رخ داد.', 'danger');
                }
            })
            .catch(error => {
                showToast('خطای شبکه. لطفاً اتصال خود را بررسی کنید.', 'danger');
            })
            .finally(() => {
                // بازگرداندن دکمه به حالت اولیه
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="bi bi-check-lg"></i>';
            });
        }
    });
});
</script>
<?php $page_scripts = ob_get_clean(); ?>

<?php require_once 'inc/footer.php'; ?>