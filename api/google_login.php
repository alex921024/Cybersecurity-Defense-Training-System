<?php
$config = require __DIR__ . '/google_config.php';
if ($config['client_id'] === '' || $config['client_secret'] === '') {
    http_response_code(500);
    exit('Google OAuth 尚未設定');
}

$nonce = bin2hex(random_bytes(32));
$issuedAt = (string) time();
$stateData = $nonce . '.' . $issuedAt;
$state = $stateData . '.' . hash_hmac('sha256', $stateData, $config['client_secret']);
$params = http_build_query([
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;