<?php
// api/logout.php
require_once 'common.php';

requirePost();
session_start();
session_unset();
session_destroy();

echo json_encode(["status" => "success", "message" => "已成功登出。"]);
?>