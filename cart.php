<?php include 'inc/header.php'; ?>

<main class="container mt-4">
    <?php
    $current_step = 1;
    include 'inc/checkout_progress.php';
    ?>

    <?php
    // (جدید) نمایش پیام‌های خطا یا موفقیت
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
    ?>
    <?php
    // بررسی اینکه آیا سبد خرید خالی است یا نه
    if (empty($_SESSION['cart'])) {
        ?>
        <div class="text-center py-5">
            <div class="mb-3"><i class="fa fa-shopping-basket fa-4x text-muted opacity-50"></i></div>
            <h4 class="text-muted fw-bold">سبد خرید شما خالی است!</h4>
            <p class="text-muted mb-4">می‌توانید برای مشاهده محصولات به صفحه فروشگاه بروید.</p>
            <a href="products.php" class="btn btn-primary btn-lg px-5">مشاهده فروشگاه</a>
        </div>
        <?php
    } else {
        $cart_items = $_SESSION['cart'];
        $offer_ids = array_keys($cart_items);
        $total_price = 0;
        $total_insurance_cost = 0;

        // تبدیل آرایه ID ها به رشته برای استفاده در کوئری IN
        $placeholders = implode(',', array_fill(0, count($offer_ids), '?'));
        $types = str_repeat('i', count($offer_ids));

        // واکشی اطلاعات کامل محصولات موجود در سبد
        $sql = "
            SELECT 
                p.id as product_id, p.name as product_name, p.image,
                pv.variant_description,
                po.id as offer_id, po.price,
                s.shop_name as seller_name
            FROM product_offers po
            JOIN product_variants pv ON po.variant_id = pv.id
            JOIN products p ON pv.product_id = p.id
            JOIN sellers s ON po.seller_id = s.id
            WHERE po.id IN ($placeholders)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$offer_ids);
        $stmt->execute();
        $result = $stmt->get_result();

        $products_in_cart = [];
        while ($row = $result->fetch_assoc()) {
            $products_in_cart[$row['offer_id']] = $row;
        }

        // (جدید) واکشی اطلاعات تمام بیمه‌های ممکن برای محاسبه هزینه
        $all_insurances_stmt = $conn->prepare("SELECT id, name, price_percentage FROM insurances WHERE status = 'active'");
        $all_insurances_stmt->execute();
        $insurances_result = $all_insurances_stmt->get_result();
        $all_insurances = [];
        while ($row = $insurances_result->fetch_assoc()) {
            $all_insurances[$row['id']] = $row;
        }



    ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">کالاهای موجود در سبد</h5>
                <a href="cart_process.php?action=clear" class="btn btn-sm btn-outline-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید تمام کالاها را حذف کنید؟')"><i class="fa fa-trash-alt me-1"></i>خالی کردن سبد</a>
            </div>
            <form action="<?php echo BASE_URL; ?>cart_process.php" method="POST">
                <input type="hidden" name="update_cart" value="1">
                <?php foreach ($products_in_cart as $offer_id => $item): 
                    $cart_item_details = $cart_items[$offer_id];
                    $quantity = $cart_item_details['quantity'];
                    $insurance_ids = $cart_item_details['insurance_ids'];

                    // محاسبه هزینه بیمه برای این آیتم
                    $item_insurance_cost = 0;
                    foreach ($insurance_ids as $ins_id) {
                        if (isset($all_insurances[$ins_id])) {
                            $item_insurance_cost += ($item['price'] * $all_insurances[$ins_id]['price_percentage']) / 100;
                        }
                    }
                    $total_insurance_cost += $item_insurance_cost * $quantity;

                    $item_subtotal = ($item['price'] + $item_insurance_cost) * $quantity;
                    $total_price += $item_subtotal;
                ?>
                <div class="cart-item-card">
                    <div class="cart-item-image">
                        <a href="<?php echo BASE_URL . 'product-' . $item['product_id'] . '/' . slugify($item['product_name']); ?>">
                            <img src="<?php echo htmlspecialchars($item['image'] ? IMAGE_BASE_URL . 'products/' . $item['image'] : 'https://via.placeholder.com/110'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        </a>
                    </div>
                    <div class="cart-item-details">
                        <a href="<?php echo BASE_URL . 'product-' . $item['product_id'] . '/' . slugify($item['product_name']); ?>" class="cart-item-title"><?php echo htmlspecialchars($item['product_name']); ?></a>
                        
                        <div class="cart-item-info">
                            <i class="fa fa-palette"></i>
                            <span><?php echo htmlspecialchars($item['variant_description']); ?></span>
                        </div>
                        <div class="cart-item-info">
                            <i class="fa fa-store"></i>
                            <span>فروشنده: <?php echo htmlspecialchars($item['seller_name']); ?></span>
                        </div>
                        <?php if (!empty($insurance_ids)): ?>
                            <div class="cart-item-info text-success">
                                <i class="fa fa-shield-alt"></i>
                                <span>بیمه: <?php echo htmlspecialchars(implode('، ', array_map(fn($id) => $all_insurances[$id]['name'] ?? '', $insurance_ids))); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-end mt-3">
                            <div class="quantity-selector d-flex align-items-center border rounded px-2" style="height: 38px;">
                                <a href="cart_process.php?action=increase&offer_id=<?php echo $offer_id; ?>" class="text-dark p-1"><i class="fa fa-plus small"></i></a>
                                <input type="number" name="quantities[<?php echo $offer_id; ?>]" class="form-control border-0 text-center p-0 mx-2" style="width: 40px; background: transparent;" value="<?php echo $quantity; ?>" min="1" onchange="this.form.submit()">
                                <?php if ($quantity > 1): ?>
                                    <a href="cart_process.php?action=decrease&offer_id=<?php echo $offer_id; ?>" class="text-dark p-1"><i class="fa fa-minus small"></i></a>
                                <?php else: ?>
                                    <span class="text-muted p-1"><i class="fa fa-minus small"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="cart-item-price fw-bold text-dark h5 mb-1"><?php echo to_persian_num(number_format($item_subtotal)); ?> <small class="fs-6 fw-normal">تومان</small></div>
                                <a href="cart_process.php?action=remove&remove=<?php echo $offer_id; ?>" class="text-danger text-decoration-none small"><i class="fa fa-trash-alt me-1"></i>حذف</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header fw-bold">
                    صورت حساب
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            جمع کالاها
                            <span><?php echo to_persian_num(number_format($total_price - $total_insurance_cost)); ?> تومان</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            هزینه بیمه
                            <span><?php echo to_persian_num(number_format($total_insurance_cost)); ?> تومان</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold h5">
                            مبلغ قابل پرداخت
                            <span><?php echo to_persian_num(number_format($total_price)); ?> تومان</span>
                        </li>
                        <?php 
                        $remaining_seconds = get_cart_expiry_seconds();
                        if ($remaining_seconds > 0): ?>
                        <li class="list-group-item bg-light text-center py-3">
                            <div class="small mb-1 text-muted">زمان باقی‌مانده برای رزرو کالاها</div>
                            <div id="cart-timer" class="h4 fw-bold text-danger mb-0" data-seconds="<?php echo $remaining_seconds; ?>">
                                00:00:00
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-grid mt-3">
                        <?php
                        $checkout_link = isset($_SESSION['customer_id']) ? 'checkout.php' : 'login.php?redirect=cart.php';
                        ?>
                        <a href="<?php echo $checkout_link; ?>" class="btn btn-primary btn-lg">تسویه حساب و ثبت سفارش</a>
                        <a href="products.php" class="btn btn-outline-secondary mt-2">بازگشت به فروشگاه</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php } // End of else block ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const timerElement = document.getElementById('cart-timer');
    if (timerElement) {
        let remainingSeconds = parseInt(timerElement.dataset.seconds);

        function updateTimerDisplay() {
            if (remainingSeconds <= 0) {
                timerElement.innerText = "00:00:00";
                // (جدید) کدر کردن صفحه قبل از رفرش برای حس بهتر به کاربر
                const cartContainer = document.querySelector('.row.g-4');
                if (cartContainer) cartContainer.style.opacity = '0.3';
                
                // رفرش صفحه برای اعمال کد حذف سبد (cleanup_expired_cart_items)
                window.location.reload();
                return;
            }

            const h = Math.floor(remainingSeconds / 3600);
            const m = Math.floor((remainingSeconds % 3600) / 60);
            const s = remainingSeconds % 60;

            const display = 
                String(h).padStart(2, '0') + ":" + 
                String(m).padStart(2, '0') + ":" + 
                String(s).padStart(2, '0');
            
            // تبدیل به عدد فارسی برای زیبایی بیشتر (اختیاری)
            timerElement.innerText = toPersianNum(display);
            remainingSeconds--;
        }

        function toPersianNum(str) {
            const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return str.replace(/[0-9]/g, function(w) {
                return persian[+w];
            });
        }

        updateTimerDisplay();
        setInterval(updateTimerDisplay, 1000);
    }
});
</script>

<?php include 'inc/footer.php'; ?>