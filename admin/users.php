<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['Admin', 'Developer']);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Developer'], true)) {
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
    $rawSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $activePolicy = array_merge($defaultPolicy, $rawSettings ?: []);
} catch (Exception $e) {
    $activePolicy = $defaultPolicy;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securityCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_staff' || $action === 'update_staff') {
        $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $fullName = trim($_POST['staff_name'] ?? '');
        $email = strtolower(trim($_POST['staff_email'] ?? ''));
        $role = in_array($_POST['staff_role'] ?? '', ['Admin', 'Staff', 'Driver'], true) ? $_POST['staff_role'] : 'Staff';
        $status = in_array($_POST['staff_status'] ?? '', ['Active', 'Suspended', 'Locked'], true) ? $_POST['staff_status'] : 'Active';
        $password = $_POST['staff_password'] ?? '';

        if (empty($fullName) || empty($email)) {
            $_SESSION['flash_error'] = "All required fields must be filled out.";
            header('Location: users.php');
            exit();
        }

        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Please provide a valid email address.";
            header('Location: users.php');
            exit();
        }


        $shouldValidatePassword = ($action === 'create_staff') || ($action === 'update_staff' && !empty($password));

        if ($shouldValidatePassword) {
            $minLen   = (int)($activePolicy['min_password_length'] ?? 12);
            $reqUpper = (int)($activePolicy['require_uppercase'] ?? 1);
            $reqLower = (int)($activePolicy['require_lowercase'] ?? 1);
            $reqNum   = (int)($activePolicy['require_number'] ?? 1);
            $reqSpec  = (int)($activePolicy['require_special'] ?? 1);

            $upperCount = preg_match_all('/[A-Z]/', $password);
            $lowerCount = preg_match_all('/[a-z]/', $password);
            $numCount   = preg_match_all('/[0-9]/', $password);
            $specCount  = preg_match_all('/[^A-Za-z0-9]/', $password);

            if (
                strlen($password) < $minLen ||
                $upperCount < $reqUpper ||
                $lowerCount < $reqLower ||
                $numCount < $reqNum ||
                $specCount < $reqSpec
            ) {
                $_SESSION['flash_error'] = "Password does not meet the specified security requirements.";
                header('Location: users.php');
                exit();
            }
        }

        if ($action === 'create_staff') {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Email address is already in use.";
            } else {
                $username = explode('@', $email)[0];
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, role, password_hash, status, account_status) VALUES (?, ?, ?, ?, ?, 'Pending', 'Pending')");
                $stmt->execute([$username, $fullName, $email, $role, $passwordHash]);
                $newUserId = $pdo->lastInsertId();

                if (function_exists('createActivationToken') && function_exists('sendActivationEmail')) {
                    $token = createActivationToken($pdo, $email);
                    sendActivationEmail($email, $fullName, $token);
                }

                logAudit($pdo, $_SESSION['user_id'], "Created new " . $role . " account for " . $email . " (User ID #" . $newUserId . ")");
                $_SESSION['flash_success'] = "New $role account created successfully. An activation link has been sent to " . htmlspecialchars($email);
            }
        } else if ($action === 'update_staff' && $userId) {
            $extraSql = ($status === 'Active') ? ", failed_attempts = 0" : "";
            
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ?, password_hash = ? {$extraSql} WHERE user_id = ?");
                $stmt->execute([$fullName, $email, $role, $status, $passwordHash, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ? {$extraSql} WHERE user_id = ?");
                $stmt->execute([$fullName, $email, $role, $status, $userId]);
            }
            logAudit($pdo, $_SESSION['user_id'], "Updated permissions/details for " . $role . " account: " . $fullName . " (User ID #" . $userId . ")");
            $_SESSION['flash_success'] = "Account permissions updated successfully.";
        }
    } elseif ($action === 'unlock_staff') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'Active', failed_attempts = 0 WHERE user_id = ? AND role IN ('Admin', 'Staff', 'Driver')");
            $stmt->execute([$userId]);
            logAudit($pdo, $_SESSION['user_id'], "Admin unlocked staff/admin account for User ID #" . $userId);
            $_SESSION['flash_success'] = "Account unlocked successfully.";
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

$staffStmt = $pdo->query("SELECT user_id, username, full_name, email, role, status, failed_attempts FROM users WHERE role IN ('Admin', 'Staff', 'Driver') ORDER BY user_id ASC");
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
$customers = decryptRows($pdo->query($customerQuery)->fetchAll(), ['phone', 'physical_address']);

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
                    <?php 
                        $isStaffLocked = (strpos(strtolower($staff['status'] ?? ''), 'locked') !== false) || ((int)($staff['failed_attempts'] ?? 0) >= 3);
                        $staffStatusBadgeClass = $isStaffLocked ? 'badge-locked' : 'badge-active';
                        $staffStatusLabel = $isStaffLocked ? 'Locked' : htmlspecialchars($staff['status'] ?? 'Active');
                    ?>
                    <tr class="staff-row">
                        <td class="staff-name"><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                        <td class="staff-email"><?php echo htmlspecialchars($staff['email']); ?></td>
                        <td class="staff-role"><span class="role-text"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                        <td>
                            <span class="badge <?php echo $staffStatusBadgeClass; ?>">
                                <?php echo $staffStatusLabel; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-flex-group">
                                <button class="btn-action" onclick="openEditPermissionsModal(<?php echo securityJs($staff); ?>)">Edit Permissions</button>
                                <?php if ($isStaffLocked): ?>
                                    <form action="users.php" method="POST" class="inline-form">
                                        <?php echo securityField(); ?>
                                        <input type="hidden" name="action" value="unlock_staff">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $staff['user_id']; ?>">
                                        <button type="submit" class="btn-action btn-unlock">Unlock Account</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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
                                        <?php echo securityField(); ?>
                                        <input type="hidden" name="action" value="unlock_customer">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
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
        <form action="users.php" method="POST" id="staffForm" novalidate>
            <?php echo securityField(); ?>
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
                    <option value="Locked">Locked</option>
                </select>
            </div>

            <div class="form-group">
                <label id="staffPasswordLabel">Password</label>
                <input type="password" name="staff_password" id="staffPassword" required>

                <ul class="password-rules" id="passwordRules">
                    <li id="ruleLength"> At least <?php echo (int)($activePolicy['min_password_length'] ?? 12); ?> characters in length </li>
                    <?php if ((int)($activePolicy['require_uppercase'] ?? 1) > 0): ?>
                        <li id="ruleUpper"> At least <?php echo (int)$activePolicy['require_uppercase']; ?> CAPITAL letter(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_lowercase'] ?? 1) > 0): ?>
                        <li id="ruleLower"> At least <?php echo (int)$activePolicy['require_lowercase']; ?> small letter(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_number'] ?? 1) > 0): ?>
                        <li id="ruleNumber"> At least <?php echo (int)$activePolicy['require_number']; ?> number(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_special'] ?? 1) > 0): ?>
                        <li id="ruleSpecial"> At least <?php echo (int)$activePolicy['require_special']; ?> special character(s) </li>
                    <?php endif; ?>
                </ul>

                <!-- Warning notice positioned directly underneath password rules checklist -->
                <p class="signup-error" id="staffModalError" style="color: #dc3545; font-size: 0.88rem; margin-top: 10px; margin-bottom: 0; display: none;"></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('staffModal')">Cancel</button>
                <button type="submit" class="btn-primary" id="staffSubmitBtn">Create Account</button>
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

<script>
window.passwordPolicy = {
    minLen: <?php echo (int)($activePolicy['min_password_length'] ?? 12); ?>,
    reqUpper: <?php echo (int)($activePolicy['require_uppercase'] ?? 1); ?>,
    reqLower: <?php echo (int)($activePolicy['require_lowercase'] ?? 1); ?>,
    reqNum: <?php echo (int)($activePolicy['require_number'] ?? 1); ?>,
    reqSpec: <?php echo (int)($activePolicy['require_special'] ?? 1); ?>
};

<?php if (!empty($flashError)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const errBox = document.getElementById('staffModalError');
    if (errBox) {
        errBox.textContent = '⚠️ <?php echo htmlspecialchars($flashError); ?>';
        errBox.style.display = 'block';
    }
    openStaffModal();
});
<?php endif; ?>
</script>
<script src="../assets/js/admin.js"></script>

<?php echo '</main></div></div></body></html>'; ?>