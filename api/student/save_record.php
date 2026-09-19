<?php
// api/save_record.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requirePost();
$session = requireAuth();

$user_id = $session['user_id'];

// 2. 接收前端傳送的 JSON 結算資料
$data = getJsonInput();

$difficulty = intval($data['difficulty'] ?? 0);
$survival_time = intval($data['survival_time'] ?? 0);
$final_score = intval($data['final_score'] ?? 0);
$end_reason = trim($data['end_reason'] ?? 'UNKNOWN');
$action_logs = isset($data['action_logs']) ? json_encode($data['action_logs'], JSON_UNESCAPED_UNICODE) : '[]';

if ($difficulty < 0 || $survival_time < 0 || $final_score < 0) {
    echo json_encode(["status" => "error", "message" => "傳入資料無效"]);
    exit;
}

try {
        $difficultyStmt = $pdo->prepare(
                "SELECT d.diff_id
                 FROM difficulty_configs d
                 JOIN users u ON u.user_id = ? AND u.role = 'student' AND u.is_approved = 1
                 WHERE d.diff_id = ? AND d.is_active = 1
                     AND (d.owner_user_id IS NULL OR d.owner_user_id = u.teacher_id)"
        );
        $difficultyStmt->execute([$user_id, $difficulty]);
    if (!$difficultyStmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "無權使用此訓練難度"]);
        exit;
    }

    // 3. 寫入資料庫
    $sql = "INSERT INTO game_records (user_id, difficulty, survival_time, final_score, end_reason, action_logs) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $difficulty, $survival_time, $final_score, $end_reason, $action_logs]);

    echo json_encode(["status" => "success", "message" => "遊戲紀錄已成功存檔"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫存檔失敗"]);
}
?>