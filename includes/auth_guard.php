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

/*
| Idle timeout for Customer and Driver sessions (admin pages use admin_header.php).
| Limits come from system_settings and are editable on Security & Policies.
| Call AFTER db.php has been loaded.
*/
function enforceIdleTimeout(PDO $pdo)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['logged_in'])) {
        return;
    }

    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'Customer') {
        $key = 'customer_timeout_minutes';
        $minutes = 30;
    } elseif ($role === 'Driver') {
        $key = 'driver_timeout_minutes';
        $minutes = 60;
    } else {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false && (int)$val > 0) {
            $minutes = (int)$val;
        }
    } catch (PDOException $e) {
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $minutes * 60) {
        require_once __DIR__ . '/audit_helper.php';
        if (!empty($_SESSION['user_id'])) {
            logAudit($pdo, $_SESSION['user_id'], "Session timed out due to inactivity (" . $role . ")");
        }
        session_unset();
        session_destroy();

        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            http_response_code(401);
            exit();
        }
        header('Location: ' . webBasePath() . '/index.php?login=timeout');
        exit();
    }
    $_SESSION['last_activity'] = $now;
}
