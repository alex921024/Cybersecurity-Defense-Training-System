<?php
// api/get_my_records.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
$session = requireAuth('student');

$user_id = $session['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT r.*, d.name AS difficulty_name
         FROM game_records r
         LEFT JOIN difficulty_configs d ON d.diff_id = r.difficulty
         WHERE r.user_id = ?
         ORDER BY r.played_at DESC"
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
                                 JOIN users u ON u.user_id = ? AND u.role = 'student'
                                                            AND u.is_approved = 1
                 WHERE d.is_active = 1
                     AND (d.owner_user_id IS NOT NULL AND d.owner_user_id = u.teacher_id)
         ORDER BY d.name ASC"
    );
                $difficultyStmt->execute([$user_id]);
    $teacherDifficulties = $difficultyStmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "data" => $records,
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