<?php
session_start();
include 'includes/admin_header.php';

$mock_logs = [
    ['time' => '2026-10-06 13:40:12', 'user' => 'maria@example.com', 'event' => 'Account Lockout', 'badge' => 'badge-locked', 'ip' => '192.168.1.45', 'details' => '3 consecutive invalid password attempts'],
    ['time' => '2026-10-06 13:12:05', 'user' => 'admin@sportmart.ph', 'event' => 'Admin Login', 'badge' => 'badge-active', 'ip' => '192.168.1.10', 'details' => 'Successful login with CAPTCHA & MFA'],
    ['time' => '2026-10-06 12:00:00', 'user' => 'juan@example.com', 'event' => 'Session Timeout', 'badge' => 'badge-inactive', 'ip' => '192.168.1.22', 'details' => 'Auto-logout after 2 minutes of inactivity']
];
?>

<div class="page-header" style="margin-bottom: 25px;">
    <h2>System & Security Audit Logs</h2>
    <p style="color: #666; font-size: 0.95rem;">Immutable activity log of user logins, account lockouts, and admin actions.</p>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User / Email</th>
                <th>Event Type</th>
                <th>IP Address</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mock_logs as $log): ?>
                <tr>
                    <td><?php echo $log['time']; ?></td>
                    <td><strong><?php echo htmlspecialchars($log['user']); ?></strong></td>
                    <td><span class="badge <?php echo $log['badge']; ?>"><?php echo $log['event']; ?></span></td>
                    <td><span style="font-family: monospace; color: #555;"><?php echo $log['ip']; ?></span></td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php echo '</main></div></div></body></html>'; ?>