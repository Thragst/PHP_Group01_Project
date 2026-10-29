<?php
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if ( empty($_SESSION['mfa_pending']) || empty($_SESSION['mfa_user_id']) ) { header('Location: ../index.php?login=required'); exit(); }
$userId = (int) $_SESSION['mfa_user_id'];
$stmt = $pdo->prepare(" SELECT user_id, full_name, email, username, role, mfa_enabled FROM users WHERE user_id = ? LIMIT 1 ");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || (int) $user['mfa_enabled'] !== 1) { unset($_SESSION['mfa_pending']); unset($_SESSION['mfa_user_id']); header('Location: ../index.php?login=error'); exit();}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" >
    <title>Two-Factor Authentication - SportMart</title>
    <link rel="stylesheet" href="../assets/css/authentication.css">
</head>

<body>
    <main class = "auth-page">
        <section class = "auth-card">
            <div class = "auth-brand"> 
                <h2 class = "auth-brand-name">SPORTMART</h2>
                <div class = "auth-brand-line"></div>
            </div>
            <h1 class = "auth-heading">Two-Factor Authentication</h1>
            <p class = "auth-description">Enter the 6-digit verification code from your authenticator app to continue. </p>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
                <div class = "auth-message auth-message-error">
                    <strong>Invalid authentication code.</strong> Please check your authenticator app and try again.
                </div> 
            <?php endif; ?>
            <form method="POST" action="verify_action.php" class="auth-form">
                <?php echo csrfField(); ?>
                <div class="auth-form-group">
                    <label for="otp" class="auth-label">Authenticaion Code</label>
                    <input type="text" id="otp" name="otp" class="auth-otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="auth-button">verify &amp; Continue</button>
            </form>
                <div class= "auth-footer">
                    <a href="../index.php">Cancel</a>
                </div>
    </main>
</body>
</html>