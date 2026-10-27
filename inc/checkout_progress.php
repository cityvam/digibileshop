<?php
// این فایل کامپوننت نوار پیشرفت مراحل تسویه حساب را نمایش می‌دهد.
// متغیر $current_step باید قبل از include کردن این فایل تعریف شده باشد.
// مقادیر معتبر برای $current_step: 1 (سبد خرید), 2 (تکمیل اطلاعات), 3 (پرداخت)

$steps = [
    1 => ['icon' => 'fa-shopping-cart', 'title' => 'سبد خرید', 'url' => 'cart.php'],
    2 => ['icon' => 'fa-map-marker-alt', 'title' => 'زمان و نحوه ارسال', 'url' => 'checkout.php'],
    3 => ['icon' => 'fa-credit-card', 'title' => 'پرداخت', 'url' => '#'] // در حال حاضر صفحه‌ای برای پرداخت نداریم
];

if (!isset($current_step) || !array_key_exists($current_step, $steps)) {
    $current_step = 1; // مقدار پیش‌فرض
}
?>

<div class="checkout-progress-bar my-4">
    <?php foreach ($steps as $step_num => $step_data): ?>
        <?php
            $is_completed = ($step_num < $current_step);
            $is_active = ($step_num == $current_step);
            $is_future = ($step_num > $current_step);
            
            $class = '';
            if ($is_active) $class = 'active';
            if ($is_completed) $class = 'completed';
        ?>
        <div class="progress-step <?php echo $class; ?>">
            <a href="<?php echo $is_future ? '#' : $step_data['url']; ?>" class="progress-icon-wrapper <?php echo $is_future ? 'disabled' : ''; ?>">
                <div class="progress-icon">
                    <i class="fa <?php echo $step_data['icon']; ?>"></i>
                </div>
            </a>
            <div class="progress-label"><?php echo $step_data['title']; ?></div>
        </div>

        <?php if ($step_num < count($steps)): // نمایش خط اتصال بین مراحل ?>
            <div class="progress-connector <?php echo $is_completed ? 'completed' : ''; ?>"></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>