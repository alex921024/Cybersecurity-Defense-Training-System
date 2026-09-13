<?php
// api/login.php
require_once 'common.php';
require_once 'db_connect.php';

requirePost();
session_start();

// 2. 取得前端傳入的 JSON 數據
$data = getJsonInput();
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "帳號與密碼不得為空"]);
    exit;
}

if (!validateUsername($username)) {
    echo json_encode(["status" => "error", "message" => "帳號格式不正確，請輸入英文、數字或底線"]);
    exit;
}

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "帳號與密碼不得為空"]);
    exit;
}

try {
    // 3. 查詢使用者資料
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 4. 驗證帳號是否存在以及密碼是否正確 (使用 Argon2id 雜湊驗證)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // 紀錄失敗嘗試的稽核日誌 (選填，可依需求擴充)
        echo json_encode(["status" => "error", "message" => "帳號或密碼錯誤"]);
        exit;
    }

    // 5. 建立安全的 Session
    session_regenerate_id(true); // 防止 Session Fixation 攻擊
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // 6. 收集登入稽核資訊 (IP 與 設備)
    $operator_id = $user['user_id'];
    $action_type = 'LOGIN_SUCCESS';
    $target_username = $user['username'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $details = json_encode(["login_time" => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);

    // 7. 寫入 account_operation_logs 稽核紀錄表
    $log_stmt = $pdo->prepare("INSERT INTO account_operation_logs (operator_id, action_type, target_username, ip_address, device_info, details) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->execute([$operator_id, $action_type, $target_username, $ip_address, $device_info, $details]);

    // 8. 依據身分決定導向網址 (分流機制)
    $redirect_url = '';
    if ($user['role'] === 'student') {
        $redirect_url = 'student_dashboard.html';
    } else {
        // 教師 (teacher) 或管理員 (admin) 導向後台
        $redirect_url = 'dashboard.html';
    }

    echo json_encode([
        "status" => "success",
        "message" => "登入成功",
        "redirect" => $redirect_url
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "系統發生錯誤，請稍後再試"]);
}
?>