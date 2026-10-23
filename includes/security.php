<?php
function securityToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function securityField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(securityToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function securityCheck()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(securityToken(), $token)) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }
}

function securityJs($value)
{
    return htmlspecialchars(json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8');
}
