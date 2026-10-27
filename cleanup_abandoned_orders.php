<?php
/**
 * cleanup_abandoned_orders.php
 *
 * این اسکریپت برای شناسایی و لغو سفارشاتی طراحی شده است که
 * در وضعیت "در انتظار پرداخت" (status_id = 1) قرار دارند
 * و مدت زمان مشخصی (مثلاً 30 دقیقه) از زمان ایجاد آنها گذشته است.
 * این اسکریپت باید به صورت دوره‌ای توسط یک Cron Job اجرا شود.
 */

require_once 'inc/config.php';

// فرض می‌کنیم:
// status_id = 1 : در انتظار پرداخت
// status_id = 5 : لغو شده

// مدت زمان (بر حسب دقیقه) که پس از آن یک سفارش در انتظار پرداخت یا پیش‌نویس، رها شده تلقی می‌شود.
$abandonment_threshold_minutes = 60; // افزایش به ۶۰ دقیقه مطابق با انقضای سبد خرید

// محاسبه زمان آستانه
$threshold_time = date('Y-m-d H:i:s', strtotime("-{$abandonment_threshold_minutes} minutes"));

// شروع تراکنش برای اطمینان از یکپارچگی داده‌ها
$conn->begin_transaction();

try {
    // 1. پیدا کردن سفارشات رها شده (در انتظار پرداخت یا پیش‌نویس)
    // status_id = 1 (پیش‌نویس) و status_id = 8 (در انتظار پرداخت)
    $stmt_find_abandoned = $conn->prepare("
        SELECT id FROM orders
        WHERE status_id IN (1, 8) AND (created_at < ? OR updated_at < ?)
    ");
    $stmt_find_abandoned->bind_param("ss", $threshold_time, $threshold_time);
    $stmt_find_abandoned->execute();
    $abandoned_orders_result = $stmt_find_abandoned->get_result();

    // 2. به‌روزرسانی وضعیت این سفارشات به "لغو شده" (status_id = 5)
    if ($abandoned_orders_result->num_rows > 0) {
        $abandoned_order_ids = [];
        while ($row = $abandoned_orders_result->fetch_assoc()) {
            $abandoned_order_ids[] = $row['id'];
        }
        $placeholders = implode(',', array_fill(0, count($abandoned_order_ids), '?'));
        $types = str_repeat('i', count($abandoned_order_ids));

        // (جدید) بازگرداندن موجودی انبار برای سفارشات لغو شده
        $stmt_items_restore = $conn->prepare("
            SELECT oi.offer_id, oi.quantity
            FROM order_items oi
            WHERE oi.order_id IN ($placeholders)
        ");
        $stmt_items_restore->bind_param($types, ...$abandoned_order_ids);
        $stmt_items_restore->execute();
        $items_to_restore = $stmt_items_restore->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt_stock_restore = $conn->prepare("UPDATE product_offers SET stock = stock + ? WHERE id = ?");
        foreach ($items_to_restore as $item) {
            if (!empty($item['offer_id']) && !empty($item['quantity'])) {
                $stmt_stock_restore->bind_param("ii", $item['quantity'], $item['offer_id']);
                $stmt_stock_restore->execute();
            }
        }

        // (اصلاح) تغییر وضعیت به 6 (لغو شده) به جای 5 (تحویل شده)
        $stmt_update_status = $conn->prepare("UPDATE orders SET status_id = 6 WHERE id IN ($placeholders)");
        $stmt_update_status->bind_param($types, ...$abandoned_order_ids);
        $stmt_update_status->execute();
    }

    $conn->commit();
    echo "اسکریپت پاکسازی سفارشات رها شده با موفقیت اجرا شد. " . $abandoned_orders_result->num_rows . " سفارش لغو شد.\n";

} catch (Exception $e) {
    $conn->rollback();
    error_log("خطا در اسکریپت پاکسازی سفارشات رها شده: " . $e->getMessage());
    echo "خطا در اجرای اسکریپت: " . $e->getMessage() . "\n";
}

$conn->close();
?>