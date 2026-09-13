<?php
// OAuth secrets should preferably be supplied through environment variables.
$configFile = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'OAuth 2.0  ID.txt';
$configText = is_readable($configFile) ? file_get_contents($configFile) : '';

preg_match('/用戶端 ID:\s*([^\r\n]+)/u', $configText, $clientIdMatch);
preg_match('/用戶端密碼:\s*([^\r\n]+)/u', $configText, $clientSecretMatch);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectPath = rtrim(str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/auth/google_login.php')))), '/');
$defaultRedirectUri = $scheme . '://' . $host . $projectPath . '/api/auth/google_callback.php';

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: trim($clientIdMatch[1] ?? ''),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: trim($clientSecretMatch[1] ?? ''),
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: $defaultRedirectUri
];