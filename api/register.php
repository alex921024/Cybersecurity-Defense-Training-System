<?php
// api/register.php
require_once 'common.php';
require_once 'db_connect.php';

requirePost();

// 接收前端透過 Fetch 傳遞過來的 JSON 資料
$data = getJsonInput();

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// 1. 後端防呆與雙重密碼驗證 (深度防禦)
if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "帳號與密碼不得為空！"]);
    exit;
}

if (!validateUsername($username)) {
    echo json_encode(["status" => "error", "message" => "帳號格式不正確，請使用 4-20 字元的英數或底線"]);
    exit;
}

if (!validatePassword($password)) {
    echo json_encode(["status" => "error", "message" => "密碼長度至少 8 個字元！"]);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "兩次輸入的密碼不一致！(伺服器退回)"]);
    exit;
}

try {
    // 2. 檢查帳號是否已被註冊
    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $check_stmt->execute([$username]);
    
    if ($check_stmt->rowCount() > 0) {
        echo json_encode(["status" => "error", "message" => "此帳號已存在，請使用其他學號/員編！"]);
        exit;
    }

    // 3. 執行業界最高標準的 Argon2id 密碼雜湊
    $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

    // 4. 將新帳號寫入資料庫 (預設 role 為 student)
    $insert_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'student')");
    $insert_stmt->execute([$username, $hashed_password]);
    
    // 取得剛寫入的使用者 ID
    $new_user_id = $pdo->lastInsertId();

    // 5. 記錄操作稽核日誌 (寫入 IP 與設備資訊)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $details = json_encode(["message" => "新學生帳號註冊成功"]);
    
    $log_stmt = $pdo->prepare("INSERT INTO account_operation_logs (operator_id, action_type, target_username, ip_address, device_info, details) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->execute([
        $new_user_id,         // 操作者是剛註冊的自己
        'REGISTER_USER',      // 操作類型
        $username,            // 目標帳號
        $ip_address,          // 來源 IP
        $device_info,         // 設備資訊
        $details              // 詳細 JSON 記錄
    ]);

    // 6. 回傳成功訊息
    echo json_encode(["status" => "success", "message" => "帳號建立成功！請切換至登入頁面進行身分驗證。"]);

} catch (PDOException $e) {
    // 實務上不建議把 $e->getMessage() 直接拋給前端，但開發階段可以打開方便除錯
    echo json_encode(["status" => "error", "message" => "系統發生錯誤，請聯絡管理員！"]);
}
?>