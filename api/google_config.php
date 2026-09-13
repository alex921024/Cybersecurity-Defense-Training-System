<?php
// OAuth secrets should preferably be supplied through environment variables.
$configFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'OAuth 2.0  ID.txt';
$configText = is_readable($configFile) ? file_get_contents($configFile) : '';

preg_match('/用戶端 ID:\s*([^\r\n]+)/u', $configText, $clientIdMatch);
preg_match('/用戶端密碼:\s*([^\r\n]+)/u', $configText, $clientSecretMatch);

function buildDefaultRedirectUri() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';

    $host = $_SERVER['HTTP_X_FORWARDED_HOST']
        ?? $_SERVER['HTTP_HOST']
        ?? $_SERVER['SERVER_NAME']
        ?? 'localhost';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $rootPath = preg_replace('#/api$#', '', $scriptDir);
    $rootPath = rtrim($rootPath, '/');

    return $scheme . '://' . $host . $rootPath . '/api/google_callback.php';
}

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: trim($clientIdMatch[1] ?? ''),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: trim($clientSecretMatch[1] ?? ''),
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: buildDefaultRedirectUri()
];