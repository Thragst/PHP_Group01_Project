<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$flashSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security_settings'])) {
    $settingsToSave = [
        'max_failed_attempts'     => max(1, (int)($_POST['max_failed_attempts'] ?? 3)),
        'min_password_length'     => max(8, (int)($_POST['min_password_length'] ?? 12)),
        'session_timeout_minutes' => max(1, (int)($_POST['session_timeout_min'] ?? 2)),
        'require_uppercase'       => isset($_POST['require_uppercase']) ? '1' : '0',
        'require_lowercase'       => isset($_POST['require_lowercase']) ? '1' : '0',
        'require_number'          => isset($_POST['require_number']) ? '1' : '0',
        'require_special'         => isset($_POST['require_special']) ? '1' : '0'
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
        <p class="text-muted-sm">Customize dynamic authentication rules, lockout thresholds, and session timeouts.</p>
    </div>
</div>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert-banner alert-success">
        ✓ <?php echo htmlspecialchars($flashSuccess); ?>
    </div>
<?php endif; ?>

<form action="security-settings.php" method="POST">
    <div class="security-grid">
        
        <div class="admin-card">
            <div class="card-title-bar">
                <div class="card-title-icon">⚙️</div>
                <h3>Access Thresholds</h3>
            </div>
            
            <div class="form-group mb-20">
                <label>Max Failed Login Attempts</label>
                <p class="form-field-note">Locks account after consecutive failures</p>
                <input type="number" name="max_failed_attempts" value="<?php echo htmlspecialchars($settings['max_failed_attempts']); ?>" min="1" max="10" required>
            </div>

            <div class="form-group mb-20">
                <label>Session Inactivity Timeout (Minutes)</label>
                <p class="form-field-note">Auto-logout duration for idle administrative users</p>
                <input type="number" name="session_timeout_min" value="<?php echo htmlspecialchars($settings['session_timeout_minutes']); ?>" min="1" max="60" required>
            </div>
        </div>

        <div class="admin-card">
            <div class="card-title-bar">
                <div class="card-title-icon-dark">🔒</div>
                <h3>Password Integrity Rules</h3>
            </div>

            <div class="form-group mb-20">
                <label>Minimum Password Length</label>
                <input type="number" name="min_password_length" value="<?php echo htmlspecialchars($settings['min_password_length']); ?>" min="8" max="32" required>
            </div>

            <label class="label-bold">Required Characters</label>
            <div class="checkbox-group-container">
                <label class="checkbox-label">
                    <input type="checkbox" name="require_uppercase" value="1" <?php echo $settings['require_uppercase'] === '1' ? 'checked' : ''; ?>>
                    <span>Uppercase Letter (A-Z)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="require_lowercase" value="1" <?php echo $settings['require_lowercase'] === '1' ? 'checked' : ''; ?>>
                    <span>Lowercase Letter (a-z)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="require_number" value="1" <?php echo $settings['require_number'] === '1' ? 'checked' : ''; ?>>
                    <span>Numeric Digit (0-9)</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="require_special" value="1" <?php echo $settings['require_special'] === '1' ? 'checked' : ''; ?>>
                    <span>Special Character (!@#$%)</span>
                </label>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="save_security_settings" class="btn-primary btn-submit-large">Save All Policies</button>
    </div>
</form>

<?php echo '</main></div></div></body></html>'; ?>