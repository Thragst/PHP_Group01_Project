<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit_helper.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_staff' || $action === 'update_staff') {
        $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $fullName = trim($_POST['staff_name'] ?? '');
        $email = trim($_POST['staff_email'] ?? '');
        $role = in_array($_POST['staff_role'] ?? '', ['Admin', 'Staff', 'Driver'], true) ? $_POST['staff_role'] : 'Staff';
        $status = in_array($_POST['staff_status'] ?? '', ['Active', 'Suspended'], true) ? $_POST['staff_status'] : 'Active';
        $password = $_POST['staff_password'] ?? '';

        if (!empty($fullName) && !empty($email)) {
            if ($action === 'create_staff') {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetchColumn() > 0) {
                    $_SESSION['flash_error'] = "Email address is already in use.";
                } else {
                    $username = explode('@', $email)[0];
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, role, password_hash, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                    $stmt->execute([$username, $fullName, $email, $role, $passwordHash]);
                    $newUserId = $pdo->lastInsertId();
                    logAudit($pdo, $_SESSION['user_id'], "Created new " . $role . " account for " . $email . " (User ID #" . $newUserId . ")");
                    $_SESSION['flash_success'] = "New $role account created successfully.";
                }
            } else if ($action === 'update_staff' && $userId) {
                if (!empty($password)) {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, password_hash = ? WHERE user_id = ?");
                    $stmt->execute([$fullName, $email, $role, $status, $passwordHash, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE user_id = ?");
                    $stmt->execute([$fullName, $email, $role, $status, $userId]);
                }
                logAudit($pdo, $_SESSION['user_id'], "Updated permissions/details for " . $role . " account: " . $fullName . " (User ID #" . $userId . ")");
                $_SESSION['flash_success'] = "Account permissions updated successfully.";
            }
        }
    } elseif ($action === 'unlock_customer') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'Active', failed_attempts = 0 WHERE user_id = ? AND role = 'Customer'");
            $stmt->execute([$userId]);
            logAudit($pdo, $_SESSION['user_id'], "Admin unlocked customer account for User ID #" . $userId);
            $_SESSION['flash_success'] = "Customer account unlocked successfully.";
        }
    }

    header('Location: users.php');
    exit();
}

$staffStmt = $pdo->query("SELECT user_id, username, full_name, email, role, status FROM users WHERE role IN ('Admin', 'Staff', 'Driver') ORDER BY user_id ASC");
$staffMembers = $staffStmt->fetchAll();

$customerQuery = "SELECT 
                    u.user_id, 
                    u.username,
                    u.full_name, 
                    u.email, 
                    u.status, 
                    u.failed_attempts,
                    u.phone,
                    u.physical_address,
                    COUNT(o.order_id) AS total_orders,
                    COALESCE(SUM(o.total_amount), 0) AS total_spent
                  FROM users u
                  LEFT JOIN orders o ON u.user_id = o.user_id
                  WHERE u.role = 'Customer'
                  GROUP BY u.user_id
                  ORDER BY u.user_id DESC";
$customers = $pdo->query($customerQuery)->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>User Account Management</h2>
        <p class="text-muted-sm">Manage registered customer profiles, driver accounts, and staff access roles.</p>
    </div>
    <button class="btn-primary" onclick="openStaffModal()">+ Create New Staff Account</button>
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

<div class="admin-card mb-30">
    <div class="card-header-flex">
        <h3>Internal Staff, Administrators & Drivers</h3>
        <div class="filter-flex-group">
            <input type="text" id="staffSearchInput" class="table-search-input" onkeyup="filterStaffTable()" placeholder="Search staff or email...">
            <select id="staffRoleFilter" class="table-filter-select" onchange="filterStaffTable()">
                <option value="all">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="Staff">Staff</option>
                <option value="Driver">Driver</option>
            </select>
        </div>
    </div>

    <table class="admin-table">
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
            <?php if (!empty($staffMembers)): ?>
                <?php foreach ($staffMembers as $staff): ?>
                    <tr class="staff-row">
                        <td class="staff-name"><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                        <td class="staff-email"><?php echo htmlspecialchars($staff['email']); ?></td>
                        <td class="staff-role"><span class="role-text"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($staff['status'] ?? 'active') === 'active' ? 'active' : 'locked'; ?>">
                                <?php echo htmlspecialchars($staff['status'] ?? 'Active'); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-action" onclick="openEditPermissionsModal(<?php echo htmlspecialchars(json_encode($staff, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Edit Permissions</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="table-empty-notice">No staff or driver accounts found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <div class="card-header-flex">
        <h3>Registered Customers</h3>
        <div class="filter-flex-group">
            <input type="text" id="customerSearchInput" class="table-search-input" onkeyup="filterCustomerTable()" placeholder="Search customer name or email...">
            <select id="customerStatusFilter" class="table-filter-select" onchange="filterCustomerTable()">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="locked">Locked</option>
            </select>
        </div>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($customers)): ?>
                <?php foreach ($customers as $user): ?>
                    <?php 
                        $isLocked = (strpos(strtolower($user['status'] ?? ''), 'locked') !== false) || ((int)($user['failed_attempts'] ?? 0) >= 3);
                        $statusBadgeClass = $isLocked ? 'badge-locked' : 'badge-active';
                        $statusLabel = $isLocked ? 'Locked (' . ($user['failed_attempts'] ?? 0) . ' Failed Attempts)' : 'Active';
                    ?>
                    <tr class="customer-row">
                        <td class="cust-name"><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td class="cust-email"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="cust-status"><span class="badge <?php echo $statusBadgeClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                        <td>
                            <div class="action-flex-group">
                                <button type="button" class="btn-action" onclick="openCustomerProfileModal(<?php echo htmlspecialchars(json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">View Profile</button>
                                <?php if ($isLocked): ?>
                                    <form action="users.php" method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="unlock_customer">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn-action btn-unlock">Unlock Account</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="table-empty-notice">No customer records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="staffModal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 id="staffModalTitle">Create Staff Account</h3>
            <button class="modal-close" onclick="closeModal('staffModal')" type="button">&times;</button>
        </div>
        <form action="users.php" method="POST" id="staffForm">
            <input type="hidden" name="action" id="staffAction" value="create_staff">
            <input type="hidden" name="user_id" id="staffUserId">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="staff_name" id="staffName" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="staff_email" id="staffEmail" required>
            </div>

            <div class="form-group">
                <label>Assign Role</label>
                <select name="staff_role" id="staffRole" required>
                    <option value="Admin">Admin</option>
                    <option value="Staff">Staff</option>
                    <option value="Driver">Driver</option>
                </select>
            </div>

            <div class="form-group hidden-element" id="staffStatusGroup">
                <label>Account Status</label>
                <select name="staff_status" id="staffStatus">
                    <option value="Active">Active</option>
                    <option value="Suspended">Suspended</option>
                </select>
            </div>

            <div class="form-group">
                <label id="staffPasswordLabel">Password</label>
                <input type="password" name="staff_password" id="staffPassword">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('staffModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="customerProfileModal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h3>Customer Profile Overview</h3>
            <button class="modal-close" onclick="closeModal('customerProfileModal')" type="button">&times;</button>
        </div>
        <div>
            <div class="profile-detail-row">
                <strong>Name:</strong>
                <span id="profileName"></span>
            </div>
            <div class="profile-detail-row">
                <strong>Username:</strong>
                <span id="profileUsername"></span>
            </div>
            <div class="profile-detail-row">
                <strong>Email:</strong>
                <span id="profileEmail"></span>
            </div>
            <div class="profile-detail-row">
                <strong>Registered Date:</strong>
                <span id="profileDate"></span>
            </div>
            <div class="profile-detail-row">
                <strong>Account Status:</strong>
                <span id="profileStatus"></span>
            </div>
            <div class="profile-detail-row">
                <strong>Contact Number:</strong>
                <span id="profilePhone"></span>
            </div>
            <div class="profile-address-block">
                <strong>Shipping Address:</strong>
                <p id="profileAddress" class="profile-address-text"></p>
            </div>
            <div class="profile-stats-grid">
                <div class="profile-stat-item">
                    <small>Total Orders</small>
                    <strong id="profileOrdersCount" class="profile-stat-value">0</strong>
                </div>
                <div class="profile-stat-item">
                    <small>Total Spent</small>
                    <strong id="profileTotalSpent" class="profile-stat-value text-primary-red">₱0.00</strong>
                </div>
                <div class="profile-stat-item">
                    <small>Failed Logins</small>
                    <strong id="profileFailedAttempts" class="profile-stat-value text-danger-red">0</strong>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('customerProfileModal')">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>

<?php echo '</main></div></div></body></html>'; ?>