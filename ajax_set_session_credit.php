<?php
require_once 'inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? null;
    $id = $_POST['id'] ?? null;

    if ($code && $id) {
        $_SESSION['applied_credit_code'] = $code;
        $_SESSION['applied_credit_id'] = (int)$id;
        echo json_encode(['status' => 'success', 'message' => 'کد در سشن ذخیره شد.']);
    } else {
        unset($_SESSION['applied_credit_code']);
        unset($_SESSION['applied_credit_id']);
        echo json_encode(['status' => 'success', 'message' => 'کد از سشن پاک شد.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'درخواست نامعتبر است.']);
}
