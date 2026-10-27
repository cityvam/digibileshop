<?php
$page_title = 'لیست سفارشات';
require_once 'inc/header.php';

$seller_id = $_SESSION['seller_id'];

// --- منطق فیلتر و صفحه‌بندی ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// فیلترها
$filters = [
    'order_id' => trim($_GET['order_id'] ?? ''),
    'customer' => trim($_GET['customer'] ?? ''),
    'status_id' => trim($_GET['status_id'] ?? ''),
];

$where_clauses = ["oi.seller_id = :seller_id"];
$params = [':seller_id' => $seller_id];

if (!empty($filters['order_id'])) {
    $where_clauses[] = "o.id = :order_id";
    $params[':order_id'] = $filters['order_id'];
}
if (!empty($filters['customer'])) {
    $where_clauses[] = "CONCAT(c.first_name, ' ', c.last_name) LIKE :customer";
    $params[':customer'] = '%' . $filters['customer'] . '%';
}
if (!empty($filters['status_id'])) {
    $where_clauses[] = "o.status_id = :status_id";
    $params[':status_id'] = $filters['status_id'];
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// واکشی وضعیت‌های سفارش برای دراپ‌داون فیلتر
$statuses = $pdo->query("SELECT id, status_name FROM order_statuses ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

// شمارش کل سفارشات برای صفحه‌بندی
$count_sql = "SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN customers c ON o.customer_id = c.id $where_sql";
$total_stmt = $pdo->prepare($count_sql);
$total_stmt->execute($params);
$total_orders = $total_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// واکشی سفارشات صفحه فعلی
$sql = "
    SELECT o.id, SUM(oi.subtotal) as total_amount, o.created_at, c.first_name, c.last_name, os.status_name, os.color_class
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN customers c ON o.customer_id = c.id
    JOIN order_statuses os ON o.status_id = os.id
    $where_sql
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => &$value) {
    $stmt->bindParam($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1>لیست سفارشات</h1>
</div>

<!-- Filter Card -->
<div class="card mb-4">
    <div class="card-header">فیلتر سفارشات</div>
    <div class="card-body">
        <form action="orders.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="order_id" class="form-label">شماره سفارش</label>
                <input type="text" name="order_id" id="order_id" class="form-control" value="<?php echo htmlspecialchars($filters['order_id']); ?>">
            </div>
            <div class="col-md-3">
                <label for="customer" class="form-label">نام مشتری</label>
                <input type="text" name="customer" id="customer" class="form-control" value="<?php echo htmlspecialchars($filters['customer']); ?>">
            </div>
            <div class="col-md-3">
                <label for="status_id" class="form-label">وضعیت</label>
                <select name="status_id" id="status_id" class="form-select">
                    <option value="">همه وضعیت‌ها</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status['id']; ?>" <?php echo ($filters['status_id'] == $status['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['status_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>اعمال فیلتر</button>
                <a href="orders.php" class="btn btn-outline-secondary w-100 mt-2">حذف فیلترها</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#سفارش</th>
                        <th>مشتری</th>
                        <th>تاریخ</th>
                        <th>مبلغ (سهم شما)</th>
                        <th class="text-center">وضعیت</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">هیچ سفارشی مطابق با فیلترهای شما یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo to_persian_nums($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td>
                                    <span title="<?php echo htmlspecialchars(jdate('Y/m/d H:i', strtotime($order['created_at']))); ?>">
                                        <?php echo time_ago_in_persian($order['created_at']); ?>
                                    </span>
                                </td>
                                <td><?php echo to_persian_nums(number_format($order['total_amount'])); ?> تومان</td>
                                <td class="text-center">
                                    <span class="badge <?php echo $order['color_class'] ?? 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($order['status_name']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary" title="مشاهده جزئیات">
                                        <i class="bi bi-eye-fill"></i>
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
        <?php echo render_pagination('orders.php', $page, $total_pages, $filters); ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'inc/footer.php'; ?>