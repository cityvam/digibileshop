<?php
// inc/admin_auth.php

/**
 * این فایل وظیفه بررسی و احراز هویت مدیران سایت را بر عهده دارد.
 * هر فایلی که نیاز به دسترسی سطح مدیر داشته باشد، باید این فایل را فراخوانی کند.
 */

// اطمینان از اینکه session قبل از استفاده، شروع شده است.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * بررسی می‌کند که آیا کاربر وارد شده یک مدیر (سطح 1) یا مدیر کل (سطح 2) است یا خیر.
 *
 * @return bool اگر کاربر ادمین باشد true و در غیر این صورت false برمی‌گرداند.
 */
function is_admin_logged_in() {
    return (isset($_SESSION['is_admin_logged_in']) && ($_SESSION['is_admin_logged_in'] == 1 || $_SESSION['is_admin_logged_in'] == 2));
}
?>