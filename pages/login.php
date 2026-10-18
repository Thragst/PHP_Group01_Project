<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

function postString($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
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
        if ($user['account_status'] === 'Locked' || (int)$user['login_attempts'] >= 3) {
            if ($user['account_status'] !== 'Locked') {
                $lockStmt = $pdo->prepare("UPDATE users SET account_status = 'Locked' WHERE user_id = ?");
                $lockStmt->execute([$user['user_id']]);
            }
            header('Location: ../index.php?login=locked');
            exit();
        }

        if (password_verify($password, $user['password_hash'])) {
            $resetStmt = $pdo->prepare("UPDATE users SET login_attempts = 0 WHERE user_id = ?");
            $resetStmt->execute([$user['user_id']]);

            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'] ?? $user['email'];
            $_SESSION['user_role'] = $user['role'];

            if (in_array($user['role'], ['Admin', 'Staff', 'Developer'], true)) {
                header('Location: ../admin/index.php');
            } else {
                header('Location: ../index.php?login=success');
            }
            exit();
        } else {
            $newAttempts = (int)$user['login_attempts'] + 1;
            
            if ($newAttempts >= 3) {
                $updateStmt = $pdo->prepare("UPDATE users SET login_attempts = ?, account_status = 'Locked' WHERE user_id = ?");
                $updateStmt->execute([$newAttempts, $user['user_id']]);
                header('Location: ../index.php?login=locked');
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE user_id = ?");
                $updateStmt->execute([$newAttempts, $user['user_id']]);
                header('Location: ../index.php?login=error');
            }
            exit();
        }
    }

    header('Location: ../index.php?login=error');
    exit();
}

header('Location: ../index.php');
exit();