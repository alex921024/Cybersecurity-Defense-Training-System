<?php
// api/get_users.php
require_once 'common.php';
require_once 'db_connect.php';

requireGet();
$session = requireAuth(['admin', 'teacher']);

$role = $session['role'];
$user_id = $session['user_id'];

try {
    if ($role === 'admin') {
        // 管理員：撈取所有非管理員的帳號
        $stmt = $pdo->prepare("SELECT user_id, username, role, teacher_id, is_approved, created_at FROM users WHERE role != 'admin' ORDER BY role DESC, created_at DESC");
        $stmt->execute();
    } else if ($role === 'teacher') {
        // 教師：撈取所有學生（包含尚未審核或已綁定在自己名下的學生）
        $stmt = $pdo->prepare("SELECT user_id, username, role, teacher_id, is_approved, created_at FROM users WHERE role = 'student' AND (teacher_id IS NULL OR teacher_id = ?) ORDER BY is_approved ASC, created_at DESC");
        $stmt->execute([$user_id]);
    }
    
    $users = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success", 
        "data" => $users, 
        "current_user_id" => $user_id,
        "current_role" => $role
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取錯誤"]);
}
?>