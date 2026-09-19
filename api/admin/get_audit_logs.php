<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
requireAuth('admin');

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;
$actionType = trim((string) ($_GET['action_type'] ?? ''));
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$conditions = [];
$params = [];

if ($actionType !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $actionType)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '操作類型格式無效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conditions[] = 'l.action_type = ?';
    $params[] = $actionType;
}

if ($keyword !== '') {
    if (mb_strlen($keyword) > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '關鍵字不可超過 100 個字元'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conditions[] = '(u.username LIKE ? OR l.target_username LIKE ? OR l.ip_address LIKE ? OR l.action_type LIKE ?)';
    $likeKeyword = '%' . $keyword . '%';
    array_push($params, $likeKeyword, $likeKeyword, $likeKeyword, $likeKeyword);
}

foreach ([['from', $from, 'l.created_at >= ?'], ['to', $to, 'l.created_at < ?']] as [$name, $value, $condition]) {
    if ($value === '') {
        continue;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "{$name} 日期格式無效"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($name === 'to') {
        $date->modify('+1 day');
    }
    $conditions[] = $condition;
    $params[] = $date->format('Y-m-d H:i:s');
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM account_operation_logs l LEFT JOIN users u ON u.user_id = l.operator_id {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "SELECT l.log_id, l.operator_id, u.username AS operator_username,
                   l.action_type, l.target_username, l.ip_address,
                   l.device_info, l.details, l.created_at
            FROM account_operation_logs l
            LEFT JOIN users u ON u.user_id = l.operator_id
            {$where}
            ORDER BY l.created_at DESC, l.log_id DESC
            LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    foreach ($logs as &$log) {
        if ($log['details'] !== null) {
            $decoded = json_decode($log['details'], true);
            $log['details'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
    }
    unset($log);

    echo json_encode([
        'status' => 'success',
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '稽核紀錄讀取失敗'], JSON_UNESCAPED_UNICODE);
}
?>
