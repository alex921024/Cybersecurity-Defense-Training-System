<?php
// api/approve_student.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(["status" => "error", "message" => "權限不足"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$student_id = $data['student_id'] ?? '';
$action = $data['action'] ?? ''; // 'approve' 或 'reject'
$teacher_id = $_SESSION['user_id'];

if (empty($student_id) || empty($action)) {
    echo json_encode(["status" => "error", "message" => "參數錯誤"]);
    exit;
}

try {
    // 確保這個學生確實是向這位老師提出申請
    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND teacher_id = ?");
    $check_stmt->execute([$student_id, $teacher_id]);
    if (!$check_stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "找不到此學生的申請紀錄"]);
        exit;
    }

    if ($action === 'approve') {
        // 同意申請：將 is_approved 設為 1
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE user_id = ?");
        $stmt->execute([$student_id]);
        echo json_encode(["status" => "success", "message" => "已同意該學生的加入申請"]);
    } else if ($action === 'reject') {
        // 拒絕申請：將 teacher_id 設為 NULL，is_approved 設為 1
        $stmt = $pdo->prepare("UPDATE users SET teacher_id = NULL, is_approved = 1 WHERE user_id = ?");
        $stmt->execute([$student_id]);
        echo json_encode(["status" => "success", "message" => "已拒絕該學生的申請"]);
    } else {
        echo json_encode(["status" => "error", "message" => "無效的操作"]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫錯誤"]);
}
?>