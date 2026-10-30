<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['Admin', 'Staff', 'Developer']);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$flashSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security_settings'])) {
    securityCheck();
    $settingsToSave = [
        'max_failed_attempts'     => max(1, (int)($_POST['max_failed_attempts'] ?? 3)),
        'min_password_length'     => max(8, (int)($_POST['min_password_length'] ?? 12)),
        'session_timeout_minutes' => max(1, (int)($_POST['session_timeout_min'] ?? 2)),
        'require_uppercase'       => max(0, (int)($_POST['require_uppercase'] ?? 1)),
        'require_lowercase'       => max(0, (int)($_POST['require_lowercase'] ?? 1)),
        'require_number'          => max(0, (int)($_POST['require_number'] ?? 1)),
        'require_special'         => max(0, (int)($_POST['require_special'] ?? 1))
    ];

    $upsertStmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($settingsToSave as $key => $val) {
        $upsertStmt->execute([$key, (string)$val]);
    }

    if (isset($_SESSION['user_id'])) {
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'],
            "Updated system security and password policies",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }

    $flashSuccess = "Security policies updated successfully.";
}

$defaultSettings = [
    'max_failed_attempts'     => '3',
    'min_password_length'     => '12',
    'session_timeout_minutes' => '2',
    'require_uppercase'       => '1',
    'require_lowercase'       => '1',
    'require_number'          => '1',
    'require_special'         => '1'
];

$rawSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = array_merge($defaultSettings, $rawSettings ?: []);

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Security & Policy Configuration</h2>
        <p class="text-muted-sm">Customize dynamic authentication rules, lockout thresholds, and password character counts.</p>
    </div>
</div>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert-banner alert-success">
        ✓ <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<form action="security-settings.php" method="POST">
    <?php echo securityField(); ?>
    <div class="security-grid">
        
        <div class="admin-card">
            <div class="card-title-bar">
                <div class="card-title-icon">⚙️</div>
                <h3>Access Thresholds</h3>
            </div>
            
            <div class="form-group mb-20">
                <label>Max Failed Login Attempts</label>
                <p class="form-field-note">Locks account after consecutive failures</p>
                <input type="number" name="max_failed_attempts" value="<?php echo htmlspecialchars($settings['max_failed_attempts'], ENT_QUOTES, 'UTF-8'); ?>" min="1" max="10" required>
            </div>

            <div class="form-group mb-20">
                <label>Session Inactivity Timeout (Minutes)</label>
                <p class="form-field-note">Auto-logout duration for idle administrative users</p>
                <input type="number" name="session_timeout_min" value="<?php echo htmlspecialchars($settings['session_timeout_minutes'], ENT_QUOTES, 'UTF-8'); ?>" min="1" max="60" required>
            </div>
        </div>

        <div class="admin-card">
            <div class="card-title-bar">
                <div class="card-title-icon-dark">🔒</div>
                <h3>Password Complexity Requirements</h3>
            </div>

            <div class="form-group mb-20">
                <label>Minimum Total Password Length</label>
                <input type="number" name="min_password_length" value="<?php echo htmlspecialchars($settings['min_password_length'], ENT_QUOTES, 'UTF-8'); ?>" min="8" max="32" required>
            </div>

            <div class="form-group mb-15">
                <label>Required Uppercase Letters (A-Z)</label>
                <input type="number" name="require_uppercase" value="<?php echo htmlspecialchars($settings['require_uppercase'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="10" required>
            </div>

            <div class="form-group mb-15">
                <label>Required Lowercase Letters (a-z)</label>
                <input type="number" name="require_lowercase" value="<?php echo htmlspecialchars($settings['require_lowercase'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="10" required>
            </div>

            <div class="form-group mb-15">
                <label>Required Numbers (0-9)</label>
                <input type="number" name="require_number" value="<?php echo htmlspecialchars($settings['require_number'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="10" required>
            </div>

            <div class="form-group mb-15">
                <label>Required Special Characters (!@#$%^&*...)</label>
                <input type="number" name="require_special" value="<?php echo htmlspecialchars($settings['require_special'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="10" required>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="save_security_settings" class="btn-primary btn-submit-large">Save All Policies</button>
    </div>
</form>

<?php echo '</main></div></div></body></html>'; ?>