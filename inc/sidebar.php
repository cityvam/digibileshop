<?php
// inc/sidebar.php
?>
<div class="col-md-3">
    <div class="card">
        <div class="card-header">
            دسته‌بندی‌ها
        </div>
        <div class="list-group list-group-flush">
            <?php
            // خواندن دسته‌بندی‌های اصلی از دیتابیس
            $sql = "SELECT id, name FROM product_categories WHERE parent_id IS NULL ORDER BY name";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($category = $result->fetch_assoc()) {
                    // برای هر دسته یک لینک ایجاد می‌کنیم که به صفحه محصولات با فیلتر آن دسته می‌رود
                    echo '<a href="products/?category_id=' . $category['id'] . '" class="list-group-item list-group-item-action">';
                    echo htmlspecialchars($category['name']);
                    echo '</a>';
                }
            } else {
                echo '<p class="p-3">دسته‌بندی یافت نشد.</p>';
            }
            ?>
        </div>
    </div>
</div>
