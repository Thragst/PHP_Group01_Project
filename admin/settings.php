<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit_helper.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';

if ($userId <= 0 || !in_array($userRole, ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT username, full_name, email, role, phone, COALESCE(mfa_enabled, 0) AS mfa_enabled FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if ($account && function_exists('decryptRow')) {
    $account = decryptRow($account, ['phone']);
}

if (!$account) {
    header('Location: ../pages/login.php');
    exit();
}

$defaultPolicy = [
    'min_password_length' => '12',
    'require_uppercase'   => '1',
    'require_lowercase'   => '1',
    'require_number'      => '1',
    'require_special'     => '1'
];
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'require_%' OR setting_key LIKE '%password%'");
    $rawPolicy = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $policy = array_merge($defaultPolicy, $rawPolicy ?: []);
} catch (Exception $e) {
    $policy = $defaultPolicy;
}

$minLength  = (int) ($policy['min_password_length'] ?? 12);
$minUpper   = (int) ($policy['require_uppercase'] ?? 1);
$minLower   = (int) ($policy['require_lowercase'] ?? 1);
$minNumber  = (int) ($policy['require_number'] ?? 1);
$minSpecial = (int) ($policy['require_special'] ?? 1);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securityCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $passStmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $passStmt->execute([$userId]);
        $currentHash = $passStmt->fetchColumn();

        if (!password_verify($currentPass, $currentHash)) {
            $_SESSION['flash_error'] = "Current password is incorrect.";
        } elseif ($newPass !== $confirmPass) {
            $_SESSION['flash_error'] = "New password and confirm password do not match.";
        } else {
            $upperCount = preg_match_all('/[A-Z]/', $newPass);
            $lowerCount = preg_match_all('/[a-z]/', $newPass);
            $numCount   = preg_match_all('/[0-9]/', $newPass);
            $specCount  = preg_match_all('/[^A-Za-z0-9]/', $newPass);

            if (
                strlen($newPass) < $minLength ||
                $upperCount < $minUpper ||
                $lowerCount < $minLower ||
                $numCount < $minNumber ||
                $specCount < $minSpecial
            ) {
                $_SESSION['flash_error'] = "New password does not meet system security requirements.";
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $updateStmt->execute([$newHash, $userId]);

                logAudit($pdo, $userId, "User updated their own password from Account Settings.");
                $_SESSION['flash_success'] = "Your password has been updated successfully.";
            }
        }
        header('Location: settings.php');
        exit();
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Account Settings</h2>
        <p class="text-muted-sm">Manage your staff credentials, administrative security, and multi-factor authentication.</p>
    </div>
</div>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert-banner alert-success">
        ✓ <?php echo htmlspecialchars($flashSuccess); ?>
    </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
    <div class="alert-banner alert-error">
        ⚠️ <?php echo htmlspecialchars($flashError); ?>
    </div>
<?php endif; ?>

<div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">

    <div class="admin-card">
        <h3>Profile Details</h3>
        <div class="profile-detail-row" style="margin-top: 15px;">
            <strong>Full Name:</strong>
            <span><?php echo htmlspecialchars($account['full_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="profile-detail-row">
            <strong>Username:</strong>
            <span><?php echo htmlspecialchars($account['username'] ?? 'N/A'); ?></span>
        </div>
        <div class="profile-detail-row">
            <strong>Email Address:</strong>
            <span><?php echo htmlspecialchars($account['email'] ?? 'N/A'); ?></span>
        </div>
        <div class="profile-detail-row">
            <strong>Assigned Role:</strong>
            <span class="badge badge-active"><?php echo htmlspecialchars($account['role'] ?? 'Staff'); ?></span>
        </div>
    </div>

    <div class="admin-card">
        <h3>Change Password</h3>
        <form action="settings.php" method="POST" id="changePasswordForm" style="margin-top: 15px;" novalidate>
            <?php echo securityField(); ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="currentPassword">Current Password</label>
                <input type="password" name="current_password" id="currentPassword" class="table-search-input" style="width:100%;" required>
            </div>

            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input type="password" name="new_password" id="newPassword" class="table-search-input" style="width:100%;" required>
                
                <ul class="password-rules" id="newPasswordRules" 
                    data-min-length="<?php echo $minLength; ?>" 
                    data-min-upper="<?php echo $minUpper; ?>"
                    data-min-lower="<?php echo $minLower; ?>"
                    data-min-number="<?php echo $minNumber; ?>"
                    data-min-special="<?php echo $minSpecial; ?>">
                    <li data-rule="length">At least <?php echo $minLength; ?> characters in length</li>
                    <li data-rule="upper">At least <?php echo $minUpper; ?> CAPITAL letter<?php echo $minUpper > 1 ? 's' : ''; ?></li>
                    <li data-rule="lower">At least <?php echo $minLower; ?> small letter<?php echo $minLower > 1 ? 's' : ''; ?></li>
                    <li data-rule="number">At least <?php echo $minNumber; ?> number<?php echo $minNumber > 1 ? 's' : ''; ?></li>
                    <li data-rule="special">At least <?php echo $minSpecial; ?> special character<?php echo $minSpecial > 1 ? 's' : ''; ?></li>
                </ul>
            </div>

            <div class="form-group">
                <label for="confirmNewPassword">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmNewPassword" class="table-search-input" style="width:100%;" required>
                <small id="confirmNewMessage" style="font-size: 0.85rem; margin-top: 4px; display: block;"></small>
            </div>

            <p class="signup-error" id="settingsFormError" style="color: #dc3545; font-size: 0.88rem; margin-bottom: 12px; display: none;"></p>

            <button type="submit" class="btn-primary" style="width: 100%;">Update Password</button>
        </form>
    </div>

    <div class="admin-card">
        <h3>Two-Factor Authentication (MFA)</h3>
        <div style="margin-top: 15px;">
            <?php if (!empty($account['mfa_enabled'])): ?>
                <div class="mfa-status enabled" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span class="mfa-status-dot" style="height: 12px; width: 12px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span>
                    <div>
                        <strong>MFA is Currently Enabled</strong>
                        <p class="text-muted-sm">Your back-office account is protected with TOTP two-factor authentication.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="mfa-status disabled" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span class="mfa-status-dot" style="height: 12px; width: 12px; background-color: #dc3545; border-radius: 50%; display: inline-block;"></span>
                    <div>
                        <strong>MFA is Disabled</strong>
                        <p class="text-muted-sm">Enhance system security by linking an authenticator app (e.g., Google Authenticator, Authy).</p>
                    </div>
                </div>
                <a href="../mfa/setup.php" class="btn-primary" style="display: inline-block; text-align: center; text-decoration: none;">Enable MFA Authentication</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const newPassInput = document.getElementById('newPassword');
    const confirmPassInput = document.getElementById('confirmNewPassword');
    const rulesContainer = document.getElementById('newPasswordRules');
    const confirmMsg = document.getElementById('confirmNewMessage');
    const form = document.getElementById('changePasswordForm');
    const errBox = document.getElementById('settingsFormError');

    if (newPassInput && rulesContainer) {
        newPassInput.addEventListener('input', function () {
            const val = this.value;

            const minLen   = parseInt(rulesContainer.dataset.minLength || 12, 10);
            const minUpper = parseInt(rulesContainer.dataset.minUpper || 1, 10);
            const minLower = parseInt(rulesContainer.dataset.minLower || 1, 10);
            const minNum   = parseInt(rulesContainer.dataset.minNumber || 1, 10);
            const minSpec  = parseInt(rulesContainer.dataset.minSpecial || 1, 10);

            const upperCount = (val.match(/[A-Z]/g) || []).length;
            const lowerCount = (val.match(/[a-z]/g) || []).length;
            const numCount   = (val.match(/[0-9]/g) || []).length;
            const specCount  = (val.match(/[^A-Za-z0-9]/g) || []).length;

            const toggleRule = (selector, isValid) => {
                const el = rulesContainer.querySelector(selector);
                if (!el) return;
                if (isValid) {
                    el.classList.add('valid');
                    el.classList.remove('invalid');
                } else {
                    el.classList.remove('valid');
                }
            };

            toggleRule('[data-rule="length"]', val.length >= minLen);
            toggleRule('[data-rule="upper"]', upperCount >= minUpper);
            toggleRule('[data-rule="lower"]', lowerCount >= minLower);
            toggleRule('[data-rule="number"]', numCount >= minNum);
            toggleRule('[data-rule="special"]', specCount >= minSpec);
        });
    }

    if (confirmPassInput && newPassInput) {
        confirmPassInput.addEventListener('input', function () {
            if (!this.value) {
                confirmMsg.textContent = '';
                return;
            }
            if (this.value === newPassInput.value) {
                confirmMsg.textContent = '✓ Passwords match';
                confirmMsg.style.color = '#28a745';
            } else {
                confirmMsg.textContent = '✕ Passwords do not match';
                confirmMsg.style.color = '#dc3545';
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            if (errBox) errBox.style.display = 'none';

            if (newPassInput.value !== confirmPassInput.value) {
                e.preventDefault();
                errBox.textContent = '⚠️ New password and confirm password do not match.';
                errBox.style.display = 'block';
            }
        });
    }
});
</script>

<?php echo '</main></div></div></body></html>'; ?>