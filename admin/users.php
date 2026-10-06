<?php
session_start();
include 'includes/admin_header.php';

if (isset($_SESSION['mock_staff']) && $_SESSION['mock_staff'][0]['role'] === 'Super Admin') {
    unset($_SESSION['mock_staff']);
}

if (!isset($_SESSION['mock_customers'])) {
    $_SESSION['mock_customers'] = [
        ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'date' => '2026-09-08', 'status' => 'Active', 'badge' => 'badge-active'],
        ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'date' => '2026-07-08', 'status' => 'Locked (3 Failed Attempts)', 'badge' => 'badge-locked']
    ];
}

if (!isset($_SESSION['mock_staff'])) {
    $_SESSION['mock_staff'] = [
        ['name' => 'Jeam Paul B. Malaba', 'email' => 'admin@sportmart.ph', 'role' => 'Admin', 'status' => 'Active', 'badge' => 'badge-active'],
        ['name' => 'Martinez', 'email' => 'martinez@sportmart.ph', 'role' => 'Staff', 'status' => 'Active', 'badge' => 'badge-active']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_email'])) {
    foreach ($_SESSION['mock_customers'] as &$c) {
        if ($c['email'] === $_POST['unlock_email']) { 
            $c['status'] = 'Active'; 
            $c['badge'] = 'badge-active'; 
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $_SESSION['mock_staff'][] = [
        'name' => $_POST['staff_name'],
        'email' => $_POST['staff_email'],
        'role' => $_POST['staff_role'],
        'status' => 'Active',
        'badge' => 'badge-active'
    ];
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2>User Account Management</h2>
        <p style="color: #666; font-size: 0.95rem;">Manage registered customer profiles and staff access roles.</p>
    </div>
    <button class="btn-primary" onclick="document.getElementById('staffModal').classList.add('active')">+ Create New Staff Account</button>
</div>

<div class="admin-card" style="margin-bottom: 30px;">
    <h3>Internal Staff & Administrators</h3>
    <table class="admin-table" style="margin-top: 15px;">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['mock_staff'] as $staff): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($staff['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                    <td><span style="font-weight: 600; color: var(--primary-dark-red);"><?php echo $staff['role']; ?></span></td>
                    <td><span class="badge <?php echo $staff['badge']; ?>"><?php echo $staff['status']; ?></span></td>
                    <td><button class="btn-action">Edit Permissions</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <h3>Registered Customers</h3>
    <table class="admin-table" style="margin-top: 15px;">
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Registered Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['mock_customers'] as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo $user['date']; ?></td>
                    <td><span class="badge <?php echo $user['badge']; ?>"><?php echo $user['status']; ?></span></td>
                    <td>
                        <?php if (strpos($user['status'], 'Locked') !== false): ?>
                            <form action="users.php" method="POST" style="display:inline;">
                                <input type="hidden" name="unlock_email" value="<?php echo $user['email']; ?>">
                                <button type="submit" class="btn-action btn-unlock">Unlock Account</button>
                            </form>
                        <?php else: ?>
                            <button class="btn-action">View Profile</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="staffModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Create Staff Account</h3>
            <button class="modal-close" onclick="document.getElementById('staffModal').classList.remove('active')" type="button">&times;</button>
        </div>
        <form action="users.php" method="POST">
            <input type="hidden" name="create_staff" value="1">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="staff_name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="staff_email" required>
            </div>
            <div class="form-group">
                <label>Assign Role</label>
                <select name="staff_role" required>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                </select>
            </div>
            <div class="form-group">
                <label>Temporary Password</label>
                <input type="password" name="staff_password" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-secondary" onclick="document.getElementById('staffModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>