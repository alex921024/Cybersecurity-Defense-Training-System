<?php
// api/get_users.php
require_once dirname(__DIR__) . '/core/common.php';
require_once dirname(__DIR__) . '/core/db_connect.php';

requireGet();
$session = requireAuth(['admin', 'teacher']);

$role = $session['role'];
$user_id = $session['user_id'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$filterRole = trim((string) ($_GET['role'] ?? ''));
$approval = $_GET['approval'] ?? '';

if (mb_strlen($keyword) > 100 || !in_array($filterRole, ['', 'student', 'teacher'], true) || !in_array((string) $approval, ['', '0', '1'], true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "帳號篩選條件無效"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conditions = [];
    $params = [];
    if ($role === 'admin') {
        $conditions[] = "u.role != 'admin'";
        if ($filterRole !== '') {
            $conditions[] = 'u.role = ?';
            $params[] = $filterRole;
        }
    } else {
        $conditions[] = "u.role = 'student' AND (u.teacher_id IS NULL OR u.teacher_id = ?)";
        $params[] = $user_id;
    }
    if ($keyword !== '') {
        $conditions[] = 'u.username LIKE ?';
        $params[] = '%' . $keyword . '%';
    }
    if ($approval !== '') {
        $conditions[] = 'u.is_approved = ?';
        $params[] = (int) $approval;
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.username, u.role, u.teacher_id, u.is_approved, u.created_at
         FROM users u {$where}
         ORDER BY u.is_approved ASC, u.role DESC, u.created_at DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success", 
        "data" => $users, 
        "current_user_id" => $user_id,
        "current_role" => $role,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "pages" => $total > 0 ? (int) ceil($total / $limit) : 0
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "資料庫讀取錯誤"]);
}
?>