<?php
if (defined('SPORTMART_SSL_LOADED')) { return; }
define('SPORTMART_SSL_LOADED', true);

if (PHP_SAPI === 'cli') { return; }

function sslIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') { return true; }
    if (($_SERVER['SERVER_PORT'] ?? '') == 443) { return true; }

    $trustProxy = getenv('SPORTMART_TRUST_PROXY');
    if ($trustProxy !== false && !in_array(strtolower($trustProxy), ['', '0', 'false', 'off', 'no'], true)) {
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') { return true; }
    }
    return false;
}

$forceHttps = getenv('SPORTMART_FORCE_HTTPS');
$forceHttps = ($forceHttps === false) ? true : !in_array(strtolower($forceHttps), ['0', 'false', 'off', 'no'], true);

$isHttps = sslIsHttps();

if ($forceHttps && !$isHttps) {
    $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/[^A-Za-z0-9\.\-\[\]:]/', '', $host);
    $port = getenv('SPORTMART_HTTPS_PORT');
    $port = ($port && $port !== '443') ? ':' . (int) $port : '';

    header('Location: https://' . $host . $port . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
    exit;
}

if ($isHttps) {
    $hstsHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if (!in_array($hstsHost, ['localhost', '127.0.0.1', '[::1]'], true)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($isHttps) {
    header('Content-Security-Policy: upgrade-insecure-requests');
}