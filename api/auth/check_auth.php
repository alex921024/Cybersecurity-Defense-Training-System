<?php
// api/check_auth.php
require_once dirname(__DIR__) . '/core/common.php';

requireGet();
$session = requireAuth();

echo json_encode([
    "status" => "success",
    "user_id" => $session['user_id'],
    "username" => $session['username'],
    "role" => $session['role']
]);
?>