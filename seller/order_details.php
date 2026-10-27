<?php
$page_title = 'جزئیات سفارش';
require_once 'inc/header.php';

$seller_id = $_SESSION['seller_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    $_SESSION['error_message'] = 'شناسه سفارش نامعتبر است.';
    header("Location: orders.php");
    exit();
}

// --- واکشی اطلاعات اصلی سفارش و مشتری ---
$stmt_order = $pdo->prepare("
    SELECT o.*, c.first_name, c.last_name, c.mobile, c.home_address, os.status_name, os.color_class
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN order_statuses os ON o.status_id = os.id
    WHERE o.id = ?
");
$stmt_order->execute([$order_id]);
$order = $stmt_order->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error_message'] = 'سفارش مورد نظر یافت نشد.';
    header("Location: orders.php");
    exit();
}

// --- واکشی آیتم‌های سفارش فقط برای این فروشنده ---
$stmt_items = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image, v.variant_description
    FROM order_items oi
    JOIN product_variants v ON oi.variant_id = v.id
    JOIN products p ON v.product_id = p.id
    WHERE oi.order_id = ? AND oi.seller_id = ?
");
$stmt_items->execute([$order_id, $seller_id]);
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// --- بررسی امنیتی: اگر فروشنده هیچ آیتمی در این سفارش ندارد، اجازه مشاهده نده ---
if (empty($order_items)) {
    $_SESSION['error_message'] = 'شما به این سفارش دسترسی ندارید.';
    header("Location: orders.php");
    exit();
}

// محاسبه مجموع مبلغ مربوط به این فروشنده
$seller_total = 0;
foreach ($order_items as $item) {
    $seller_total += $item['subtotal'];
}

?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1>جزئیات سفارش #<?php echo to_persian_nums($order_id); ?></h1>
    <a href="orders.php" class="btn btn-secondary">بازگشت به لیست سفارشات</a>
</div>

<div class="row g-4">
    <!-- اطلاعات سفارش و مشتری -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">اطلاعات کلی سفارش</div>
            <div class="card-body">
                <p><strong>تاریخ ثبت:</strong> <?php echo to_persian_nums(jdate('Y/m/d H:i', strtotime($order['created_at']))); ?></p>
                <p><strong>وضعیت فعلی:</strong> <span class="badge <?php echo $order['color_class'] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($order['status_name']); ?></span></p>
                <p><strong>مبلغ کل سفارش:</strong> <?php echo to_persian_nums(number_format($order['total_amount'])); ?> تومان</p>
                <p class="text-success"><strong>مبلغ سهم شما:</strong> <?php echo to_persian_nums(number_format($seller_total)); ?> تومان</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header">اطلاعات مشتری</div>
            <div class="card-body">
                <p><strong>نام:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                <p><strong>موبایل:</strong> <?php echo to_persian_nums($order['mobile']); ?></p>
                <?php if (!empty($order['home_address'])): ?>
                    <p class="mb-0"><strong>آدرس:</strong> <?php echo htmlspecialchars($order['home_address']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- لیست محصولات فروشنده در این سفارش -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">محصولات شما در این سفارش</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="text-center">تصویر</th>
                                <th>محصول / متغیر</th>
                                <th class="text-center">تعداد</th>
                                <th>قیمت واحد</th>
                                <th>مبلغ کل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php $image_url = $item['image'] ? STATIC_ASSETS_URL . '/products/' . htmlspecialchars($item['image']) : rtrim(BASE_URL, '/') . '/img/placeholder.png'; ?>
                                        <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" width="50" height="50" style="object-fit: cover;" class="rounded">
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($item['variant_description']); ?></small>
                                    </td>
                                    <td class="text-center"><?php echo to_persian_nums($item['quantity']); ?></td>
                                    <td><?php echo to_persian_nums(number_format($item['price'])); ?> تومان</td>
                                    <td><?php echo to_persian_nums(number_format($item['subtotal'])); ?> تومان</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'inc/footer.php'; ?>