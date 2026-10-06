<?php
// api/login.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requirePost();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2. 取得前端傳入的 JSON 數據
$data = getJsonInput();
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "登入資料格式無效"], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_string($data['username'] ?? null) || !is_string($data['password'] ?? null)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "登入資料格式無效"], JSON_UNESCAPED_UNICODE);
    exit;
}
$username = trim($data['username']);
$password = $data['password'];

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "帳號與密碼不得為空"]);
    exit;
}

if (!validateUsername($username)) {
    echo json_encode(["status" => "error", "message" => "帳號格式不正確，請輸入英文、數字或底線"]);
    exit;
}

try {
    $now = time();
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // 既有資料庫尚未匯入新版 sql.txt 時自動補建節流表，避免密碼登入整體失效
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
            username VARCHAR(50) NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            window_started_at INT UNSIGNED NOT NULL,
            blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL,
            PRIMARY KEY (username, ip_hash),
            KEY idx_login_attempts_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    if (random_int(1, 100) === 1) {
        $pdo->exec('DELETE FROM login_attempts WHERE updated_at < UNIX_TIMESTAMP() - 86400');
    }
    $pdo->beginTransaction();

    $limitStmt = $pdo->prepare(
        'INSERT IGNORE INTO login_attempts
            (username, ip_hash, failed_attempts, window_started_at, blocked_until, updated_at)
         VALUES (?, ?, 0, ?, 0, ?)'
    );
    $limitStmt->execute([$username, $ipHash, $now, $now]);

    $attemptStmt = $pdo->prepare(
        'SELECT failed_attempts, window_started_at, blocked_until
         FROM login_attempts
         WHERE username = ? AND ip_hash = ?
         FOR UPDATE'
    );
    $attemptStmt->execute([$username, $ipHash]);
    $attempt = $attemptStmt->fetch();

    if ((int) $attempt['blocked_until'] > $now) {
        $pdo->rollBack();
        http_response_code(429);
        header('Retry-After: ' . ((int) $attempt['blocked_until'] - $now));
        echo json_encode(["status" => "error", "message" => "登入嘗試過多，請稍後再試"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($now - (int) $attempt['window_started_at'] >= 900) {
        $attempt['failed_attempts'] = 0;
        $attempt['window_started_at'] = $now;
        $attempt['blocked_until'] = 0;
    }

    // 3. 查詢使用者資料
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 4. 驗證帳號是否存在以及密碼是否正確 (使用 Argon2id 雜湊驗證)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $failedAttempts = (int) $attempt['failed_attempts'] + 1;
        $blockedUntil = $failedAttempts >= 5 ? $now + 900 : 0;
        $updateAttempt = $pdo->prepare(
            'UPDATE login_attempts
             SET failed_attempts = ?, window_started_at = ?, blocked_until = ?, updated_at = ?
             WHERE username = ? AND ip_hash = ?'
        );
        $updateAttempt->execute([
            $failedAttempts,
            (int) $attempt['window_started_at'],
            $blockedUntil,
            $now,
            $username,
            $ipHash
        ]);
        $pdo->commit();
        echo json_encode(["status" => "error", "message" => "帳號或密碼錯誤"]);
        exit;
    }

    $clearAttempt = $pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_hash = ?');
    $clearAttempt->execute([$username, $ipHash]);
    $pdo->commit();

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
        $redirect_url = '../../assets/html/student_dashboard.html?v=20260920';
    } else {
        // 教師 (teacher) 或管理員 (admin) 導向後台
        $redirect_url = '../../assets/html/dashboard.html';
    }

    echo json_encode([
        "status" => "success",
        "message" => "登入成功",
        "redirect" => $redirect_url
    ]);

} catch (PDOException $e) {
    error_log('Login failed: ' . $e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "系統發生錯誤，請稍後再試"]);
}
?>