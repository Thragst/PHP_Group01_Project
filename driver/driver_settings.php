<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit_helper.php';

$driverId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';

if ($driverId <= 0 || $userRole !== 'Driver') {
    header('Location: ../pages/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT username, full_name, email, role, phone, COALESCE(mfa_enabled, 0) AS mfa_enabled FROM users WHERE user_id = ? AND role = 'Driver' LIMIT 1");
$stmt->execute([$driverId]);
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
        $passStmt->execute([$driverId]);
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
                $updateStmt->execute([$newHash, $driverId]);

                logAudit($pdo, $driverId, "Driver updated their password from Driver Settings.");
                $_SESSION['flash_success'] = "Password updated successfully.";
            }
        }
        header('Location: driver_settings.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Account Settings - SportMart</title>
    <link rel="stylesheet" href="../assets/css/driver.css">
</head>
<body>

<div class="mobile-container">
    <div class="header">
        <div>
            <h2>⚙️ Driver Settings</h2>
            <span class="driver-name">Driver: <?php echo htmlspecialchars($account['username'] ?? 'Rider'); ?></span>
        </div>
        <div class="header-actions">
            <a href="deliveries.php" class="settings-btn">📦 Deliveries</a>
            <a href="../pages/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <?php if (!empty($flashSuccess)): ?>
        <div class="driver-alert alert-success">
            ✓ <?php echo htmlspecialchars($flashSuccess); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="driver-alert alert-error">
            ⚠️ <?php echo htmlspecialchars($flashError); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span>Profile Details</span>
        </div>
        <div class="details">
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($account['full_name']); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($account['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($account['email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($account['phone'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Change Password</span>
        </div>
        <form action="driver_settings.php" method="POST" id="driverPasswordForm" novalidate>
            <?php echo securityField(); ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group-mobile">
                <label>Current Password</label>
                <input type="password" name="current_password" id="currentPassword" class="select-input" required>
            </div>

            <div class="form-group-mobile">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPassword" class="select-input" required>

                <ul class="password-rules" id="newPasswordRules" 
                    data-min-length="<?php echo $minLength; ?>" 
                    data-min-upper="<?php echo $minUpper; ?>"
                    data-min-lower="<?php echo $minLower; ?>"
                    data-min-number="<?php echo $minNumber; ?>"
                    data-min-special="<?php echo $minSpecial; ?>">
                    <li data-rule="length">At least <?php echo $minLength; ?> characters</li>
                    <li data-rule="upper">At least <?php echo $minUpper; ?> CAPITAL letter(s)</li>
                    <li data-rule="lower">At least <?php echo $minLower; ?> small letter(s)</li>
                    <li data-rule="number">At least <?php echo $minNumber; ?> number(s)</li>
                    <li data-rule="special">At least <?php echo $minSpecial; ?> special character(s)</li>
                </ul>
            </div>

            <div class="form-group-mobile">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmNewPassword" class="select-input" required>
                <small id="confirmNewMessage" class="match-message"></small>
            </div>

            <p id="driverFormError" style="color: #dc3545; font-size: 0.85rem; margin-top: 8px; display: none;"></p>

            <button type="submit" class="btn-update" style="margin-top: 12px;">Update Password</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Two-Factor Authentication</span>
        </div>
        <div class="details" style="margin-top: 6px;">
            <?php if (!empty($account['mfa_enabled'])): ?>
                <p><strong style="color: #28a745;">✓ MFA is Currently Enabled</strong></p>
                <p>Your driver account is protected with an authenticator app.</p>
            <?php else: ?>
                <p><strong style="color: #dc3545;">✕ MFA is Disabled</strong></p>
                <p>Add an extra layer of security using an authenticator app.</p>
                <a href="../mfa/setup.php" class="btn-deliver" style="display: block; text-align: center; text-decoration: none; margin-top: 10px;">Enable MFA</a>
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
    const form = document.getElementById('driverPasswordForm');
    const errBox = document.getElementById('driverFormError');

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
                    el.style.color = '#28a745';
                } else {
                    el.style.color = '#666';
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
                errBox.textContent = '⚠️ Passwords do not match.';
                errBox.style.display = 'block';
            }
        });
    }
});
</script>

</body>
</html>