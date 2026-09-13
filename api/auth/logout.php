<?php
// api/logout.php
require_once dirname(__DIR__) . '/core/common.php';

requirePost();
session_start();
session_unset();
session_destroy();

echo json_encode(["status" => "success", "message" => "已成功登出。"]);
?>