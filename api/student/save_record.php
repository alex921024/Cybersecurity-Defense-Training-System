<?php
// api/save_record.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requirePost();
$session = requireAuth();

$user_id = $session['user_id'];

// 2. 接收前端傳送的 JSON 結算資料
$data = getJsonInput();
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "傳入資料格式無效"], JSON_UNESCAPED_UNICODE);
    exit;
}

$difficulty = filter_var($data['difficulty'] ?? null, FILTER_VALIDATE_INT);
$survivalTime = filter_var($data['survival_time'] ?? null, FILTER_VALIDATE_INT);
$endReason = $data['end_reason'] ?? null;
$actionLogs = $data['action_logs'] ?? [];

if (
    $difficulty === false || $difficulty < 0 ||
    $survivalTime === false || $survivalTime < 0 ||
    !is_string($endReason) ||
    !in_array($endReason, ['SUCCESS', 'FAILURE_RESOURCE', 'FAILURE_CRACKED', 'FAILURE_OVERLOAD'], true) ||
    !is_array($actionLogs) || !array_is_list($actionLogs) || count($actionLogs) > 500
) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "傳入資料無效"]);
    exit;
}

foreach ($actionLogs as $log) {
    if (
        !is_array($log) ||
        !isset($log['cmd'], $log['time'], $log['timer_left']) ||
        !is_string($log['cmd']) || mb_strlen($log['cmd']) > 200 ||
        !is_string($log['time']) || !preg_match('/^(?:(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d|24:00:00)$/', $log['time']) ||
        filter_var($log['timer_left'], FILTER_VALIDATE_INT) === false ||
        (int) $log['timer_left'] < 0
    ) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "操作紀錄格式無效"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$actionLogsJson = json_encode($actionLogs, JSON_UNESCAPED_UNICODE);
if ($actionLogsJson === false || strlen($actionLogsJson) > 65535) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "操作紀錄內容過大"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $difficultyStmt = $pdo->prepare(
        "SELECT d.diff_id, d.total_time
         FROM difficulty_configs d
         JOIN users u ON u.user_id = ? AND u.role = 'student' AND u.is_approved = 1
         WHERE d.diff_id = ? AND d.is_active = 1
           AND (
               d.owner_user_id IS NULL OR
               (d.owner_user_id = u.teacher_id AND EXISTS (
                   SELECT 1
                   FROM difficulty_assignments assignment
                   WHERE assignment.difficulty_id = d.diff_id
                     AND assignment.student_id = u.user_id
                     AND assignment.assigned_by = u.teacher_id
               ))
           )"
    );
    $difficultyStmt->execute([$user_id, $difficulty]);
    $difficultyConfig = $difficultyStmt->fetch();
    if (!$difficultyConfig) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "無權使用此訓練難度"]);
        exit;
    }

    $duration = (int) $difficultyConfig['total_time'];
    if (
        $survivalTime > $duration ||
        ($endReason === 'SUCCESS' && $survivalTime !== $duration) ||
        array_filter($actionLogs, static fn($log) => (int) $log['timer_left'] > $duration)
    ) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "訓練結算資料超出合理範圍"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $finalScore = ($survivalTime * 10) + ($endReason === 'SUCCESS' ? 1000 : 0);

    // 3. 寫入資料庫
    $sql = "INSERT INTO game_records (user_id, difficulty, survival_time, final_score, end_reason, action_logs) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $difficulty, $survivalTime, $finalScore, $endReason, $actionLogsJson]);

    echo json_encode([
        "status" => "success",
        "message" => "遊戲紀錄已成功存檔",
        "final_score" => $finalScore
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫存檔失敗"]);
}
?>