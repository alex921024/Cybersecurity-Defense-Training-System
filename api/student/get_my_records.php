<?php
// api/get_my_records.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
$session = requireAuth('student');

$user_id = $session['user_id'];
$page = min(1000000, max(1, (int) ($_GET['page'] ?? 1)));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM game_records WHERE user_id = ?');
    $countStmt->execute([$user_id]);
    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $limit) : 0;
    $page = $totalPages > 0 ? min($page, $totalPages) : 1;
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT r.record_id, r.difficulty, r.survival_time, r.final_score,
                r.end_reason, r.played_at, d.name AS difficulty_name
         FROM game_records r
         LEFT JOIN difficulty_configs d ON d.diff_id = r.difficulty
         WHERE r.user_id = ?
         ORDER BY r.played_at DESC, r.record_id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll();

    $profileStmt = $pdo->prepare(
        "SELECT u.teacher_id, u.is_approved, t.username AS teacher_name
         FROM users u
         LEFT JOIN users t ON t.user_id = u.teacher_id AND t.role = 'teacher'
         WHERE u.user_id = ? AND u.role = 'student'"
    );
    $profileStmt->execute([$user_id]);
    $profile = $profileStmt->fetch() ?: [];

    $difficultyStmt = $pdo->prepare(
        "SELECT d.diff_id, d.name, d.description, d.total_time
         FROM difficulty_configs d
         JOIN users u ON u.user_id = ? AND u.role = 'student' AND u.is_approved = 1
         JOIN difficulty_assignments assignment
           ON assignment.difficulty_id = d.diff_id
          AND assignment.student_id = u.user_id
          AND assignment.assigned_by = u.teacher_id
         WHERE d.is_active = 1 AND d.owner_user_id = u.teacher_id
         ORDER BY d.name ASC"
    );
    $difficultyStmt->execute([$user_id]);
    $teacherDifficulties = $difficultyStmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "data" => $records,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $totalRecords,
            "pages" => $totalPages
        ],
        "username" => $session['username'],
        "teacher_id" => isset($profile['teacher_id']) ? (int) $profile['teacher_id'] : null,
        "teacher_name" => $profile['teacher_name'] ?? null,
        "is_approved" => isset($profile['is_approved']) ? (int) $profile['is_approved'] : null,
        "teacher_difficulties" => array_map(static function ($difficulty) {
            return [
                'diff_id' => (int) $difficulty['diff_id'],
                'name' => $difficulty['name'],
                'description' => $difficulty['description'],
                'total_time' => (int) $difficulty['total_time']
            ];
        }, $teacherDifficulties)
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取失敗"]);
}
?>