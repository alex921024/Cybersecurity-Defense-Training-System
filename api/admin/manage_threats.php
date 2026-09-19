<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

$session = requireAuth('admin');
$method = $_SERVER['REQUEST_METHOD'];
$allowedAttackTypes = ['syn', 'udp', 'icmp', 'dns'];

function threatError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function writeThreatAudit(PDO $pdo, array $session, string $action, array $details)
{
    $stmt = $pdo->prepare(
        'INSERT INTO account_operation_logs
            (operator_id, action_type, target_username, ip_address, device_info, details)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $session['user_id'],
        $action,
        $session['username'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode($details, JSON_UNESCAPED_UNICODE)
    ]);
}

try {
    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT ip_id, ip_address, attack_type, payload_desc, is_active
             FROM threat_ips
             ORDER BY ip_id DESC'
        );
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($method, ['POST', 'PATCH'], true)) {
        threatError('不支援的請求方法', 405);
    }

    $data = getJsonInput();

    if ($method === 'PATCH') {
        $threatId = (int) ($data['ip_id'] ?? 0);
        $isActive = $data['is_active'] ?? null;
        if ($threatId < 1 || !is_bool($isActive)) {
            threatError('威脅狀態參數無效');
        }

        $stmt = $pdo->prepare('UPDATE threat_ips SET is_active = ? WHERE ip_id = ?');
        $stmt->execute([$isActive ? 1 : 0, $threatId]);
        if ($stmt->rowCount() === 0) {
            threatError('找不到指定的威脅資料', 404);
        }
        writeThreatAudit($pdo, $session, 'update_threat_status', [
            'ip_id' => $threatId,
            'is_active' => $isActive
        ]);
        echo json_encode(['status' => 'success', 'message' => $isActive ? '威脅已啟用' : '威脅已停用'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ipAddress = trim((string) ($data['ip_address'] ?? ''));
    $attackType = strtolower(trim((string) ($data['attack_type'] ?? '')));
    $payloadDescription = trim((string) ($data['payload_desc'] ?? ''));

    if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        threatError('IP 位址格式無效');
    }
    if (!in_array($attackType, $allowedAttackTypes, true)) {
        threatError('不支援的攻擊類型');
    }
    if ($payloadDescription === '' || mb_strlen($payloadDescription) > 2000) {
        threatError('封包特徵描述不可為空且不得超過 2000 個字元');
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT ip_id FROM threat_ips WHERE ip_address = ? AND attack_type = ? LIMIT 1'
    );
    $duplicateStmt->execute([$ipAddress, $attackType]);
    if ($duplicateStmt->fetch()) {
        threatError('相同 IP 與攻擊類型已存在於題庫', 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO threat_ips (ip_address, attack_type, payload_desc, is_active)
         VALUES (?, ?, ?, 1)'
    );
    $stmt->execute([$ipAddress, $attackType, $payloadDescription]);
    writeThreatAudit($pdo, $session, 'create_threat_ip', [
        'ip_address' => $ipAddress,
        'attack_type' => $attackType,
        'ip_id' => (int) $pdo->lastInsertId()
    ]);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => '成功新增惡意 IP 題庫'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '資料庫操作失敗'], JSON_UNESCAPED_UNICODE);
}
?>