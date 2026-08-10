<?php
// api/check_auth.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 檢查是否已登入 (驗證 Session 中是否有 user_id)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "unauthorized", "message" => "請先登入系統。"]);
    exit;
}

// 若已登入，回傳使用者的基本資訊與權限
echo json_encode([
    "status" => "success",
    "user_id" => $_SESSION['user_id'],
    "username" => $_SESSION['username'],
    "role" => $_SESSION['role']
]);
?>