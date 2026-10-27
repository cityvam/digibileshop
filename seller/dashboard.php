<?php
$page_title = 'داشبورد';
require_once 'inc/header.php'; // این فایل شامل config.php و seller_auth.php نیز می‌باشد

$seller_id = $_SESSION['seller_id'];

// --- واکشی داده‌ها برای کارت‌های خلاصه ---

// 1. مجموع فروش (فقط سفارشات تایید شده و بالاتر)
$stmt_sales = $pdo->prepare("
    SELECT SUM(oi.subtotal) 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN order_statuses os ON o.status_id = os.id
    WHERE oi.seller_id = ? AND os.status_key IN ('confirmed', 'processing', 'shipped', 'completed')
");
$stmt_sales->execute([$seller_id]);
$total_sales = $stmt_sales->fetchColumn() ?? 0;

// 2. تعداد سفارشات جدید (در حال پردازش)
$stmt_new_orders = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id) 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN order_statuses os ON o.status_id = os.id
    WHERE oi.seller_id = ? AND os.status_key = 'processing'
");
$stmt_new_orders->execute([$seller_id]);
$new_orders_count = $stmt_new_orders->fetchColumn() ?? 0;

// 3. تعداد محصولات فعال (با موجودی)
$stmt_active_products = $pdo->prepare("SELECT COUNT(*) FROM product_offers WHERE seller_id = ? AND stock > 0");
$stmt_active_products->execute([$seller_id]);
$active_products_count = $stmt_active_products->fetchColumn() ?? 0;

// 4. کالاهای رو به اتمام (موجودی کمتر یا مساوی 5)
$stmt_low_stock = $pdo->prepare("SELECT COUNT(*) FROM product_offers WHERE seller_id = ? AND stock > 0 AND stock <= 5");
$stmt_low_stock->execute([$seller_id]);
$low_stock_count = $stmt_low_stock->fetchColumn() ?? 0;

// --- واکشی داده‌های فروش ماهانه ---
$stmt_monthly_sales = $pdo->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS sale_month,
        SUM(oi.subtotal) AS monthly_total
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ?
    GROUP BY sale_month
    ORDER BY sale_month ASC
");
$stmt_monthly_sales->execute([$seller_id]);
$monthly_sales = $stmt_monthly_sales->fetchAll(PDO::FETCH_KEY_PAIR);

// --- واکشی آخرین سفارشات ---
$stmt_recent_orders = $pdo->prepare("
    SELECT o.id, SUM(oi.subtotal) as total_amount, o.created_at, c.first_name, c.last_name, os.status_name, os.color_class
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN customers c ON o.customer_id = c.id
    JOIN order_statuses os ON o.status_id = os.id
    WHERE oi.seller_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt_recent_orders->execute([$seller_id]);
$recent_orders = $stmt_recent_orders->fetchAll(PDO::FETCH_ASSOC);

// --- واکشی پرفروش‌ترین محصولات ---
$stmt_top_products = $pdo->prepare("
    SELECT 
        p.name as product_name, 
        p.image, 
        SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN order_statuses os ON o.status_id = os.id
    JOIN product_variants v ON oi.variant_id = v.id
    JOIN products p ON v.product_id = p.id
    WHERE oi.seller_id = ? 
      AND os.status_key IN ('confirmed', 'processing', 'shipped', 'completed')
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt_top_products->execute([$seller_id]);
$top_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);

// آماده‌سازی داده‌های نمودار برای استفاده در جاوااسکریپت
$chart_labels = [];
$chart_data = [];
foreach ($monthly_sales as $month => $amount) {
    // تبدیل تاریخ میلادی ماه به شمسی (نام ماه و سال)
    $timestamp = strtotime($month . '-01');
    $chart_labels[] = jdate('F Y', $timestamp);
    $chart_data[] = $amount;
}

?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1>داشبورد</h1>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col">
        <div class="summary-card">
            <div class="card-content"><span class="title">مجموع فروش</span><span class="amount"><?php echo to_persian_nums(number_format($total_sales)); ?> <small>تومان</small></span></div>
            <div class="icon text-success"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="summary-card">
            <div class="card-content"><span class="title">سفارشات جدید</span><span class="amount"><?php echo to_persian_nums($new_orders_count); ?></span></div>
            <div class="icon text-primary"><i class="bi bi-receipt"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="summary-card">
            <div class="card-content"><span class="title">محصولات فعال</span><span class="amount"><?php echo to_persian_nums($active_products_count); ?></span></div>
            <div class="icon text-info"><i class="bi bi-box-seam"></i></div>
        </div>
    </div>
    <div class="col">
        <div class="summary-card">
            <div class="card-content"><span class="title">کالاهای رو به اتمام</span><span class="amount"><?php echo to_persian_nums($low_stock_count); ?></span></div>
            <div class="icon <?php echo $low_stock_count > 0 ? 'text-danger' : 'text-warning'; ?>"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
    </div>
</div>

<!-- Sales Chart -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">فروش ماهانه</h5>
    </div>
    <div class="card-body">
        <canvas id="monthlySalesChart" width="400" height="200"></canvas>
    </div>
</div>

<style>
.chart-container {
    position: relative;
}
</style>

<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">آخرین سفارشات</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#سفارش</th>
                                <th>مشتری</th>
                                <th>تاریخ</th>
                                <th>مبلغ کل</th>
                                <th class="text-center">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="5" class="text-center text-muted">هنوز سفارشی برای شما ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo to_persian_nums($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                        <td><?php echo time_ago_in_persian($order['created_at']); ?></td>
                                        <td><?php echo to_persian_nums(number_format($order['total_amount'])); ?> تومان</td>
                                        <td class="text-center"><span class="badge <?php echo $order['color_class'] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($order['status_name']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">پرفروش‌ترین‌ها</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_products)): ?>
                    <div class="text-center text-muted p-4">هنوز فروشی نداشته‌اید.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top_products as $product): ?>
                            <li class="list-group-item d-flex align-items-center p-3">
                                <?php $image_url = $product['image'] ? STATIC_ASSETS_URL . '/products/' . htmlspecialchars($product['image']) : rtrim(BASE_URL, '/') . '/img/placeholder.png'; ?>
                                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" width="50" height="50" class="rounded me-3" style="object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 small fw-bold"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                    <small class="text-muted">فروش رفته: <?php echo to_persian_nums($product['total_sold']); ?> عدد</small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<!-- اضافه کردن کتابخانه Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlySalesChart').getContext('2d');
    
    // تنظیم فونت پیش‌فرض نمودار به وزیر
    Chart.defaults.font.family = 'Vazirmatn';

    new Chart(ctx, {
        type: 'line', // نوع نمودار: خطی
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'فروش ماهانه (تومان)',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#0d6efd', // رنگ خط (آبی بوت‌استرپ)
                backgroundColor: 'rgba(13, 110, 253, 0.1)', // رنگ زیر خط
                borderWidth: 2,
                fill: true,
                tension: 0.3 // انحنای خط برای زیبایی
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' تومان';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fa-IR').format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>
<?php $page_scripts = ob_get_clean(); ?>

<?php require_once 'inc/footer.php'; ?>