<?php
// api/get_my_records.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// 安全檢查：必須登入且身分為 student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(["status" => "error", "message" => "權限不足"]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 撈取該學生的所有遊戲紀錄，由新到舊排序
    $stmt = $pdo->prepare("SELECT * FROM game_records WHERE user_id = ? ORDER BY played_at DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "data" => $records,
        "username" => $_SESSION['username']
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取失敗"]);
}
?>