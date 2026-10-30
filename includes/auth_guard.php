<?php
const ADMIN_ROLES = ['Admin', 'Staff', 'Developer'];

function noCacheHeaders()
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

function roleHome($role)
{
    if (in_array($role, ADMIN_ROLES, true)) return 'admin/index.php';
    if ($role === 'Driver')                 return 'driver/deliveries.php';
    return 'index.php';
}

function webBasePath()
{
    $root    = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return substr($root, strlen($docRoot));
}

function keepStaffOut()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    noCacheHeaders();
    $role = $_SESSION['user_role'] ?? '';
    if (!empty($_SESSION['logged_in']) && $role !== '' && $role !== 'Customer'
        && (in_array($role, ADMIN_ROLES, true) || $role === 'Driver')) {
        header('Location: ' . webBasePath() . '/' . roleHome($role));
        exit();
    }
}

function requireRole(array $allowedRoles)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    noCacheHeaders();

    $root     = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $webBase  = substr($root, strlen($docRoot));

    $loggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    $role     = $_SESSION['user_role'] ?? '';

    if ($loggedIn && in_array($role, $allowedRoles, true)) {
        return;
    }

    $target = $loggedIn ? roleHome($role) : 'index.php';
    header('Location: ' . $webBase . '/' . $target);
    exit();
}