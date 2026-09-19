<?php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireAuth('teacher');

$allowedCommands = [
    'status', 'netstat', 'whois', 'limit', 'block', 'unblock',
    'flush-dns', 'scan-mail', 'passwd'
];
$allowedAttacks = ['syn', 'udp', 'icmp', 'dns', 'crack', 'fishing'];
$rateAttackTypes = ['syn', 'udp', 'icmp', 'dns', 'fishing'];

function difficultyError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizeBooleanPolicy($policy, $allowedKeys)
{
    if (!is_array($policy)) {
        difficultyError('政策設定格式無效');
    }

    $normalized = [];
    foreach ($allowedKeys as $key) {
        $normalized[$key] = isset($policy[$key]) && $policy[$key] === true;
    }

    return $normalized;
}

function normalizeAttackRates($rates, $rateAttackTypes)
{
    if (!is_array($rates)) {
        difficultyError('攻擊機率設定格式無效');
    }

    $normalized = [];
    $intervals = [];
    foreach ($rateAttackTypes as $attackType) {
        $range = $rates[$attackType] ?? null;
        if ($range === null) {
            $normalized[$attackType] = null;
            continue;
        }

        if (!is_array($range) || count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1])) {
            difficultyError("{$attackType} 攻擊機率格式無效");
        }

        $start = (int) $range[0];
        $end = (int) $range[1];
        if ($start < 1 || $end > 100 || $start > $end) {
            difficultyError("{$attackType} 攻擊機率必須介於 1 到 100");
        }

        foreach ($intervals as $interval) {
            if ($start <= $interval[1] && $end >= $interval[0]) {
                difficultyError('啟用的攻擊機率區間不可重疊');
            }
        }

        $intervals[] = [$start, $end];
        $normalized[$attackType] = [$start, $end];
    }

    $normalized['none'] = $rates['none'] ?? null;
    return $normalized;
}

function normalizeDifficultyInput($data, $allowedCommands, $allowedAttacks, $rateAttackTypes)
{
    $name = trim((string) ($data['name'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $totalTime = filter_var($data['total_time'] ?? null, FILTER_VALIDATE_INT);
    $commandPolicy = normalizeBooleanPolicy($data['command_policy'] ?? [], $allowedCommands);
    $attackPolicy = normalizeBooleanPolicy($data['attack_policy'] ?? [], $allowedAttacks);
    $attackRates = normalizeAttackRates($data['attack_rates'] ?? [], $rateAttackTypes);
    $gameSettings = normalizeGameSettings($data['game_settings'] ?? []);

    if ($name === '' || mb_strlen($name) > 100) {
        difficultyError('難度名稱不得為空且不可超過 100 個字元');
    }
    if (mb_strlen($description) > 255) {
        difficultyError('難度說明不可超過 255 個字元');
    }
    if ($totalTime === false || $totalTime < 30 || $totalTime > 86400) {
        difficultyError('遊戲時間必須介於 30 到 86400 秒');
    }
    if (!$commandPolicy['status'] || !$commandPolicy['netstat']) {
        difficultyError('status 與 netstat 必須保持啟用');
    }
    if (!in_array(true, $attackPolicy, true)) {
        difficultyError('至少必須啟用一種攻擊或破解事件');
    }

    foreach ($rateAttackTypes as $attackType) {
        if ($attackPolicy[$attackType] && $attackRates[$attackType] === null) {
            difficultyError("{$attackType} 已啟用，但未設定攻擊機率");
        }
    }

    return [
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'total_time' => $totalTime,
        'attack_rates' => $attackRates,
        'command_policy' => $commandPolicy,
        'attack_policy' => $attackPolicy,
        'game_settings' => $gameSettings
    ];
}

function normalizeGameSettings($settings)
{
    if (!is_array($settings)) {
        difficultyError('細緻難度設定格式無效');
    }

    $settings = array_merge([
        'attack_cooldown' => 12,
        'damage_multiplier' => 1,
        'crack_speed' => 1,
        'initial_load' => ['cpu' => 12, 'gpu' => 8, 'ram' => 25, 'wifi' => 5],
        'password_policy' => [
            'min_length' => 8,
            'require_number' => true,
            'require_uppercase' => false,
            'require_symbol' => false,
            'allow_change' => true,
            'change_cooldown' => 30
        ],
        'dictionary_attack' => ['enabled' => true, 'speed_multiplier' => 1]
    ], $settings);
    $settings['initial_load'] = array_merge(
        ['cpu' => 12, 'gpu' => 8, 'ram' => 25, 'wifi' => 5],
        is_array($settings['initial_load'] ?? null) ? $settings['initial_load'] : []
    );
    $passwordPolicy = array_merge([
        'min_length' => 8,
        'require_number' => true,
        'require_uppercase' => false,
        'require_symbol' => false,
        'allow_change' => true,
        'change_cooldown' => 30
    ], is_array($settings['password_policy'] ?? null) ? $settings['password_policy'] : []);
    $dictionaryAttack = array_merge(
        ['enabled' => true, 'speed_multiplier' => 1],
        is_array($settings['dictionary_attack'] ?? null) ? $settings['dictionary_attack'] : []
    );

    $cooldown = filter_var($settings['attack_cooldown'] ?? null, FILTER_VALIDATE_INT);
    $damageMultiplier = filter_var($settings['damage_multiplier'] ?? null, FILTER_VALIDATE_FLOAT);
    $crackSpeed = filter_var($settings['crack_speed'] ?? null, FILTER_VALIDATE_FLOAT);
    $initialLoad = $settings['initial_load'] ?? [];

    if ($cooldown === false || $cooldown < 3 || $cooldown > 60) {
        difficultyError('攻擊冷卻時間必須介於 3 到 60 秒');
    }
    if ($damageMultiplier === false || $damageMultiplier < 0.5 || $damageMultiplier > 3) {
        difficultyError('攻擊傷害倍率必須介於 0.5 到 3');
    }
    if ($crackSpeed === false || $crackSpeed < 0 || $crackSpeed > 3) {
        difficultyError('密碼破解速度必須介於 0 到 3');
    }
    $minLength = filter_var($passwordPolicy['min_length'], FILTER_VALIDATE_INT);
    $changeCooldown = filter_var($passwordPolicy['change_cooldown'], FILTER_VALIDATE_INT);
    $dictionarySpeed = filter_var($dictionaryAttack['speed_multiplier'], FILTER_VALIDATE_FLOAT);
    if ($minLength === false || $minLength < 8 || $minLength > 32) {
        difficultyError('遊戲密碼最低長度必須介於 8 到 32');
    }
    if ($changeCooldown === false || $changeCooldown < 0 || $changeCooldown > 300) {
        difficultyError('密碼更換冷卻時間必須介於 0 到 300 秒');
    }
    if ($dictionarySpeed === false || $dictionarySpeed < 0 || $dictionarySpeed > 5) {
        difficultyError('字典破解速度倍率必須介於 0 到 5');
    }
    if (!is_array($initialLoad)) {
        difficultyError('初始系統負載格式無效');
    }

    $normalizedLoad = [];
    foreach (['cpu', 'gpu', 'ram', 'wifi'] as $resource) {
        $value = filter_var($initialLoad[$resource] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0 || $value > 80) {
            difficultyError("初始 {$resource} 負載必須介於 0 到 80");
        }
        $normalizedLoad[$resource] = $value;
    }

    return [
        'attack_cooldown' => $cooldown,
        'damage_multiplier' => round((float) $damageMultiplier, 2),
        'crack_speed' => round((float) $crackSpeed, 2),
        'initial_load' => $normalizedLoad,
        'password_policy' => [
            'min_length' => $minLength,
            'require_number' => $passwordPolicy['require_number'] === true,
            'require_uppercase' => $passwordPolicy['require_uppercase'] === true,
            'require_symbol' => $passwordPolicy['require_symbol'] === true,
            'allow_change' => $passwordPolicy['allow_change'] === true,
            'change_cooldown' => $changeCooldown
        ],
        'dictionary_attack' => [
            'enabled' => $dictionaryAttack['enabled'] === true,
            'speed_multiplier' => round((float) $dictionarySpeed, 2)
        ]
    ];
}

function decodeDifficultyRow($row)
{
    $row['diff_id'] = (int) $row['diff_id'];
    $row['total_time'] = (int) $row['total_time'];
    $row['is_active'] = (bool) $row['is_active'];
    $row['is_system'] = $row['owner_user_id'] === null;
    $row['attack_rates'] = json_decode($row['attack_rates'], true);
    $row['command_policy'] = json_decode($row['command_policy'], true);
    $row['attack_policy'] = json_decode($row['attack_policy'], true);
    $row['game_settings'] = json_decode($row['game_settings'], true);
    return $row;
}

$session = $_SESSION;
$teacherId = (int) $session['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT diff_id, name, description, owner_user_id, total_time, attack_rates,
                    command_policy, attack_policy, game_settings, is_active, created_at, updated_at
             FROM difficulty_configs
             WHERE owner_user_id IS NULL OR owner_user_id = ?
             ORDER BY owner_user_id IS NOT NULL, diff_id ASC'
        );
        $stmt->execute([$teacherId]);
        $rows = array_map('decodeDifficultyRow', $stmt->fetchAll());

        echo json_encode(['status' => 'success', 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
        difficultyError('不支援的請求方法', 405);
    }

    $data = getJsonInput();

    if ($method === 'POST') {
        $difficulty = normalizeDifficultyInput($data, $allowedCommands, $allowedAttacks, $rateAttackTypes);
        $stmt = $pdo->prepare(
            'INSERT INTO difficulty_configs
                     (name, description, owner_user_id, total_time, attack_rates, command_policy, attack_policy, game_settings, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)'
        );
        $stmt->execute([
            $difficulty['name'],
            $difficulty['description'],
            $teacherId,
            $difficulty['total_time'],
            json_encode($difficulty['attack_rates'], JSON_UNESCAPED_UNICODE),
            json_encode($difficulty['command_policy'], JSON_UNESCAPED_UNICODE),
            json_encode($difficulty['attack_policy'], JSON_UNESCAPED_UNICODE),
            json_encode($difficulty['game_settings'], JSON_UNESCAPED_UNICODE)
        ]);

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => '難度建立成功',
            'diff_id' => (int) $pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $diffId = (int) ($data['diff_id'] ?? $_GET['diff_id'] ?? 0);
    if ($diffId < 1) {
        difficultyError('只能管理教師自行建立的難度');
    }

    $stmt = $pdo->prepare(
        'SELECT diff_id, name, description, owner_user_id, total_time, attack_rates,
                command_policy, attack_policy, game_settings, is_active
         FROM difficulty_configs
         WHERE diff_id = ? AND owner_user_id = ?'
    );
    $stmt->execute([$diffId, $teacherId]);
    $current = $stmt->fetch();
    if (!$current) {
        difficultyError('找不到此難度，或您沒有管理權限', 404);
    }

    if ($method === 'DELETE') {
        $assignmentStmt = $pdo->prepare('SELECT COUNT(*) FROM difficulty_assignments WHERE difficulty_id = ?');
        $assignmentStmt->execute([$diffId]);
        if ((int) $assignmentStmt->fetchColumn() > 0) {
            difficultyError('此難度已分配給學生，請先停用或解除分配後再刪除');
        }

        $deleteStmt = $pdo->prepare('DELETE FROM difficulty_configs WHERE diff_id = ? AND owner_user_id = ?');
        $deleteStmt->execute([$diffId, $teacherId]);
        echo json_encode(['status' => 'success', 'message' => '難度已刪除'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current['attack_rates'] = json_decode($current['attack_rates'], true);
    $current['command_policy'] = json_decode($current['command_policy'], true);
    $current['attack_policy'] = json_decode($current['attack_policy'], true);
    $current['game_settings'] = json_decode($current['game_settings'], true);
    $merged = array_merge($current, $data);
    $difficulty = normalizeDifficultyInput($merged, $allowedCommands, $allowedAttacks, $rateAttackTypes);
    $isActive = array_key_exists('is_active', $data)
        ? ($data['is_active'] === true)
        : (bool) $current['is_active'];

    $updateStmt = $pdo->prepare(
        'UPDATE difficulty_configs
         SET name = ?, description = ?, total_time = ?, attack_rates = ?,
             command_policy = ?, attack_policy = ?, game_settings = ?, is_active = ?
         WHERE diff_id = ? AND owner_user_id = ?'
    );
    $updateStmt->execute([
        $difficulty['name'],
        $difficulty['description'],
        $difficulty['total_time'],
        json_encode($difficulty['attack_rates'], JSON_UNESCAPED_UNICODE),
        json_encode($difficulty['command_policy'], JSON_UNESCAPED_UNICODE),
        json_encode($difficulty['attack_policy'], JSON_UNESCAPED_UNICODE),
        json_encode($difficulty['game_settings'], JSON_UNESCAPED_UNICODE),
        $isActive ? 1 : 0,
        $diffId,
        $teacherId
    ]);

    echo json_encode(['status' => 'success', 'message' => '難度更新成功'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '難度資料庫操作失敗'], JSON_UNESCAPED_UNICODE);
}
?>