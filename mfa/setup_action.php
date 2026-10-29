<?php
use PragmaRX\Google2FA\Google2FA;
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isLoggedIn()) { header('Location: ../index.php?login=required'); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: setup.php'); exit(); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid request. Refresh the page and try again.'); }
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare(" SELECT user_id, mfa_enabled, mfa_secret FROM users WHERE user_id = ? LIMIT 1 ");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: ../logout.php'); exit();}
if ((int) $user['mfa_enabled'] === 1) { header('Location: ../index.php?page=settings&mfa=already_enabled'); exit(); }
if (empty($user['mfa_secret'])) { header('Location: setup.php?error=missing_secret'); exit();}
$otp = trim($_POST['otp'] ?? '');
if (!preg_match('/^\d{6}$/', $otp)) { header('Location: setup.php?error=invalid'); exit(); }

$google2fa = new Google2FA();
$isValid = $google2fa->verifyKey( $user['mfa_secret'], $otp);

if (!$isValid) { header('Location: setup.php?error=invalid'); exit(); }
$stmt = $pdo->prepare(" UPDATE users SET mfa_enabled = 1, mfa_confirmed_at = NOW() WHERE user_id = ? ");
$stmt->execute([$userId]);

header('Location: ../index.php?page=settings&mfa=enabled');
exit();