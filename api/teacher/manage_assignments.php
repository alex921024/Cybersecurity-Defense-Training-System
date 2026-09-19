<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

$session = requireAuth('teacher');
$teacherId = (int) $session['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function assignmentError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($method === 'GET') {
        $difficultyStmt = $pdo->prepare(
            'SELECT diff_id, name, description, is_active
             FROM difficulty_configs
             WHERE owner_user_id = ?
             ORDER BY name ASC'
        );
        $difficultyStmt->execute([$teacherId]);
        $difficulties = $difficultyStmt->fetchAll();

        $studentStmt = $pdo->prepare(
            'SELECT u.user_id, u.username, u.is_approved,
                    GROUP_CONCAT(da.difficulty_id ORDER BY da.difficulty_id) AS assigned_difficulty_ids
             FROM users u
             LEFT JOIN difficulty_assignments da
               ON da.student_id = u.user_id AND da.assigned_by = ?
             WHERE u.role = \'student\' AND u.teacher_id = ?
             GROUP BY u.user_id, u.username, u.is_approved
             ORDER BY u.is_approved ASC, u.username ASC'
        );
        $studentStmt->execute([$teacherId, $teacherId]);
        $students = array_map(static function ($student) {
            $ids = $student['assigned_difficulty_ids'] === null || $student['assigned_difficulty_ids'] === ''
                ? []
                : array_map('intval', explode(',', $student['assigned_difficulty_ids']));
            return [
                'user_id' => (int) $student['user_id'],
                'username' => $student['username'],
                'is_approved' => (int) $student['is_approved'],
                'difficulty_ids' => $ids
            ];
        }, $studentStmt->fetchAll());

        echo json_encode([
            'status' => 'success',
            'difficulties' => $difficulties,
            'students' => $students
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($method, ['POST', 'DELETE'], true)) {
        assignmentError('不支援的請求方法', 405);
    }

    $data = getJsonInput();
    $difficultyId = (int) ($data['difficulty_id'] ?? $_GET['difficulty_id'] ?? 0);
    $studentId = (int) ($data['student_id'] ?? $_GET['student_id'] ?? 0);
    if ($difficultyId < 1 || $studentId < 1) {
        assignmentError('缺少難度或學生參數');
    }

    $ownershipStmt = $pdo->prepare(
        'SELECT d.diff_id
         FROM difficulty_configs d
         JOIN users u ON u.user_id = ? AND u.role = \'student\' AND u.teacher_id = ? AND u.is_approved = 1
         WHERE d.diff_id = ? AND d.owner_user_id = ? AND d.is_active = 1'
    );
    $ownershipStmt->execute([$studentId, $teacherId, $difficultyId, $teacherId]);
    if (!$ownershipStmt->fetch()) {
        assignmentError('只能分配自己的啟用難度給已核准的名下學生', 403);
    }

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO difficulty_assignments (difficulty_id, student_id, assigned_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)'
        );
        $stmt->execute([$difficultyId, $studentId, $teacherId]);
        echo json_encode(['status' => 'success', 'message' => '難度已分配給學生'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM difficulty_assignments
         WHERE difficulty_id = ? AND student_id = ? AND assigned_by = ?'
    );
    $stmt->execute([$difficultyId, $studentId, $teacherId]);
    echo json_encode(['status' => 'success', 'message' => '難度分配已解除'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '難度分配資料庫操作失敗'], JSON_UNESCAPED_UNICODE);
}
?>
