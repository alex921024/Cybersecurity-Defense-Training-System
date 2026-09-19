<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
requireAuth('admin');

try {
    $stats = [];
    $stats['students'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $stats['teachers'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $stats['pending_approvals'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND teacher_id IS NOT NULL AND is_approved = 0")->fetchColumn();
    $stats['active_threats'] = (int) $pdo->query('SELECT COUNT(*) FROM threat_ips WHERE is_active = 1')->fetchColumn();
    $stats['active_difficulties'] = (int) $pdo->query('SELECT COUNT(*) FROM difficulty_configs WHERE is_active = 1')->fetchColumn();
    $stats['today_records'] = (int) $pdo->query('SELECT COUNT(*) FROM game_records WHERE played_at >= CURRENT_DATE()')->fetchColumn();

    $successStmt = $pdo->query(
        "SELECT COALESCE(ROUND(100 * AVG(end_reason = 'SUCCESS'), 1), 0)
         FROM game_records
         WHERE played_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)"
    );
    $stats['success_rate_30d'] = (float) $successStmt->fetchColumn();

    echo json_encode(['status' => 'success', 'data' => $stats], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '管理員總覽資料讀取失敗'], JSON_UNESCAPED_UNICODE);
}
?>
