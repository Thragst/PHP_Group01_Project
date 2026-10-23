<?php
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';
if ($userId <= 0) { echo '<section class="account-page"> <p class="account-notice error">Account not found. Please login again.</p> </section>'; return;}
$stmt = $pdo->prepare(
    "SELECT phone, physical_address
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    echo '<section class="account-page"><p class="account-notice error">Account not found. Please login again.</p></section>';
    return;
}

$flash = getFlash();
?>
<section class="account-page">
    <div class="account-header">
        <p class="small-title">SPORTMART ACCOUNT</p>
        <h1>Settings</h1>
        <p>Manage your shipping details, password, and account.</p>
    </div>

    <?php if ($flash): ?>
        <div class="account-notice <?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>
<div class="settings-grid">
    <div class="account-card">
        <h2>Shipping Information</h2>

        <form action="pages/account_actions.php" method="POST" class="account-form" novalidate>
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_shipping">

            <div class="form-row">
                <label for="address">Shipping address</label>
                <textarea id="address" name="address" rows="3" maxlength="255" placeholder="House no., street, barangay, city, province"><?php echo e($account['physical_address']); ?></textarea>
            </div>

            <div class="form-row">
                <label for="phone">Contact number</label>
                <input type="text" id="phone" name="phone" value="<?php echo e($account['phone']); ?>" maxlength="13" placeholder="09123456789">
                <small>Format: 09XXXXXXXXX or +639XXXXXXXXX</small>
            </div>

            <button type="submit" class="account-button">Save Shipping Info</button>
        </form>
    </div>
    <div class="account-card">
        <h2>Change Password</h2>
            <form action="pages/account_actions.php" method="POST" class="account-form" id="changePasswordForm" novalidate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-row">
                    <label for="currentPassword">Current password</label>
                    <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-row">
                    <label for="newPassword">New password</label>
                    <input type="password" id="newPassword" name="new_password" autocomplete="new-password" required>
                    <ul class="password-rules" id="newPasswordRules">
                        <li data-rule="length">At least twelve (12) characters in length</li>
                        <li data-rule="upper">At least one (1) CAPITAL letter</li>
                        <li data-rule="lower">At least one (1) small letter</li>
                        <li data-rule="number">At least one (1) number</li>
                        <li data-rule="special">At least one (1) special character</li>
                    </ul>
                </div>
                <div class="form-row">
                    <label for="confirmNewPassword">Confirm new password</label>
                    <input type="password" id="confirmNewPassword" name="confirm_password" autocomplete="new-password" required>
                    <small id="confirmNewMessage" class="match-message"></small>
                </div>
                <button type="submit" class="account-button">Update Password</button>
            </form>
    </div>

    <div class="account-card">
        <h2>Two-Factor Authentication</h2>
            <?php $mfaStmt = $pdo->prepare(" SELECT mfa_enabled FROM users WHERE user_id = ? LIMIT 1 ");
            $mfaStmt->execute([$userId]);
            $mfaEnabled = (int) $mfaStmt->fetchColumn() === 1;
        ?>
        <?php if ($mfaEnabled): ?>
            <div class="mfa-status enabled">
                <span class="mfa-status-dot"></span>
                <div> <strong>MFA is enabled</strong>
                    <p> Your account is protected with two-factor authentication. </p>
                </div>
            </div>
            <p> You will be asked for a verification code from your authenticator
                app when you sign in.
            </p>
        <?php else: ?>
            <div class="mfa-status disabled">
                <span class="mfa-status-dot"></span>
                <div> <strong>MFA is disabled</strong>
                    <p>
                        Add an extra layer of security to your SportMart account
                        using an authenticator app.
                    </p>
                </div>
            </div>
            <a href="mfa/setup.php" class="account-button mfa-button" > Enable MFA</a>
        <?php endif; ?>
    </div>

    <div class="account-card danger-card">
        <h2>Delete Account</h2>

        <?php if ($userRole === 'Customer'): ?>
            <p class="danger-text">
                This will permanently remove your account and saved cart.
                This action cannot be undone.
            </p>
            <button type="button" class="account-button danger" onclick="openPopup('deleteAccountPopup')">Delete My Account</button>
        <?php else: ?>
            <p class="danger-text">Only customer accounts can be deleted from this page.</p>
        <?php endif; ?>
    </div>
    </div>

</section>

<?php if ($userRole === 'Customer'): ?>
    <div class="popup" id="deleteAccountPopup">
        <div class="popup-box delete-box">
            <button type="button" class="close" onclick="closePopup('deleteAccountPopup')">x</button>
            <h2>Delete account?</h2>
            <p>Enter your password to confirm. This cannot be undone.</p>

            <form action="pages/account_actions.php" method="POST" class="account-form" novalidate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_account">

                <div class="form-row">
                    <input type="password" id="deletePassword" name="password" placeholder="Your password" autocomplete="current-password" required>
                </div>

                <div class="delete-actions">
                    <button type="button" class="account-button secondary" onclick="closePopup('deleteAccountPopup')">Cancel</button>
                    <button type="submit" class="account-button danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>