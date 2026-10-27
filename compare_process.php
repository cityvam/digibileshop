<?php
require_once 'inc/config.php';
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$action = $_POST['action'] ?? '';
$product_id = isset($_POST['product_id']) ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT) : null;

switch ($action) {
    case 'toggle':
        if (!$product_id) {
            echo json_encode(['status' => 'error', 'message' => 'شناسه محصول نامعتبر است.']);
            exit();
        }

        if (in_array($product_id, $_SESSION['compare_list'])) {
            // حذف محصول از لیست
            $_SESSION['compare_list'] = array_diff($_SESSION['compare_list'], [$product_id]);
            $result_action = 'removed';
        } else {
            // افزودن محصول به لیست (با محدودیت ۴ محصول)
            if (count($_SESSION['compare_list']) >= 4) {
                echo json_encode(['status' => 'error', 'message' => 'شما می‌توانید حداکثر ۴ محصول را برای مقایسه انتخاب کنید.']);
                exit();
            }
            $_SESSION['compare_list'][] = $product_id;
            $result_action = 'added';
        }
        break;

    case 'clear':
        $_SESSION['compare_list'] = [];
        $result_action = 'cleared';
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر است.']);
        exit();
}

// واکشی اطلاعات محصولات موجود در لیست مقایسه برای ارسال به کلاینت
$compare_items_details = [];
if (!empty($_SESSION['compare_list'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['compare_list']), '?'));
    $types = str_repeat('i', count($_SESSION['compare_list']));
    $stmt = $conn->prepare("SELECT id, name, image FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$_SESSION['compare_list']);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $compare_items_details[] = $row;
    }
}

echo json_encode([
    'status' => 'success',
    'action' => $result_action,
    'compare_list' => $compare_items_details
]);
exit();