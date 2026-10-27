<?php
include 'inc/header.php';

if (!isset($_SESSION['compare_list']) || empty($_SESSION['compare_list'])) {
    echo "<main class='container mt-4'><div class='alert alert-warning'>هیچ محصولی برای مقایسه انتخاب نشده است.</div></main>";
    include 'inc/footer.php';
    exit();
}

$product_ids = $_SESSION['compare_list'];
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$types = str_repeat('i', count($product_ids));

// واکشی اطلاعات اصلی محصولات
$stmt_products = $conn->prepare("SELECT id, name, image, description FROM products WHERE id IN ($placeholders)");
$stmt_products->bind_param($types, ...$product_ids);
$stmt_products->execute();
$products_result = $stmt_products->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[$row['id']] = $row;
}

// واکشی تمام مشخصات فنی برای این محصولات
$stmt_specs = $conn->prepare("SELECT product_id, spec_key, spec_value FROM product_specs WHERE product_id IN ($placeholders)");
$stmt_specs->bind_param($types, ...$product_ids);
$stmt_specs->execute();
$specs_result = $stmt_specs->get_result();

$all_specs = [];
$all_spec_keys = [];
while ($row = $specs_result->fetch_assoc()) {
    $all_specs[$row['product_id']][$row['spec_key']] = $row['spec_value'];
    if (!in_array($row['spec_key'], $all_spec_keys)) {
        $all_spec_keys[] = $row['spec_key'];
    }
}
sort($all_spec_keys);

?>

<main class="container-xl mt-4">
    <h1 class="mb-4">مقایسه محصولات</h1>

    <div class="table-responsive">
        <table class="table table-bordered table-hover compare-table">
            <thead>
                <tr>
                    <th class="spec-col">ویژگی</th>
                    <?php foreach ($product_ids as $pid): ?>
                        <?php if (isset($products[$pid])): $product = $products[$pid]; ?>
                            <th class="product-col" data-product-id="<?php echo $product['id']; ?>">
                                <button class="btn-close remove-from-compare-btn" data-product-id="<?php echo $product['id']; ?>" title="حذف از مقایسه"></button>
                                <a href="product.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($product['image'] ? IMAGE_BASE_URL . 'products/' . $product['image'] : 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid mb-2">
                                    <p class="small fw-bold"><?php echo htmlspecialchars($product['name']); ?></p>
                                </a>
                            </th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // (جدید) بررسی تفاوت در توضیحات کلی
                $description_values = [];
                foreach ($product_ids as $pid) {
                    $description_values[] = $products[$pid]['description'] ?? '---DESC-NOT-SET---';
                }
                $has_desc_difference = count(array_unique($description_values)) > 1;
                ?>
                <tr class="<?php echo $has_desc_difference ? 'highlight-diff' : ''; ?>">
                    <td class="fw-bold">توضیحات کلی</td>
                    <?php foreach ($product_ids as $pid): ?>
                        <td data-product-id="<?php echo $pid; ?>">
                            <p class="small text-muted">
                                <?php echo isset($products[$pid]['description']) && !empty($products[$pid]['description']) ? nl2br(htmlspecialchars($products[$pid]['description'])) : '-'; ?>
                            </p>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- ردیف‌های مشخصات فنی -->
                <?php foreach ($all_spec_keys as $spec_key): ?>
                    <?php
                    // (جدید) منطق بررسی تفاوت‌ها برای هر ویژگی
                    $spec_values = [];
                    foreach ($product_ids as $pid) {
                        $spec_values[] = $all_specs[$pid][$spec_key] ?? '---SPEC-NOT-SET---';
                    }
                    $has_difference = count(array_unique($spec_values)) > 1;
                    ?>
                    <tr class="<?php echo $has_difference ? 'highlight-diff' : ''; ?>">
                        <td class="fw-bold"><?php echo htmlspecialchars($spec_key); ?></td>
                        <?php foreach ($product_ids as $pid): ?>
                            <td data-product-id="<?php echo $pid; ?>">
                                <?php echo isset($all_specs[$pid][$spec_key]) ? htmlspecialchars($all_specs[$pid][$spec_key]) : '-'; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
.compare-table th, .compare-table td { vertical-align: middle; text-align: center; }
.compare-table th.product-col { position: relative; }
.compare-table .spec-col { width: 15%; text-align: right; }
.compare-table .product-col { width: 21%; }
.compare-table .product-col img { max-height: 150px; object-fit: contain; }
.compare-table .product-col p { margin-bottom: 0; }
.compare-table a { text-decoration: none; color: inherit; }
.compare-table a:hover p { color: var(--bs-primary); }
.remove-from-compare-btn {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
}
.highlight-diff {
    background-color: #fff8e1; /* A light yellow color */
}
.highlight-diff td {
    font-weight: 500; /* Make the text slightly bolder */
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.remove-from-compare-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            
            // ارسال درخواست AJAX برای حذف از سشن
            fetch('<?php echo BASE_URL; ?>compare_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // حذف ستون از جدول
                    document.querySelectorAll(`[data-product-id="${productId}"]`).forEach(el => el.remove());
                    // به‌روزرسانی نوار مقایسه شناور
                    if (typeof window.updateCompareBar === 'function') {
                        window.updateCompareBar(data.compare_list);
                    }
                    // اگر محصولی باقی نماند، صفحه را رفرش کن تا پیام "محصولی نیست" نمایش داده شود
                    if (data.compare_list.length === 0) {
                        window.location.reload();
                    }
                }
            });
        });
    });
});
</script>

<?php include 'inc/footer.php'; ?>