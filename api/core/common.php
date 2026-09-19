<?php
// api/common.php
header('Content-Type: application/json; charset=utf-8');

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_path', '/');
// Lax allows the top-level GET callback from Google OAuth to keep the session.
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "不合法的請求方法"]);
        exit;
    }
}

function requireGet() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(["status" => "error", "message" => "不合法的請求方法"]);
        exit;
    }
}

function getJsonInput() {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["status" => "error", "message" => "無效的 JSON 請求"]);
        exit;
    }
    return $data;
}

function requireAuth($roles = null) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "尚未登入"]);
        exit;
    }

    if ($roles !== null) {
        if (is_array($roles)) {
            if (!in_array($_SESSION['role'], $roles, true)) {
                echo json_encode(["status" => "error", "message" => "權限不足"]);
                exit;
            }
        } else {
            if ($_SESSION['role'] !== $roles) {
                echo json_encode(["status" => "error", "message" => "權限不足"]);
                exit;
            }
        }
    }

    return $_SESSION;
}

function writeAuditLog(PDO $pdo, array $session, string $actionType, ?string $targetUsername = null, array $details = []) {
    $stmt = $pdo->prepare(
        'INSERT INTO account_operation_logs
            (operator_id, action_type, target_username, ip_address, device_info, details)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $session['user_id'],
        $actionType,
        $targetUsername,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode($details, JSON_UNESCAPED_UNICODE)
    ]);
}

function validateUsername($username) {
    return preg_match('/^[A-Za-z0-9_-]{4,20}$/', $username) === 1;
}

function validatePassword($password) {
    return mb_strlen($password) >= 8;
}
