<?php
// api/apply_teacher.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(["status" => "error", "message" => "權限不足"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$teacher_username = trim($data['teacher_username'] ?? '');
$student_id = $_SESSION['user_id'];

if (empty($teacher_username)) {
    echo json_encode(["status" => "error", "message" => "請輸入教師帳號"]);
    exit;
}

try {
    // 1. 尋找該教師是否存在
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND role = 'teacher'");
    $stmt->execute([$teacher_username]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        echo json_encode(["status" => "error", "message" => "找不到此教師帳號"]);
        exit;
    }

    $teacher_id = $teacher['user_id'];

    // 2. 更新學生的 teacher_id 並設定為未審核 (is_approved = 0)
    $update_stmt = $pdo->prepare("UPDATE users SET teacher_id = ?, is_approved = 0 WHERE user_id = ?");
    $update_stmt->execute([$teacher_id, $student_id]);

    echo json_encode(["status" => "success", "message" => "已成功送出申請，請等待教師審核"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫錯誤"]);
}
?>