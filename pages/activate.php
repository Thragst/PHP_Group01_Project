<?php
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit_helper.php';

$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: ../index.php?login=invalidlink');
    exit();
}

$hash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare("SELECT email FROM email_verifications WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        header('Location: ../index.php?login=invalidlink');
        exit();
    }

    $pdo->prepare("UPDATE users SET status = 'Active', account_status = 'Active' WHERE email = ? AND status = 'Pending'")
        ->execute([$row['email']]);
    $pdo->prepare("DELETE FROM email_verifications WHERE email = ?")->execute([$row['email']]);

    $u = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $u->execute([$row['email']]);
    $user = $u->fetch();
    if ($user) {
        logAudit($pdo, $user['user_id'], 'Account activated via email link');
    }

    header('Location: ../index.php?login=activated');
    exit();
} catch (PDOException $e) {
    header('Location: ../index.php?login=invalidlink');
    exit();
}