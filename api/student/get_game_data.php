<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();

try {
    $threatStmt = $pdo->prepare('SELECT ip_address FROM threat_ips WHERE is_active = 1');
    $threatStmt->execute();
    $maliciousIPs = array_column($threatStmt->fetchAll(PDO::FETCH_ASSOC), 'ip_address');

    $vipStmt = $pdo->prepare('SELECT ip_address FROM vip_ips');
    $vipStmt->execute();
    $vipIPs = array_column($vipStmt->fetchAll(PDO::FETCH_ASSOC), 'ip_address');

    $emailStmt = $pdo->prepare('SELECT sender, subject, content, is_malicious FROM phishing_emails');
    $emailStmt->execute();
    $emails = $emailStmt->fetchAll(PDO::FETCH_ASSOC);

    $difficultyStmt = $pdo->prepare('SELECT diff_id, total_time, attack_rates FROM difficulty_configs ORDER BY diff_id ASC');
    $difficultyStmt->execute();
    $difficultyRows = $difficultyStmt->fetchAll(PDO::FETCH_ASSOC);

    $difficulties = [];
    foreach ($difficultyRows as $row) {
        $attackRates = json_decode($row['attack_rates'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($attackRates)) {
            throw new RuntimeException('難度設定格式錯誤');
        }

        $difficulties[(int) $row['diff_id']] = [
            'time' => (int) $row['total_time'],
            'ranges' => $attackRates
        ];
    }

    echo json_encode([
        'status' => 'success',
        'maliciousIPs' => $maliciousIPs,
        'vipIPs' => $vipIPs,
        'emails' => [
            'normal' => array_values(array_filter($emails, fn($email) => (int) $email['is_malicious'] === 0)),
            'malicious' => array_values(array_filter($emails, fn($email) => (int) $email['is_malicious'] === 1))
        ],
        'difficulties' => $difficulties
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '無法讀取遊戲資料庫設定'
    ], JSON_UNESCAPED_UNICODE);
}