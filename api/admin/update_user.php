<?php
// api/update_user.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

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
    // 動作 A：管理員修改教師或學生密碼
    if ($action === 'change_password' && $my_role === 'admin') {
        $password = (string) ($data['password'] ?? '');
        $confirm_password = (string) ($data['confirm_password'] ?? '');

        if (!validatePassword($password)) {
            echo json_encode(["status" => "error", "message" => "密碼長度至少 8 個字元"]);
            exit;
        }
        if ($password !== $confirm_password) {
            echo json_encode(["status" => "error", "message" => "兩次輸入的密碼不一致"]);
            exit;
        }

        $targetStmt = $pdo->prepare("SELECT username, role FROM users WHERE user_id = ? AND role IN ('teacher', 'student')");
        $targetStmt->execute([$target_user_id]);
        $targetUser = $targetStmt->fetch();
        if (!$targetUser) {
            echo json_encode(["status" => "error", "message" => "只能修改教師或學生帳號的密碼"]);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([$passwordHash, $target_user_id]);
        writeAuditLog($pdo, $session, 'change_user_password', $targetUser['username'], [
            'target_user_id' => $target_user_id,
            'target_role' => $targetUser['role']
        ]);
        echo json_encode(["status" => "success", "message" => "已成功修改該帳號密碼"]);
    }
    // 動作 A：管理員將學生升級為教師
    else if ($action === 'upgrade_to_teacher' && $my_role === 'admin') {
        $stmt = $pdo->prepare("UPDATE users SET role = 'teacher', teacher_id = NULL WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$target_user_id]);
        writeAuditLog($pdo, $session, 'upgrade_student_to_teacher', null, ['target_user_id' => $target_user_id]);
        echo json_encode(["status" => "success", "message" => "已成功將該帳號升級為教師！"]);
    }
    // 動作 B：教師將學生加入自己的管理範圍
    else if ($action === 'bind_student' && $my_role === 'teacher') {
        $stmt = $pdo->prepare("UPDATE users SET teacher_id = ? WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$my_id, $target_user_id]);
        writeAuditLog($pdo, $session, 'bind_student', null, ['target_user_id' => $target_user_id]);
        echo json_encode(["status" => "success", "message" => "已將該學生加入您的管理範圍。"]);
    }
    // 動作 C：教師將學生移出自己的管理範圍
    else if ($action === 'unbind_student' && $my_role === 'teacher') {
        $stmt = $pdo->prepare("UPDATE users SET teacher_id = NULL WHERE user_id = ? AND teacher_id = ? AND role = 'student'");
        $stmt->execute([$target_user_id, $my_id]);
        writeAuditLog($pdo, $session, 'unbind_student', null, ['target_user_id' => $target_user_id]);
        echo json_encode(["status" => "success", "message" => "已將該學生移出您的管理範圍。"]);
    }
    else {
        echo json_encode(["status" => "error", "message" => "無效的操作，或您沒有權限執行此動作。"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫更新錯誤"]);
}
?>