<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';
if (!isLoggedIn()) {header('Location: ../index.php?login=required'); exit(); }

use PragmaRX\Google2FA\Google2FA;
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare(" SELECT user_id, full_name, email, mfa_enabled, mfa_secret FROM users WHERE user_id = ? LIMIT 1 ");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: ../logout.php'); exit(); }
if ((int) $user['mfa_enabled'] === 1) { header('Location: ../index.php?page=settings&mfa=already_enabled'); exit(); }
$google2fa = new Google2FA();
if (empty($user['mfa_secret'])) { $secret = $google2fa->generateSecretKey();
    $stmt = $pdo->prepare(" UPDATE users SET mfa_secret = ? WHERE user_id = ? ");
    $stmt->execute([ $secret, $userId ]);
    $user['mfa_secret'] = $secret;
}
$otpAuthUrl = $google2fa->getQRCodeUrl( 'SportMart', $user['email'], $user['mfa_secret'] );
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
$builder = new Builder(writer: new SvgWriter(), data: $otpAuthUrl, size: 300, margin: 10);
$result = $builder->build();
$qrCodeDataUri = $result->getDataUri();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" >
    <title>Enable MFA - SportMart</title>
    <link rel="stylesheet" href="../assets/css/authentication.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card auth-card-wide">
            <div class="auth-brand">
                <h2 class="auth-brand-name">SPORTMART</h2>
                <div class="auth-brand-line"></div>
            </div>
            <h1 class="auth-heading">Enable Two-Factor Authentication</h1>
            <p class="auth-description">Protect your Sportmart account with an additional layer of security. </p>
            <div class="auth-info"> <strong>Step 1</strong><br> Open Google Authenticator on your phone and Scan the QR code below. </div>
            <div class="auth-qr-container"> <img src="<?=  htmlspecialchars($qrCodeDataUri) ?>" alt="Sportmart MFA QR Code"> </div>
            <div class="auth-info"> <strong>Can't scan the QR Code?</strong><br> Enter the setup key manually into your authenticator app. </div>
            <div class="auth-secret-box"> <span class="auth-secret-label">Manual Setup Key</span> <code class="auth-secret"> <?=  htmlspecialchars($user['mfa_secret']) ?></code> </div>
            <div class="auth-divider"></div>
            <div class="auth-info"> <strong>Step 2</strong><br> Enter the 6-digit code currently displayed by Google Authenticator. </div>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
                <div class="auth-message auth-message-error"> <strong>Invalid authentication code. </strong> Please check your authentication app and try again. </div>
            <?php endif; ?>
            <form method="POST" action="setup_action.php" class="auth-form">
                <div class="auth-form-group"> <label for="otp" class="auth-label">Authentication Code</label> <input type="text" id="otp" name="otp" class="auth-otp-input" inputmode="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required> </div>
                <button type="submit" class="auth-button">Verify %amp; Enable MFA</button>
            </form>
            <div class="auth-footer"> <a href="../index.php?page=settings">Cancel</a> </div>
        </section>
    </main>
</body>
</html>