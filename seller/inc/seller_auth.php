<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. بررسی اینکه آیا کاربر از طریق سشن لاگین کرده است.
if (isset($_SESSION['seller_id'])) {
    return; // کاربر لاگین است، نیازی به ادامه نیست.
}

// 2. اگر لاگین نکرده، کوکی "مرا به خاطر بسپار" را بررسی کن.
if (isset($_COOKIE['remember_me'])) {
    list($seller_id, $token) = explode(':', $_COOKIE['remember_me'], 2);

    if ($seller_id && $token) {
        $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ? AND remember_token IS NOT NULL AND remember_token_expires_at > NOW()");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($seller) {
            $token_hash = hash('sha256', $token);
            if (hash_equals($seller['remember_token'], $token_hash)) {
                // توکن معتبر است، کاربر را لاگین کن
                session_regenerate_id(true);
                $_SESSION['seller_id'] = $seller['id'];
                $_SESSION['seller_shop_name'] = $seller['shop_name'];
                return; // لاگین با موفقیت انجام شد.
            }
        }
    }

    // اگر کوکی معتبر نبود، آن را پاک کن
    setcookie('remember_me', '', time() - 3600, '/');
}

// 3. اگر کاربر نه از طریق سشن و نه از طریق کوکی لاگین نکرده بود، او را به صفحه لاگین هدایت کن.
if (!isset($_SESSION['seller_id'])) { // دوباره چک می‌کنیم
    // اگر لاگین نکرده یا فروشنده نیست، پیام خطا را در سشن ذخیره کن
    $_SESSION['error_message'] = 'برای دسترسی به این بخش، ابتدا باید به عنوان فروشنده وارد شوید.';
    // او را به صفحه لاگین هدایت کن
    header("Location: login.php");
    exit();
}
?>