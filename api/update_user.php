<?php
// api/update_user.php
require_once 'common.php';
require_once 'db_connect.php';

requirePost();
$session = requireAuth(['admin', 'teacher']);

$data = getJsonInput();
$action = $data['action'] ?? '';
$target_user_id = intval($data['user_id'] ?? 0);

$my_role = $session['role'];
$my_id = $session['user_id'];

if (empty($action) || empty($target_user_id)) {
    echo json_encode(["status" => "error", "message" => "參數遺失"]);
    exit;
}

try {
    // 動作 A：管理員將學生升級為教師
    if ($action === 'upgrade_to_teacher' && $my_role === 'admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'teacher', teacher_id = NULL WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$target_user_id]);
        echo json_encode(["status" => "success", "message" => "已成功將該帳號升級為教師！"]);
    }
    // 動作 B：教師將學生加入自己的管理範圍
    else if ($action === 'bind_student' && $my_role === 'teacher') {
        $stmt = $pdo->prepare("UPDATE users SET teacher_id = ? WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$my_id, $target_user_id]);
        echo json_encode(["status" => "success", "message" => "已將該學生加入您的管理範圍。"]);
    }
    // 動作 C：教師將學生移出自己的管理範圍
    else if ($action === 'unbind_student' && $my_role === 'teacher') {
        $stmt = $pdo->prepare("UPDATE users SET teacher_id = NULL WHERE user_id = ? AND teacher_id = ? AND role = 'student'");
        $stmt->execute([$target_user_id, $my_id]);
        echo json_encode(["status" => "success", "message" => "已將該學生移出您的管理範圍。"]);
    }
    else {
        echo json_encode(["status" => "error", "message" => "無效的操作，或您沒有權限執行此動作。"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫更新錯誤"]);
}
?>