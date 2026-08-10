<?php
// api/get_records.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// 安全攔截：未登入或學生無權存取
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'student') {
    echo json_encode(["status" => "error", "message" => "權限不足"]);
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// 檢查前端是否有傳入特定的學生 ID
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

try {
    if ($role === 'admin') {
        if ($student_id) {
            // 管理員：看特定學生
            $sql = "SELECT r.*, u.username FROM game_records r JOIN users u ON r.user_id = u.user_id WHERE r.user_id = ? ORDER BY r.played_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id]);
        } else {
            // 管理員：看所有人
            $sql = "SELECT r.*, u.username FROM game_records r JOIN users u ON r.user_id = u.user_id ORDER BY r.played_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
    } else if ($role === 'teacher') {
        if ($student_id) {
            // 教師：看自己名下的特定學生 (雙重驗證 teacher_id 保障安全)
            $sql = "SELECT r.*, u.username FROM game_records r JOIN users u ON r.user_id = u.user_id WHERE u.teacher_id = ? AND r.user_id = ? ORDER BY r.played_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $student_id]);
        } else {
            // 教師：看自己名下的所有人
            $sql = "SELECT r.*, u.username FROM game_records r JOIN users u ON r.user_id = u.user_id WHERE u.teacher_id = ? ORDER BY r.played_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
        }
    }
    
    $records = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success", 
        "data" => $records
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取錯誤"]);
}
?>