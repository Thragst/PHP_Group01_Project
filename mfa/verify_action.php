<?php
use PragmaRX\Google2FA\Google2FA;
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

if ( empty($_SESSION['mfa_pending']) || empty($_SESSION['mfa_user_id']) ) { header('Location: ../index.php?login=required'); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: verify.php'); exit(); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid request. Refresh the page and try again.'); }
$userId = (int) $_SESSION['mfa_user_id'];
$stmt = $pdo->prepare(" SELECT user_id, full_name, email, username, role, status, failed_attempts, mfa_enabled, mfa_secret FROM users WHERE user_id = ? LIMIT 1 ");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || (int)$user['mfa_enabled'] !== 1 || empty($user['mfa_secret'])) {
    unset($_SESSION['mfa_pending']); unset($_SESSION['mfa_user_id']); header('Location: ../index.php?login=error'); exit();
}
$maxAttempts = 3;
try {
    $ms = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_failed_attempts' LIMIT 1");
    $ms->execute();
    $mv = $ms->fetchColumn();
    if ($mv !== false && (int)$mv > 0) { $maxAttempts = (int)$mv; }
} catch (PDOException $e) {}
$mfaStatus   = strtolower((string)($user['status'] ?? ''));
$mfaAttempts = (int)($user['failed_attempts'] ?? 0);
if ($mfaStatus === 'locked' || $mfaStatus === 'suspended' || $mfaAttempts >= $maxAttempts) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_user_id']);
    header('Location: ../index.php?login=locked'); exit();
}
$otp = trim($_POST['otp'] ?? '');
if (!preg_match('/^\d{6}$/', $otp)) { header('Location: verify.php?error=invalid'); exit(); }
$google2fa = new Google2FA();
$isValid = $google2fa->verifyKey( $user['mfa_secret'], $otp );
if (!$isValid) {
    $newAttempts = $mfaAttempts + 1;
    if ($newAttempts >= $maxAttempts) {
        $pdo->prepare("UPDATE users SET failed_attempts = ?, login_attempts = ?, status = 'Locked', account_status = 'Locked' WHERE user_id = ?")->execute([$newAttempts, $newAttempts, $user['user_id']]);
        try {
            $pdo->prepare("INSERT INTO account_locks (username, failed_attempts, locked_at, status) VALUES (?, ?, NOW(), 'Locked')")->execute([$user['username'] ?? $user['email'], $newAttempts]);
        } catch (PDOException $ex) {}
        logAudit( $pdo, $user['user_id'], "Account lockout triggered after " . $newAttempts . " failed MFA attempts" );
        unset($_SESSION['mfa_pending'], $_SESSION['mfa_user_id']);
        header('Location: ../index.php?login=locked'); exit();
    }
    $pdo->prepare("UPDATE users SET failed_attempts = ?, login_attempts = ? WHERE user_id = ?")->execute([$newAttempts, $newAttempts, $user['user_id']]);
    logAudit( $pdo, $user['user_id'], "Failed MFA verification attempt" );
    header('Location: verify.php?error=invalid'); exit();
}

$resetStmt = $pdo->prepare(" UPDATE users SET failed_attempts = 0, login_attempts = 0 WHERE user_id = ? ");
$resetStmt->execute([$user['user_id']]);
session_regenerate_id(true);
$_SESSION['logged_in'] = true; $_SESSION['user_id'] = $user['user_id']; $_SESSION['user_name'] = $user['full_name']; $_SESSION['username'] = $user['username'] ?? $user['email']; $_SESSION['user_role'] = $user['role']; $_SESSION['last_activity'] = time();
unset($_SESSION['mfa_pending']);
unset($_SESSION['mfa_user_id']);
logAudit( $pdo, $user['user_id'], "Successful login with MFA (" . $user['role'] . ")" );
switch ($user['role']) { 
    case 'Admin': case 'Staff': case 'Developer': header('Location: ../admin/index.php'); break;
    case 'Driver': header('Location: ../driver/deliveries.php'); break;
    case 'Customer': default: header('Location: ../index.php?login=success'); break;
}
exit();