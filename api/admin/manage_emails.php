<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

$session = requireAuth('admin');
$method = $_SERVER['REQUEST_METHOD'];

function emailAdminError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!in_array($method, ['POST', 'DELETE'], true)) {
        emailAdminError('不支援的請求方法', 405);
    }

    $data = getJsonInput();
    $emailId = (int) ($data['email_id'] ?? 0);

    if ($method === 'DELETE') {
        if ($emailId < 1) {
            emailAdminError('郵件 ID 無效');
        }
        $findStmt = $pdo->prepare('SELECT sender, subject FROM phishing_emails WHERE email_id = ?');
        $findStmt->execute([$emailId]);
        $email = $findStmt->fetch();
        if (!$email) {
            emailAdminError('找不到指定郵件', 404);
        }
        $deleteStmt = $pdo->prepare('DELETE FROM phishing_emails WHERE email_id = ?');
        $deleteStmt->execute([$emailId]);
        writeAuditLog($pdo, $session, 'delete_training_email', null, [
            'email_id' => $emailId,
            'sender' => $email['sender'],
            'subject' => $email['subject']
        ]);
        echo json_encode(['status' => 'success', 'message' => '郵件題目已刪除'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sender = trim((string) ($data['sender'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));
    $isMalicious = $data['is_malicious'] ?? null;
    if ($sender === '' || mb_strlen($sender) > 100 || $subject === '' || mb_strlen($subject) > 255 || $content === '' || mb_strlen($content) > 10000 || !is_bool($isMalicious)) {
        emailAdminError('郵件欄位格式無效');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO phishing_emails (sender, subject, content, is_malicious)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$sender, $subject, $content, $isMalicious ? 1 : 0]);
    writeAuditLog($pdo, $session, 'create_training_email', null, [
        'email_id' => (int) $pdo->lastInsertId(),
        'sender' => $sender,
        'subject' => $subject,
        'is_malicious' => $isMalicious
    ]);
    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => '郵件題目已新增'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '郵件題庫操作失敗'], JSON_UNESCAPED_UNICODE);
}
?>
