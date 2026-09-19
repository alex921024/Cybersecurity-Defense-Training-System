<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
$session = requireAuth(['student', 'teacher', 'admin']);

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

    if ($session['role'] === 'admin') {
        $difficultySql = 'SELECT diff_id, name, description, owner_user_id, total_time, attack_rates,
                     command_policy, attack_policy, game_settings, is_active
                          FROM difficulty_configs
                          WHERE is_active = 1
                          ORDER BY diff_id ASC';
        $difficultyStmt = $pdo->prepare($difficultySql);
        $difficultyStmt->execute();
    } elseif ($session['role'] === 'teacher') {
        $difficultySql = 'SELECT diff_id, name, description, owner_user_id, total_time, attack_rates,
                     command_policy, attack_policy, game_settings, is_active
                          FROM difficulty_configs
                          WHERE is_active = 1 AND (owner_user_id IS NULL OR owner_user_id = ?)
                          ORDER BY diff_id ASC';
        $difficultyStmt = $pdo->prepare($difficultySql);
        $difficultyStmt->execute([(int) $session['user_id']]);
    } else {
        $difficultySql = 'SELECT d.diff_id, d.name, d.description, d.owner_user_id,
                     d.total_time, d.attack_rates, d.command_policy,
                     d.attack_policy, d.game_settings, d.is_active
                  FROM difficulty_configs d
                  LEFT JOIN users student ON student.user_id = ?
                               AND student.role = \'student\'
                               AND student.is_approved = 1
                  WHERE d.is_active = 1
                    AND (d.owner_user_id IS NULL OR d.owner_user_id = student.teacher_id)
                  ORDER BY d.diff_id ASC';
        $difficultyStmt = $pdo->prepare($difficultySql);
        $difficultyStmt->execute([(int) $session['user_id']]);
    }
    $difficultyRows = $difficultyStmt->fetchAll(PDO::FETCH_ASSOC);

    $difficulties = [];
    $defaultCommands = [
        'status' => true, 'netstat' => true, 'whois' => true, 'limit' => true,
        'block' => true, 'unblock' => true, 'flush-dns' => true,
        'scan-mail' => true, 'passwd' => true
    ];
    $defaultAttacks = [
        'syn' => true, 'udp' => true, 'icmp' => true,
        'dns' => true, 'crack' => true, 'fishing' => true
    ];
    $defaultGameSettings = [
        'attack_cooldown' => 12,
        'damage_multiplier' => 1,
        'crack_speed' => 1,
        'initial_load' => ['cpu' => 12, 'gpu' => 8, 'ram' => 25, 'wifi' => 5],
        'password_policy' => [
            'min_length' => 8, 'require_number' => true, 'require_uppercase' => false,
            'require_symbol' => false, 'allow_change' => true, 'change_cooldown' => 30
        ],
        'dictionary_attack' => ['enabled' => true, 'speed_multiplier' => 1]
    ];
    foreach ($difficultyRows as $row) {
        $attackRates = json_decode($row['attack_rates'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($attackRates)) {
            throw new RuntimeException('難度設定格式錯誤');
        }

        $commandPolicy = json_decode($row['command_policy'] ?: '{}', true);
        $attackPolicy = json_decode($row['attack_policy'] ?: '{}', true);
        if (!is_array($commandPolicy)) $commandPolicy = [];
        if (!is_array($attackPolicy)) $attackPolicy = [];
        $gameSettings = json_decode($row['game_settings'] ?: '{}', true);
        if (!is_array($gameSettings)) $gameSettings = [];
        $gameSettings = array_merge($defaultGameSettings, $gameSettings);
        $gameSettings['initial_load'] = array_merge($defaultGameSettings['initial_load'], $gameSettings['initial_load'] ?? []);
        $gameSettings['password_policy'] = array_merge($defaultGameSettings['password_policy'], $gameSettings['password_policy'] ?? []);
        $gameSettings['dictionary_attack'] = array_merge($defaultGameSettings['dictionary_attack'], $gameSettings['dictionary_attack'] ?? []);

        $difficulties[(int) $row['diff_id']] = [
            'name' => $row['name'] ?: '未命名難度',
            'description' => $row['description'] ?? null,
            'time' => (int) $row['total_time'],
            'ranges' => $attackRates,
            'commandPolicy' => array_merge($defaultCommands, $commandPolicy),
            'attackPolicy' => array_merge($defaultAttacks, $attackPolicy),
            'gameSettings' => $gameSettings,
            'isSystem' => $row['owner_user_id'] === null,
            'ownerUserId' => $row['owner_user_id'] === null ? null : (int) $row['owner_user_id']
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