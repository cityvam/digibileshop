<?php
// 1. ابتدا فایل‌های ضروری برای بررسی منطق را فراخوانی می‌کنیم
require_once 'inc/config.php';
require_once 'inc/functions.php';


// 2. بررسی شرایط لازم قبل از نمایش هرگونه HTML
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['message'] = "برای ادامه تسویه حساب، لطفاً ابتدا وارد شوید.";
    $_SESSION['message_type'] = "warning";
    header("Location: " . BASE_URL . "login.php"); // استفاده از BASE_URL برای آدرس‌دهی صحیح
    exit();
}

if (empty($_SESSION['cart'])) {
    $_SESSION['message'] = 'سبد خرید شما خالی است.';
    $_SESSION['message_type'] = 'info';
    header("Location: " . BASE_URL . "cart.php");
    exit();
}

// 3. اگر تمام شرایط برقرار بود، حالا هدر را فراخوانی می‌کنیم
$page_title = 'تکمیل سفارش';
$current_step = 2; // (جدید) تعریف مرحله فعلی برای نوار پیشرفت
include 'inc/header.php';

// 4. واکشی آدرس‌های ذخیره شده کاربر
$customer_id = $_SESSION['customer_id'];
$stmt_customer_data = $conn->prepare("
    SELECT c.wallet_balance, a.* 
    FROM customers c 
    LEFT JOIN customer_addresses a ON c.id = a.customer_id AND a.id IS NOT NULL
    WHERE c.id = ?
");
$stmt_customer_data->bind_param("i", $customer_id);
$stmt_customer_data->execute();
$result_data = $stmt_customer_data->get_result();
$addresses = $result_data->num_rows > 0 ? $result_data->fetch_all(MYSQLI_ASSOC) : [];
$wallet_balance = $addresses[0]['wallet_balance'] ?? 0;

// (جدید) واکشی اطلاعات مشتری برای پر کردن پیش‌فرض فرم آدرس
$stmt_customer_info = $conn->prepare("SELECT first_name, last_name, mobile FROM customers WHERE id = ?");
$stmt_customer_info->bind_param("i", $customer_id);
$stmt_customer_info->execute();
$customer_info_result = $stmt_customer_info->get_result()->fetch_assoc();
$customer_full_name = ($customer_info_result['first_name'] ?? '') . ' ' . ($customer_info_result['last_name'] ?? '');
$customer_mobile = $customer_info_result['mobile'] ?? '';

// 5. واکشی اطلاعات و محاسبه قیمت کل سبد خرید
$cart_items = $_SESSION['cart'];
$offer_ids = array_keys($cart_items);
$total_price = 0;

$placeholders = implode(',', array_fill(0, count($offer_ids), '?'));
$types = str_repeat('i', count($offer_ids));

$sql = "
    SELECT po.id as offer_id, po.price, p.name as product_name
    FROM product_offers po
    JOIN product_variants pv ON po.variant_id = pv.id
    JOIN products p ON pv.product_id = p.id
    WHERE po.id IN ($placeholders)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$offer_ids);
$stmt->execute();
$result = $stmt->get_result();

$products_in_cart = [];
while ($row = $result->fetch_assoc()) {
    $products_in_cart[$row['offer_id']] = $row; // ساختار سشن تغییر کرده
    $total_price += $row['price'] * $cart_items[$row['offer_id']]['quantity'];
}

// (اصلاح) خواندن شیوه‌های ارسال و یک هزینه پیش‌فرض برای آنها
$stmt_shipping = $conn->prepare("
    SELECT 
        sm.id, 
        sm.name, 
        sm.description, 
        (SELECT cost FROM shipping_price_rules WHERE shipping_method_id = sm.id ORDER BY min_weight ASC LIMIT 1) as price
    FROM shipping_methods sm
    WHERE 
        sm.is_active = 1
    GROUP BY 
        sm.id, 
        sm.name, 
        sm.description
    ORDER BY sm.display_order ASC
");
$stmt_shipping->execute();
$shipping_methods_result = $stmt_shipping->get_result();
$shipping_methods = $shipping_methods_result->fetch_all(MYSQLI_ASSOC);

// (جدید) واکشی و گروه‌بندی بازه‌های زمانی ارسال
$stmt_time_slots = $conn->prepare("
    -- Debug: Querying shipping_time_slots
    SELECT id, shipping_method_id, specific_date, start_time, end_time, max_orders, 0 as booked_orders
    FROM shipping_time_slots 
    WHERE is_active = 1 AND specific_date >= CURDATE()
    ORDER BY specific_date ASC, start_time ASC
");
$stmt_time_slots->execute();
$time_slots_result = $stmt_time_slots->get_result();
$time_slots_count = $time_slots_result->num_rows; // Debug: Count rows
$time_slots_by_method = [];
while ($slot = $time_slots_result->fetch_assoc()) {
    // (اصلاح) استفاده از تابع to_jalali_date برای دریافت نام روز، شماره روز و نام ماه شمسی
    $day_name = to_jalali_date($slot['specific_date'], 'EEEE'); // نام کامل روز (مثلا: چهارشنبه)
    $day_number = to_persian_num(to_jalali_date($slot['specific_date'], 'dd')); // شماره روز (مثلا: ۰۱)
    $month_name = to_jalali_date($slot['specific_date'], 'MMMM'); // نام کامل ماه (مثلا: فروردین)

    $time_slots_by_method[$slot['shipping_method_id']][$slot['specific_date']]['display'] = ['day_name' => $day_name, 'day_number' => $day_number, 'month_name' => $month_name];
    $time_slots_by_method[$slot['shipping_method_id']][$slot['specific_date']]['slots'][] = [
        'id' => $slot['id'],
        'start' => $slot['start_time'], 
        'end' => $slot['end_time'],
        'max_orders' => $slot['max_orders'],
        'booked_orders' => $slot['booked_orders']
    ];
}
error_log("Debug: Fetched " . $time_slots_count . " time slots from database."); // Debug: Log count
error_log("Debug: Processed time_slots_by_method array: " . print_r($time_slots_by_method, true)); // Debug: Log array content

// (جدید) آماده‌سازی داده‌های دیباگ برای نمایش در کنسول مرورگر
$php_debug_data = [
    'message' => 'این اطلاعات از سمت سرور (PHP) برای دیباگ ارسال شده است.',
    'fetched_time_slots_count' => $time_slots_count,
    'processed_time_slots_array' => $time_slots_by_method,
    'query_conditions' => 'is_active = 1 AND specific_date >= CURDATE()'
];
?>

<main class="container mt-4">
    <?php
    include 'inc/checkout_progress.php'; // (جدید) نمایش نوار پیشرفت
    ?>
    <form action="payment.php" method="POST" id="checkoutForm">
        <div class="row g-4">
            <!-- ستون اصلی (آدرس و شیوه ارسال) -->
            <div class="col-lg-8">
                <!-- کارت آدرس -->
                <div class="checkout-card">
                    <div class="checkout-card-header">
                        <h5>آدرس تحویل سفارش</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                            <i class="fa fa-plus me-1"></i>افزودن آدرس جدید
                        </button>
                    </div>
                    <?php if (!empty($addresses)): ?>
                        <?php foreach ($addresses as $index => $address): ?>
                            <label class="address-item row mx-0 align-items-center" for="address_<?php echo $address['id']; ?>">
                                <div class="col-8 d-flex align-items-center">
                                    <input type="radio" class="form-check-input" id="address_<?php echo $address['id']; ?>" name="shipping_address_id" value="<?php echo $address['id']; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> required>
                                    <div class="ms-4">
                                        <p class="mb-1 fw-bold"><?php echo htmlspecialchars($address['province'] . '، ' . $address['city'] . '، ' . $address['full_address']); ?></p>
                                    </div>
                                </div>
                                <div class="col-4 cost text-start">
                                    <p class="mb-0 small"><i class="fa fa-tag me-2"></i><?php echo htmlspecialchars($address['title']); ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            شما هیچ آدرسی ثبت نکرده‌اید. لطفاً ابتدا از <a href="profile/addresses.php" class="alert-link">اینجا</a> یک آدرس جدید اضافه کنید.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- کارت شیوه ارسال -->
                <div class="checkout-card">
                    <div class="checkout-card-header">
                        <h5>شیوه ارسال</h5>
                    </div>
                    <div class="accordion" id="shippingMethodsAccordion">
                        <?php foreach ($shipping_methods as $index => $method): ?>
                            <?php 
                                $method_id = htmlspecialchars($method['id']); 
                                $has_time_slots = isset($time_slots_by_method[$method['id']]) && !empty($time_slots_by_method[$method['id']]);
                                $is_disabled = !$has_time_slots;
                            ?>
                            <div class="shipping-method-container">
                                <label class="shipping-method-item row mx-0 align-items-center <?php echo $is_disabled ? 'disabled' : ''; ?>" for="shipping_<?php echo $method_id; ?>" 
                                       <?php if ($has_time_slots): ?>data-bs-toggle="collapse" data-bs-target="#time-slots-<?php echo $method_id; ?>" aria-expanded="false" aria-controls="time-slots-<?php echo $method_id; ?>"<?php endif; ?>>
                                    <div class="col-8 d-flex align-items-center">
                                        <input type="radio" class="form-check-input" id="shipping_<?php echo $method_id; ?>" name="shipping_method" value="<?php echo $method_id; ?>" data-cost="<?php echo htmlspecialchars($method['price']); ?>" required <?php echo $index === 0 ? 'checked' : ''; ?> <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                        <div class="text-end">
                                            <span class="mb-1 fw-bold"><?php echo htmlspecialchars($method['name']); ?></span>
                                            <small class="text-muted ms-2">(<?php echo htmlspecialchars($method['description']); ?>)</small>
                                        </div>
                                    </div>
                                    <div class="col-4 cost text-start">
                                        <?php if ($method['price'] > 0): ?>
                                            <i class="fa fa-wallet me-2"></i>
                                            هزینه: <?php echo to_persian_num(number_format($method['price'])); ?> تومان
                                        <?php else: ?>
                                            <i class="fa fa-box-open me-2"></i>
                                            پس‌کرایه
                                        <?php endif; ?>
                                    </div>
                                </label>

                                <?php if ($has_time_slots): ?>
                                <div id="time-slots-<?php echo $method_id; ?>" class="accordion-collapse collapse" data-bs-parent="#shippingMethodsAccordion">
                                    <div class="time-slot-box">
                                        <p class="fw-bold small">انتخاب زمان تحویل سفارش به آدرس مشتری:</p>
                                        <div class="d-flex gap-2 mb-3">
                                            <?php foreach ($time_slots_by_method[$method['id']] as $date => $date_data): ?>
                                                <label class="date-slot-item" data-date="<?php echo htmlspecialchars($date); ?>">
                                                    <input type="radio" name="delivery_date_<?php echo $method_id; ?>" value="<?php echo $date; ?>" class="d-none">
                                                    <span class="day-name"><?php echo $date_data['display']['day_name']; ?></span>
                                                    <span class="day-number"><?php echo $date_data['display']['day_number']; ?></span>
                                                    <span class="month-name"><?php echo $date_data['display']['month_name']; ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="time-slots-for-date">
                                            <!-- بازه‌های زمانی با جاوااسکریپت در اینجا قرار می‌گیرند -->
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <!-- ستون خلاصه سفارش -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                جمع کالاها (<?php echo to_persian_num(count($cart_items)); ?>)
                                <span id="subtotal"><?php echo to_persian_num(number_format($total_price)); ?> تومان</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                هزینه ارسال
                                <span id="shipping-cost">محاسبه می‌شود</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 fw-bold h5">
                                مبلغ قابل پرداخت
                                <span id="final-price"><?php echo to_persian_num(number_format($total_price)); ?> تومان</span>
                            </li>
                        </ul>
                        <div class="d-grid mt-3">
                            <button class="btn btn-primary btn-lg" type="submit" <?php echo empty($addresses) ? 'disabled' : ''; ?>>انتخاب روش ارسال</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<!-- (جدید) مودال افزودن آدرس -->
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAddressModalLabel">افزودن آدرس جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo BASE_URL; ?>address_process.php" method="POST" id="modalAddressForm">
                    <input type="hidden" name="source" value="checkout"> <!-- برای بازگشت به همین صفحه -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_title" class="form-label">عنوان آدرس (مثلا: خانه) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_title" name="title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_recipient_name" class="form-label">نام و نام خانوادگی تحویل‌گیرنده <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_recipient_name" name="recipient_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_province" class="form-label">استان <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_province" name="province" required>
                                <option value="" selected disabled>در حال بارگذاری...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_city" class="form-label">شهر <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_city" name="city" required disabled>
                                <option value="" selected disabled>ابتدا استان را انتخاب کنید</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_full_address" class="form-label">آدرس کامل پستی <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="modal_full_address" name="full_address" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_postal_code" class="form-label">کد پستی</label>
                            <input type="text" class="form-control" id="modal_postal_code" name="postal_code">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_recipient_mobile" class="form-label">شماره موبایل تحویل‌گیرنده <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="modal_recipient_mobile" name="recipient_mobile" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" name="add_address" form="modalAddressForm" class="btn btn-primary">ثبت آدرس</button>
            </div>
        </div>
    </div>
</div>

<!-- (اصلاح) کد جاوااسکریپت اختصاصی برای این صفحه با قابلیت دیباگ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // (جدید) نمایش اطلاعات دیباگ از PHP در کنسول مرورگر
    console.groupCollapsed('%c[PHP DEBUG] - Click to expand', 'color: #1e90ff; font-weight: bold; background-color: #f0f8ff; padding: 2px 5px; border-radius: 3px;');
    console.log(<?php echo json_encode($php_debug_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>);
    console.groupEnd();


    console.log('Checkout DOM loaded. Initializing script...');

    // داده‌های بازه‌های زمانی را از PHP به JS منتقل می‌کنیم
    const timeSlotsData = <?php echo json_encode($time_slots_by_method, JSON_UNESCAPED_UNICODE); ?>;
    console.log('Time Slots Data:', timeSlotsData);

    // تابع برای فرمت‌دهی اعداد به فارسی
    function toPersianNum(num) {
        const persian = { 0: '۰', 1: '۱', 2: '۲', 3: '۳', 4: '۴', 5: '۵', 6: '۶', 7: '۷', 8: '۸', 9: '۹' };
        return String(num).replace(/[0-9]/g, (w) => persian[w]);
    }

    // تابع برای رندر کردن بازه‌های زمانی یک تاریخ مشخص
    function renderTimeSlots(date, methodId, container) {
        console.log(`Rendering time slots for method ${methodId} on date ${date}`);
        let html = '<div class="row g-2">';
        const slots = timeSlotsData[methodId]?.[date]?.slots || [];
        if (slots.length > 0) {
            slots.forEach((slot, index) => {
                const startTime = slot.start.substring(0, 5);
                const endTime = slot.end.substring(0, 5);
                const capacity = parseInt(slot.max_orders);
                const booked = parseInt(slot.booked_orders);
                const remaining = capacity - booked;
                const percentage = capacity > 0 ? (booked / capacity) * 100 : 0;
                const isDisabled = remaining <= 0;

                let capacityText = '';
                if (capacity !== null && capacity > 0 && !isDisabled) { // بررسی capacity !== null
                    if (remaining <= 3) { // اگر ظرفیت کم بود، با رنگ قرمز نمایش بده
                        capacityText = `<small class="text-danger fw-bold">فقط ${toPersianNum(remaining)} عدد باقی مانده</small>`;
                    } else {
                        capacityText = `<small class="text-muted">ظرفیت: ${toPersianNum(remaining)}</small>`;
                    }
                }

                // (اصلاح) ایجاد ID یکتا برای رادیو و اتصال آن به label
                const radioId = `delivery_time_slot_${slot.id}`;

                html += `
                    <div class="col-12">
                        <label class="time-slot-option w-100 ${isDisabled ? 'disabled' : ''}" for="${radioId}">
                            <input type="radio" id="${radioId}" name="delivery_time_${methodId}" value="${slot.id}" class="d-none" ${isDisabled ? 'disabled' : ''}>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>ساعت ${toPersianNum(startTime)} تا ${toPersianNum(endTime)}</span>
                                ${capacityText}
                            </div>
                            ${capacity > 0 ? `
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar" role="progressbar" style="width: ${percentage}%;" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            ` : ''}
                        </label>
                    </div>
                `;
            });
        } else {
            html += '<p class="small text-muted">متاسفانه بازه زمانی برای این روز وجود ندارد.</p>';
        }
        html += '</div>';
        container.innerHTML = html;


        // (جدید) انتخاب اولین بازه زمانی موجود به صورت پیش‌فرض
        const firstAvailableSlot = container.querySelector('input[type="radio"]:not(:disabled)');
        if (firstAvailableSlot) {
            firstAvailableSlot.checked = true;
            firstAvailableSlot.closest('label').classList.add('selected');
        }

        console.log('Time slots rendered.');
    }

    // افزودن Event Listener به تمام آیتم‌های تاریخ
    document.querySelectorAll('.date-slot-item').forEach(label => {
        label.addEventListener('click', function() {
            console.log('DEBUG: Date slot clicked:', this.dataset.date);
            const methodContainer = this.closest('.shipping-method-container');
            const methodId = methodContainer.querySelector('input[name="shipping_method"]').value;
            const date = this.querySelector('input').value;

            // حذف کلاس selected از سایر آیتم‌های تاریخ در همین گروه
            methodContainer.querySelectorAll('.date-slot-item').forEach(item => item.classList.remove('selected'));
            this.classList.add('selected');

            // رندر کردن بازه‌های زمانی مربوطه
            const timeSlotsContainer = methodContainer.querySelector('.time-slots-for-date');
            renderTimeSlots(date, methodId, timeSlotsContainer);
        });
    });

    // تابع برای به‌روزرسانی خلاصه سفارش و کلاس‌های selected
    function updateCheckoutState() {
        console.log('Updating checkout state...');
        // به‌روزرسانی کلاس selected برای آدرس‌ها
        document.querySelectorAll('input[name="shipping_address_id"]').forEach(radio => {
            radio.closest('label').classList.toggle('selected', radio.checked);
        });

        // به‌روزرسانی کلاس selected برای روش‌های ارسال
        document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
            radio.closest('label').classList.toggle('selected', radio.checked);
        });

        // به‌روزرسانی قیمت‌ها
        const selectedShippingMethod = document.querySelector('input[name="shipping_method"]:checked');
        const shippingCostEl = document.getElementById('shipping-cost');
        const finalPriceEl = document.getElementById('final-price');
        const subtotalEl = document.getElementById('subtotal');
        const subtotal = parseFloat(subtotalEl.textContent.replace(/[^۰-۹0-9]/g, '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))) || 0;

        if (selectedShippingMethod) {
            console.log('Selected shipping method found. Updating prices.');
            const cost = parseFloat(selectedShippingMethod.dataset.cost);
            if (cost > 0) {
                shippingCostEl.textContent = toPersianNum(cost.toLocaleString()) + ' تومان';
            } else {
                shippingCostEl.textContent = 'پس‌کرایه';
            }
            const finalPrice = subtotal + cost;
            finalPriceEl.textContent = toPersianNum(finalPrice.toLocaleString()) + ' تومان';
        } else {
            console.log('No shipping method selected.');
            shippingCostEl.textContent = 'محاسبه می‌شود';
            finalPriceEl.textContent = toPersianNum(subtotal.toLocaleString()) + ' تومان';
        }
    }

    // افزودن Event Listener به کل فرم برای مدیریت تغییرات
    document.getElementById('checkoutForm').addEventListener('change', function(event) {
        if (event.target.name === 'shipping_address_id' || event.target.name === 'shipping_method') {
            console.log(`Input changed: ${event.target.name}`);
            updateCheckoutState();

            // اگر روش ارسال تغییر کرد، منطق مربوط به ریست کردن آکاردئون‌های دیگر را اجرا کن
            if (event.target.name === 'shipping_method') {
                 // فعال کردن اولین روز در گروه جدید
                 const newMethodContainer = event.target.closest('.shipping-method-container');
                 const firstDateItem = newMethodContainer.querySelector('.date-slot-item');
                 if (firstDateItem && !firstDateItem.classList.contains('selected')) {
                     console.log('Activating first date for new shipping method.');
                     // ریست کردن انتخاب‌های قبلی قبل از کلیک جدید
                     document.querySelectorAll('.date-slot-item.selected').forEach(item => item.classList.remove('selected'));
                     firstDateItem.click();
                     firstDateItem.querySelector('input').checked = true;
                 }

                 // (جدید) باز کردن آکاردئون مربوطه و بستن بقیه
                 const targetId = event.target.closest('label').getAttribute('data-bs-target');
                 const targetCollapse = document.querySelector(targetId);
                 if (targetCollapse) {
                    const bsCollapse = new bootstrap.Collapse(targetCollapse);
                    bsCollapse.show();
                 }
            }        
        } else if (event.target.name && event.target.name.startsWith('delivery_time_')) {
            console.log(`DEBUG: Time slot radio changed. Target name: ${event.target.name}, Value: ${event.target.value}`);
            const clickedLabel = event.target.closest('label.time-slot-option');
            const currentMethodContainer = event.target.closest('.shipping-method-container');
            if (currentMethodContainer && clickedLabel) {
                currentMethodContainer.querySelectorAll('.time-slot-option').forEach(label => label.classList.remove('selected'));
                clickedLabel.classList.add('selected');
                console.log(`DEBUG: Added 'selected' to label for ID: ${clickedLabel.getAttribute('for')}`);
            }
        }
    });


    // اجرای اولیه برای نمایش حالت پیش‌فرض
    function initializeCheckout() {
        console.log('Initializing checkout state...');
        const firstActiveMethod = document.querySelector('input[name="shipping_method"]:checked');
        if (firstActiveMethod) {
            console.log('Found initial active shipping method.');
            const container = firstActiveMethod.closest('.shipping-method-container');
            const firstDateItem = container.querySelector('.date-slot-item');
            if (firstDateItem) {
                console.log('Found initial date item. Simulating click.');
                // به صورت دستی اولین روز را انتخاب و بازه‌های زمانی آن را رندر می‌کنیم
                firstDateItem.classList.add('selected');
                firstDateItem.querySelector('input').checked = true;
                const methodId = firstActiveMethod.value;
                const date = firstDateItem.querySelector('input').value;
                const timeSlotsContainer = container.querySelector('.time-slots-for-date');
                renderTimeSlots(date, methodId, timeSlotsContainer);

            }
        }
        updateCheckoutState();
        console.log('Initialization complete.');
    }

    initializeCheckout();

    // (جدید) منطق مربوط به خواندن استان و شهر از JSON
    const addAddressModal = document.getElementById('addAddressModal');
    if (addAddressModal) {
        let citiesData = null; // برای ذخیره داده‌ها و جلوگیری از fetch مجدد

        addAddressModal.addEventListener('show.bs.modal', function () {
            const provinceSelect = document.getElementById('modal_province');
            const citySelect = document.getElementById('modal_city');

            // (جدید) پر کردن فیلدهای نام و موبایل با اطلاعات مشتری
            const recipientNameInput = document.getElementById('modal_recipient_name');
            const recipientMobileInput = document.getElementById('modal_recipient_mobile');
            recipientNameInput.value = '<?php echo htmlspecialchars($customer_full_name, ENT_QUOTES); ?>';
            recipientMobileInput.value = '<?php echo htmlspecialchars($customer_mobile, ENT_QUOTES); ?>';

            // اگر داده‌ها قبلا واکشی نشده‌اند، آنها را fetch کن
            if (!citiesData) {
                console.log('Fetching cities data...');
                fetch('<?php echo BASE_URL; ?>assets/js/iran_cities.json')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Cities data loaded successfully.');
                        citiesData = data; // ذخیره داده‌ها
                        provinceSelect.innerHTML = '<option value="" selected disabled>استان را انتخاب کنید</option>';
                        citiesData.forEach(province => {
                            const option = document.createElement('option');
                            option.value = province.name;
                            option.textContent = province.name;
                            provinceSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading cities data:', error);
                        provinceSelect.innerHTML = '<option value="" selected disabled>خطا در بارگذاری استان‌ها</option>';
                    });
            }

            provinceSelect.addEventListener('change', function() {
                // این Event Listener باید داخل show.bs.modal باشد تا به درستی کار کند
                // اما برای جلوگیری از ثبت چندباره، می‌توان آن را بیرون تعریف کرد و فقط یکبار ثبت کرد.
                // برای سادگی فعلا اینجا نگه می‌داریم.
                const selectedProvinceName = this.value;
                console.log('Province changed to:', selectedProvinceName);

                // اطمینان از اینکه citiesData بارگذاری شده
                if (!citiesData) return;

                const selectedProvince = citiesData.find(p => p.name === selectedProvinceName);

                citySelect.innerHTML = '<option value="" selected disabled>شهر را انتخاب کنید</option>';
                citySelect.disabled = true;

                if (selectedProvince) {
                    selectedProvince.cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        citySelect.appendChild(option);
                    });
                    citySelect.disabled = false;
                }
            });
        });
    }
});
</script>

<?php include 'inc/footer.php'; ?>