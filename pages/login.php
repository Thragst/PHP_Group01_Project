<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['action']) || isset($_GET['timeout']))) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?login=timeout');
    exit();
}

function postString($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$maxAttempts = 3;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_failed_attempts' LIMIT 1");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false && (int)$val > 0) {
        $maxAttempts = (int)$val;
    }
} catch (PDOException $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputIdentifier = postString('username');
    $password = postString('password');

    if ($inputIdentifier === '' || $password === '') {
        header('Location: ../index.php?login=error');
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR user_id = ? LIMIT 1");
    $stmt->execute([$inputIdentifier, $inputIdentifier, $inputIdentifier]);
    $user = $stmt->fetch();

    if ($user) {
        $currentStatus = $user['status'] ?? $user['account_status'] ?? 'Active';
        $currentAttempts = (int)($user['failed_attempts'] ?? $user['login_attempts'] ?? 0);

        if (strtolower($currentStatus) === 'locked' || strtolower($currentStatus) === 'suspended' || $currentAttempts >= $maxAttempts) {
            if (strtolower($currentStatus) !== 'locked') {
                $lockStmt = $pdo->prepare("UPDATE users SET status = 'Locked', account_status = 'Locked' WHERE user_id = ?");
                $lockStmt->execute([$user['user_id']]);
            }
            logAudit($pdo, $user['user_id'], "Login blocked: Account is currently locked for '" . $inputIdentifier . "'");
            header('Location: ../index.php?login=locked');
            exit();
        }

        if (strtolower($currentStatus) === 'pending') {
            if (password_verify($password, $user['password_hash'])) {
                header('Location: ../index.php?login=unverified');
            } else {
                header('Location: ../index.php?login=error');
            }
            exit();
        }

        if (password_verify($password, $user['password_hash'])) {
            $resetStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, login_attempts = 0 WHERE user_id = ?");
            $resetStmt->execute([$user['user_id']]);

            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'] ?? $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            logAudit($pdo, $user['user_id'], "Successful login (" . $user['role'] . ")");

            switch ($user['role']) {
                case 'Admin':
                case 'Staff':
                case 'Developer':
                    header('Location: ../admin/index.php');
                    break;

                case 'Driver':
                    header('Location: ../driver/deliveries.php');
                    break;

                case 'Customer':
                default:
                    header('Location: ../index.php?login=success');
                    break;
            }
            exit();
        } else {
            $newAttempts = $currentAttempts + 1;

            if ($newAttempts >= $maxAttempts) {
                $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, login_attempts = ?, status = 'Locked', account_status = 'Locked' WHERE user_id = ?");
                $updateStmt->execute([$newAttempts, $newAttempts, $user['user_id']]);
                logAudit($pdo, $user['user_id'], "Account lockout triggered for '" . $inputIdentifier . "' after " . $newAttempts . " failed attempts");
                header('Location: ../index.php?login=locked');
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, login_attempts = ? WHERE user_id = ?");
                $updateStmt->execute([$newAttempts, $newAttempts, $user['user_id']]);
                logAudit($pdo, $user['user_id'], "Failed login attempt for '" . $inputIdentifier . "'");
                header('Location: ../index.php?login=error');
            }
            exit();
        }
    }

    logAudit($pdo, null, "Failed login attempt for unknown identifier: '" . $inputIdentifier . "'");
    header('Location: ../index.php?login=error');
    exit();
}

header('Location: ../index.php');
exit();