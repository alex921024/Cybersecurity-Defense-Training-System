<?php
// api/get_my_records.php
require_once 'common.php';
require_once 'db_connect.php';

requireGet();
$session = requireAuth('student');

$user_id = $session['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM game_records WHERE user_id = ? ORDER BY played_at DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "data" => $records,
        "username" => $session['username']
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取失敗"]);
}
?>