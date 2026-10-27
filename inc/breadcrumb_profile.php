<?php
// inc/breadcrumb_profile.php

// این فایل به متغیر $active_page نیاز دارد که باید در صفحه والد تعریف شده باشد.

$page_titles = [
    'index' => 'خلاصه فعالیت‌ها',
    'profile' => 'اطلاعات حساب',
    'my_orders' => 'سفارش‌های من',
    'addresses' => 'مدیریت آدرس‌ها',
    'wishlist' => 'لیست علاقه‌مندی‌ها',
    'wallet' => 'کیف پول شما',
    'order_details' => 'جزئیات سفارش',
    'payments' => 'تاریخچه پرداخت‌ها',
    'qna_reviews' => 'دیدگاه‌ها و پرسش‌ها'
];

$current_page_title = $page_titles[$active_page] ?? 'حساب کاربری';
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small bg-light p-2 rounded-3">
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>index.php" class="text-decoration-none">فروشگاه</a></li>
        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>profile/" class="text-decoration-none">حساب کاربری</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($current_page_title); ?></li>
    </ol>
</nav>