<?php
// inc/sidebar_cus.php

// این فایل به یک متغیر به نام $active_page نیاز دارد که باید
// قبل از include شدن این فایل، در صفحه والد تعریف شده باشد.
// مثال: $active_page = 'profile';
?>
<div class="col-md-3">
    <div class="card">
        <div class="card-header fw-bold">حساب کاربری</div>
        <div class="list-group list-group-flush">
            <a href="index.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'index') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-speedometer2 me-2"></i>خلاصه فعالیت‌ها</a>
            <a href="my_orders.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'my_orders') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-box-seam me-2"></i>سفارش‌های من</a>
            <a href="profile.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'profile') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-person-circle me-2"></i>اطلاعات حساب</a>
            <a href="wishlist.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'wishlist') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-heart me-2"></i>لیست علاقه‌مندی‌ها</a>
            <a href="addresses.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'addresses') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-geo-alt me-2"></i>آدرس‌ها</a>
            <a href="qna_reviews.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'qna_reviews') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-chat-quote me-2"></i>دیدگاه‌ها و پرسش‌ها</a>
            <a href="wallet.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'wallet') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-wallet2 me-2"></i>کیف پول</a>
            <a href="payments.php" class="list-group-item list-group-item-action <?php echo ($active_page === 'payments') ? 'active' : ''; ?>" style="padding: 15px 17px 13px 10px;"><i class="bi bi-receipt me-2"></i>پرداختی‌ها</a>
            <a href="../logout.php" class="list-group-item list-group-item-action text-danger" style="padding: 15px 17px 13px 10px;"><i class="bi bi-box-arrow-right me-2"></i>خروج از حساب</a>
        </div>
    </div>
</div>