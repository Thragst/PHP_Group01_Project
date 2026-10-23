<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $role   = $_SESSION['user_role'] ?? 'User';
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
    $logStmt->execute([
        $userId,
        "User logged out (" . $role . ")",
        $ip
    ]);

    if (!empty($_SESSION['username'])) {
        $loginLogStmt = $pdo->prepare("INSERT INTO login_logs (user_id, username, event_type, ip_address) VALUES (?, ?, 'LOGOUT', ?)");
        $loginLogStmt->execute([
            $userId,
            $_SESSION['username'],
            $ip
        ]);
    }
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header('Location: ../index.php?logout=success');
exit();