<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

function googleFail($message) {
    header('Location: ../../login.html?google_error=' . rawurlencode($message));
    exit;
}

$config = require __DIR__ . '/google_config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$state = $_GET['state'] ?? '';
$stateParts = explode('.', $state);
$stateData = count($stateParts) === 3 ? $stateParts[0] . '.' . $stateParts[1] : '';
$expectedSignature = $stateData === '' ? '' : hash_hmac('sha256', $stateData, $config['client_secret']);
$issuedAt = (int) ($stateParts[1] ?? 0);
if (
    count($stateParts) !== 3 ||
    !hash_equals($expectedSignature, $stateParts[2] ?? '') ||
    $issuedAt < time() - 600 ||
    $issuedAt > time() + 60
) {
    googleFail('Google 驗證狀態無效，請重新嘗試');
}

if (isset($_GET['error'])) {
    googleFail('您已取消 Google 驗證');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    googleFail('Google 未回傳授權碼');
}

$tokenResponse = httpRequest('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code'
]);
$token = json_decode($tokenResponse, true);
if (!is_array($token) || empty($token['access_token'])) {
    googleFail('Google 授權交換失敗');
}

$profileResponse = httpRequest('https://openidconnect.googleapis.com/v1/userinfo', [], [
    'Authorization: Bearer ' . $token['access_token']
]);
$profile = json_decode($profileResponse, true);
$googleId = trim($profile['sub'] ?? '');
$email = strtolower(trim($profile['email'] ?? ''));
if ($googleId === '' || $email === '' || ($profile['email_verified'] ?? false) !== true) {
    googleFail('Google 帳號 email 未通過驗證');
}

try {
    $stmt = $pdo->prepare('SELECT user_id, username, role FROM users WHERE google_id = ? OR email = ? LIMIT 1');
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $baseUsername = 'google_' . substr(hash('sha256', $googleId), 0, 12);
        $username = $baseUsername;
        $suffix = 1;
        while (true) {
            $check = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
            $check->execute([$username]);
            if (!$check->fetch()) {
                break;
            }
            $username = $baseUsername . $suffix++;
        }

        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
        $insert = $pdo->prepare('INSERT INTO users (username, password_hash, google_id, email, role) VALUES (?, ?, ?, ?, "student")');
        $insert->execute([$username, $passwordHash, $googleId, $email]);
        $user = [
            'user_id' => $pdo->lastInsertId(),
            'username' => $username,
            'role' => 'student'
        ];
        $action = 'GOOGLE_REGISTER';
    } else {
        $update = $pdo->prepare('UPDATE users SET google_id = COALESCE(google_id, ?), email = COALESCE(email, ?) WHERE user_id = ?');
        $update->execute([$googleId, $email, $user['user_id']]);
        $action = 'GOOGLE_LOGIN';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    $log = $pdo->prepare('INSERT INTO account_operation_logs (operator_id, action_type, target_username, ip_address, device_info, details) VALUES (?, ?, ?, ?, ?, ?)');
    $log->execute([$user['user_id'], $action, $user['username'], $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', json_encode(['email' => $email], JSON_UNESCAPED_UNICODE)]);

    header('Location: ../../' . ($user['role'] === 'student' ? 'student_dashboard.html' : 'dashboard.html'));
    exit;
} catch (PDOException $e) {
    googleFail('Google 登入時資料庫發生錯誤');
}

function httpRequest($url, $postFields = [], $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result === false ? '' : $result;
}