<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
requireAuth('admin');

try {
    $threats = $pdo->query(
        'SELECT ip_id, ip_address, attack_type, payload_desc, is_active
         FROM threat_ips ORDER BY ip_id DESC'
    )->fetchAll();
    $vipIps = $pdo->query(
        'SELECT ip_id, ip_address, description
         FROM vip_ips ORDER BY ip_id DESC'
    )->fetchAll();
    $emails = $pdo->query(
        'SELECT email_id, sender, subject, content, is_malicious
         FROM phishing_emails ORDER BY email_id DESC'
    )->fetchAll();
    $difficulties = $pdo->query(
        "SELECT d.diff_id, d.name, d.description, d.owner_user_id,
                u.username AS owner_username, d.total_time, d.attack_rates,
                d.command_policy, d.attack_policy, d.game_settings,
                d.is_active, d.created_at, d.updated_at
         FROM difficulty_configs d
         LEFT JOIN users u ON u.user_id = d.owner_user_id
         ORDER BY d.owner_user_id IS NOT NULL, d.diff_id ASC"
    )->fetchAll();

    foreach ($difficulties as &$difficulty) {
        $difficulty['attack_rates'] = json_decode($difficulty['attack_rates'], true);
        $difficulty['command_policy'] = json_decode($difficulty['command_policy'], true);
        $difficulty['attack_policy'] = json_decode($difficulty['attack_policy'], true);
        $difficulty['game_settings'] = json_decode($difficulty['game_settings'], true);
    }
    unset($difficulty);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'threats' => $threats,
            'vip_ips' => $vipIps,
            'emails' => $emails,
            'difficulties' => $difficulties
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '完整題庫資料讀取失敗'], JSON_UNESCAPED_UNICODE);
}
?>
