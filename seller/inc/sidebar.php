<?php
$current_page = basename($_SERVER['PHP_SELF']);

// واکشی نام فروشگاه برای نمایش در هدر
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand-text">پنل فروشندگان</a>
    </div>
    <ul class="sidebar-nav">
        <li class="sidebar-nav-item">
            <a href="dashboard.php" class="sidebar-nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-grid-fill nav-icon"></i>
                <span class="nav-text">داشبورد</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="offers.php" class="sidebar-nav-link <?php echo ($current_page == 'offers.php') ? 'active' : ''; ?>">
                <i class="bi bi-tags-fill nav-icon"></i>
                <span class="nav-text">مدیریت پیشنهادها</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="orders.php" class="sidebar-nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                <i class="bi bi-basket-fill nav-icon"></i>
                <span class="nav-text">سفارش‌ها</span>
            </a>
        </li>
        <li class="sidebar-nav-item">
            <a href="profile.php" class="sidebar-nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-circle nav-icon"></i>
                <span class="nav-text">پروفایل</span>
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
         <a href="logout.php" class="sidebar-nav-link text-danger">
            <i class="bi bi-box-arrow-right nav-icon"></i>
            <span class="nav-text">خروج از حساب</span>
        </a>
    </div>
</aside>