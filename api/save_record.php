<?php
// api/save_record.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// 1. 安全攔截：確認玩家是否已登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "尚未登入，無法儲存紀錄"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. 接收前端傳送的 JSON 結算資料
$data = json_decode(file_get_contents("php://input"), true);

$difficulty = $data['difficulty'] ?? 0;
$survival_time = $data['survival_time'] ?? 0;
$final_score = $data['final_score'] ?? 0;
$end_reason = $data['end_reason'] ?? 'UNKNOWN';
// 將操作日誌陣列轉為 JSON 字串以便存入資料庫
$action_logs = isset($data['action_logs']) ? json_encode($data['action_logs'], JSON_UNESCAPED_UNICODE) : '[]';

try {
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