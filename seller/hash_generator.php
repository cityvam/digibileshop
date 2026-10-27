<?php

// // این یک ابزار مدیریتی است، فقط سوپر ادمین باید به آن دسترسی داشته باشد.
// // از یک دسترسی سطح بالا مثل 'manage_roles' استفاده می‌کنیم.
// require_permission('manage_roles', $pdo);

$original_string = '';
$hashed_string = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_string = $_POST['string_to_hash'] ?? '';

    if (!empty($original_string)) {
        // ساخت هش با استفاده از الگوریتم پیش‌فرض و امن PHP
        $hashed_string = password_hash($original_string, PASSWORD_DEFAULT);
    }
}
?>

<h1>ابزار ساخت هش (Hash Generator)</h1>
<p>از این ابزار برای ساخت هش امن برای رمزهای عبور استفاده کنید. مقدار تولید شده را می‌توانید مستقیماً در ستون `password` جدول `users` کپی کنید.</p>

<form action="hash_generator.php" method="POST" style="margin-bottom: 20px;">
    <div style="margin-bottom: 10px;">
        <label for="string_to_hash">رشته مورد نظر برای هش (مثلاً رمز عبور):</label>
        <input type="text" id="string_to_hash" name="string_to_hash" value="<?php echo htmlspecialchars($original_string); ?>" required style="width: 100%; padding: 8px; margin-top: 5px; direction: ltr;">
    </div>
    <button type="submit" style="padding: 10px 20px;">ساخت هش</button>
</form>

<?php if ($hashed_string): ?>
    <div style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; border-radius: 5px;">
        <h3>نتیجه:</h3>
        <p><strong>هش تولید شده:</strong></p>
        <textarea readonly style="width: 100%; height: 80px; direction: ltr; text-align: left; padding: 10px; margin-top: 5px; background-color: #eee; border: 1px solid #ccc; resize: vertical;"><?php echo htmlspecialchars($hashed_string); ?></textarea>
        <p style="font-size: 12px; color: #777;">این مقدار را کپی و در دیتابیس استفاده کنید.</p>
    </div>
<?php endif; ?>

<?php require_once 'inc/footer.php'; ?>