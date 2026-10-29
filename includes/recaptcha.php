<?php
if (!defined('RECAPTCHA_ENABLED')) {
    define('RECAPTCHA_ENABLED', true);
}
if (!defined('RECAPTCHA_SITE_KEY')) {
    define('RECAPTCHA_SITE_KEY', '6Lf9qQYuAAAAAHB-kZLmtgs75fROZt_1_Rd_e16E');
}
if (!defined('RECAPTCHA_SECRET_KEY')) {
    define('RECAPTCHA_SECRET_KEY', '6Lf9qQYuAAAAACOHWCJceNnN4fZTkztcsr3LQQXa');
}

function verifyRecaptcha($response): bool
{
    if (!RECAPTCHA_ENABLED) {
        return true;
    }
    if (!is_string($response) || $response === '' || strlen($response) > 4096) {
        return false;
    }
    if (RECAPTCHA_SECRET_KEY === '') {
        error_log('reCAPTCHA is enabled but RECAPTCHA_SECRET_KEY is not set.');
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            error_log('reCAPTCHA cURL error: ' . curl_error($ch));
        }
    }
    if ($body === false) {
        $context = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data,
            'timeout' => 8,
        ]]);
        $body = @file_get_contents($url, false, $context);
    }
    if ($body === false) {
        return false;
    }

    $result = json_decode((string) $body, true);
    return is_array($result) && !empty($result['success']);
}