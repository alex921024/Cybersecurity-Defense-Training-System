<?php
// api/admin/manage_threats.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php'; // 注意路徑：因為在 admin 子資料夾內，需退回上一層

// 1. 安全攔檢查：必須登入且身分為 admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "權限不足，僅限管理員操作"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // 2. 處理 GET 請求：取得所有惡意 IP 題庫
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT ip_id, ip_address, attack_type, payload_desc, is_active, created_at FROM threat_ips ORDER BY ip_id DESC");
        $stmt->execute();
        $threats = $stmt->fetchAll();

        echo json_encode([
            "status" => "success",
            "data" => $threats
        ]);
        exit;
    }

    // 3. 處理 POST 請求：新增一筆惡意 IP 題庫
    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $ip_address = trim($data['ip_address'] ?? '');
        $attack_type = strtolower(trim($data['attack_type'] ?? 'syn'));
        $payload_desc = trim($data['payload_desc'] ?? 'Custom Attack Payload');

        if (empty($ip_address)) {
            echo json_encode(["status" => "error", "message" => "IP 位址不得為空"]);
            exit;
        }

        // 寫入資料庫
        $stmt = $pdo->prepare("INSERT INTO threat_ips (ip_address, attack_type, payload_desc, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$ip_address, $attack_type, $payload_desc]);

        echo json_encode([
            "status" => "success",
            "message" => "成功新增惡意 IP 題庫！"
        ]);
        exit;
    }

    echo json_encode(["status" => "error", "message" => "不支援的請求方法"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫操作失敗: " . $e->getMessage()]);
}
?>