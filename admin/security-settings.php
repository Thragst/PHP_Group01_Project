<?php
session_start();
include 'includes/admin_header.php';

if (!isset($_SESSION['mock_security'])) {
    $_SESSION['mock_security'] = [
        'max_failed_attempts' => 3, 'min_password_length' => 12, 'session_timeout_min' => 2,
        'require_uppercase' => 1, 'require_lowercase' => 1, 'require_number' => 1, 'require_special' => 1
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security_settings'])) {
    $_SESSION['mock_security'] = [
        'max_failed_attempts' => (int)$_POST['max_failed_attempts'],
        'min_password_length' => (int)$_POST['min_password_length'],
        'session_timeout_min' => (int)$_POST['session_timeout_min'],
        'require_uppercase'   => isset($_POST['require_uppercase']) ? 1 : 0,
        'require_lowercase'   => isset($_POST['require_lowercase']) ? 1 : 0,
        'require_number'      => isset($_POST['require_number']) ? 1 : 0,
        'require_special'     => isset($_POST['require_special']) ? 1 : 0
    ];
    $success_msg = "Security policies updated successfully.";
}
$settings = $_SESSION['mock_security'];
?>

<div class="page-header">
    <div>
        <h2>Security & Policy Configuration</h2>
        <p style="color: #666; font-size: 0.95rem;">Customize dynamic authentication rules, lockout thresholds, and session timeouts.</p>
    </div>
</div>

<?php if(isset($success_msg)): ?>
    <div style="background-color: #d4edda; border-left: 5px solid #28a745; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500;">
        ✓ <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<form action="security-settings.php" method="POST">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <div class="admin-card">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                <div style="background: var(--primary-dark-red); color: white; padding: 8px 12px; border-radius: 6px;">⚙️</div>
                <h3 style="margin: 0;">Access Thresholds</h3>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Max Failed Login Attempts</label>
                <p style="font-size: 0.85rem; color: #777; margin-bottom: 8px;">Locks account after consecutive failures</p>
                <input type="number" name="max_failed_attempts" value="<?php echo $settings['max_failed_attempts']; ?>" min="1" max="10" required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Session Inactivity Timeout (Minutes)</label>
                <p style="font-size: 0.85rem; color: #777; margin-bottom: 8px;">Auto-logout duration for idle admins</p>
                <input type="number" name="session_timeout_min" value="<?php echo $settings['session_timeout_min']; ?>" min="1" max="60" required>
            </div>
        </div>

        <div class="admin-card">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                <div style="background: #262626; color: white; padding: 8px 12px; border-radius: 6px;">🔒</div>
                <h3 style="margin: 0;">Password Integrity Rules</h3>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Minimum Password Length</label>
                <input type="number" name="min_password_length" value="<?php echo $settings['min_password_length']; ?>" min="8" max="32" required>
            </div>

            <label style="font-weight: bold; font-size: 0.9rem; margin-top: 15px; display: block; margin-bottom: 10px;">Required Characters</label>
            <div style="display: flex; flex-direction: column; gap: 12px; background: #fcfcfc; padding: 15px; border: 1px solid var(--border-color); border-radius: 6px;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="require_uppercase" value="1" <?php echo $settings['require_uppercase'] ? 'checked' : ''; ?>>
                    <span>Uppercase Letter (A-Z)</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="require_lowercase" value="1" <?php echo $settings['require_lowercase'] ? 'checked' : ''; ?>>
                    <span>Lowercase Letter (a-z)</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="require_number" value="1" <?php echo $settings['require_number'] ? 'checked' : ''; ?>>
                    <span>Numeric Digit (0-9)</span>
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="require_special" value="1" <?php echo $settings['require_special'] ? 'checked' : ''; ?>>
                    <span>Special Character (!@#$%)</span>
                </label>
            </div>
        </div>
    </div>

    <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
        <button type="submit" name="save_security_settings" class="btn-primary" style="padding: 12px 25px; font-size: 1rem;">Save All Policies</button>
    </div>
</form>

<?php echo '</main></div></div></body></html>'; ?>