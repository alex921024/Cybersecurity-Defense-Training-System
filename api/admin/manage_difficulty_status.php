<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

$session = requireAuth('admin');
$method = $_SERVER['REQUEST_METHOD'];

function adminDifficultyError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!in_array($method, ['PATCH', 'DELETE'], true)) {
        adminDifficultyError('不支援的請求方法', 405);
    }

    $data = getJsonInput();
    $difficultyId = (int) ($data['diff_id'] ?? 0);
    if ($difficultyId < 1) {
        adminDifficultyError('難度 ID 無效');
    }

    $difficultyStmt = $pdo->prepare(
        'SELECT diff_id, name, owner_user_id
         FROM difficulty_configs WHERE diff_id = ?'
    );
    $difficultyStmt->execute([$difficultyId]);
    $difficulty = $difficultyStmt->fetch();
    if (!$difficulty) {
        adminDifficultyError('找不到指定難度', 404);
    }

    if ($method === 'PATCH') {
        if (!array_key_exists('is_active', $data) || !is_bool($data['is_active'])) {
            adminDifficultyError('啟用狀態格式無效');
        }
        $stmt = $pdo->prepare('UPDATE difficulty_configs SET is_active = ? WHERE diff_id = ?');
        $stmt->execute([$data['is_active'] ? 1 : 0, $difficultyId]);
        writeAuditLog($pdo, $session, 'admin_update_difficulty_status', null, [
            'diff_id' => $difficultyId,
            'name' => $difficulty['name'],
            'is_active' => $data['is_active']
        ]);
        echo json_encode(['status' => 'success', 'message' => $data['is_active'] ? '難度已啟用' : '難度已停用'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($difficulty['owner_user_id'] === null) {
        adminDifficultyError('系統預設難度不可刪除，請改用停用', 403);
    }
    $assignmentStmt = $pdo->prepare('SELECT COUNT(*) FROM difficulty_assignments WHERE difficulty_id = ?');
    $assignmentStmt->execute([$difficultyId]);
    if ((int) $assignmentStmt->fetchColumn() > 0) {
        adminDifficultyError('此難度已分配給學生，請先解除分配後再刪除', 409);
    }

    $deleteStmt = $pdo->prepare('DELETE FROM difficulty_configs WHERE diff_id = ?');
    $deleteStmt->execute([$difficultyId]);
    writeAuditLog($pdo, $session, 'admin_delete_difficulty', null, [
        'diff_id' => $difficultyId,
        'name' => $difficulty['name']
    ]);
    echo json_encode(['status' => 'success', 'message' => '教師自訂難度已刪除'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '難度狀態操作失敗'], JSON_UNESCAPED_UNICODE);
}
?>
