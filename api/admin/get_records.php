<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
$session = requireAuth(['admin', 'teacher']);
$role = $session['role'];
$userId = (int) $session['user_id'];
$studentId = null;

if (isset($_GET['student_id'])) {
    $studentId = filter_var($_GET['student_id'], FILTER_VALIDATE_INT);
    if ($studentId === false || $studentId < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '學生 ID 無效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$recordId = null;
if (isset($_GET['record_id'])) {
    $recordId = filter_var($_GET['record_id'], FILTER_VALIDATE_INT);
    if ($recordId === false || $recordId < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '紀錄 ID 無效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$page = min(1000000, max(1, (int) ($_GET['page'] ?? 1)));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;
$conditions = [];
$params = [];

if ($role === 'teacher') {
    $conditions[] = 'u.teacher_id = ?';
    $params[] = $userId;
}
if ($studentId !== null) {
    $conditions[] = 'r.user_id = ?';
    $params[] = $studentId;
}
$where = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';

try {
    if ($recordId !== null) {
        $stmt = $pdo->prepare(
            "SELECT r.record_id, r.user_id, r.difficulty, r.survival_time,
                    r.final_score, r.end_reason, r.action_logs, r.played_at,
                    u.username
             FROM game_records r
             JOIN users u ON u.user_id = r.user_id
             WHERE r.record_id = ?{$where}"
        );
        $stmt->execute(array_merge([$recordId], $params));
        $record = $stmt->fetch();
        if (!$record) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => '找不到訓練紀錄'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['status' => 'success', 'data' => [$record]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM game_records r
         JOIN users u ON u.user_id = r.user_id
         WHERE 1 = 1{$where}"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pages = $total > 0 ? (int) ceil($total / $limit) : 0;
    $page = $pages > 0 ? min($page, $pages) : 1;
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT r.record_id, r.user_id, r.difficulty, r.survival_time,
                r.final_score, r.end_reason, r.played_at, u.username
         FROM game_records r
         JOIN users u ON u.user_id = r.user_id
         WHERE 1 = 1{$where}
         ORDER BY r.played_at DESC, r.record_id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $pages
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '資料庫讀取錯誤'], JSON_UNESCAPED_UNICODE);
}
?>
