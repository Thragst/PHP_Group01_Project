<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['Admin', 'Developer']);

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../pages/login.php');
    exit();
}

$currentScript = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 25, 50, 100], true) ? (int)$_GET['per_page'] : 10;
$currentPage = isset($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

if (!in_array($roleFilter, ['Admin', 'Staff', 'Developer', 'Driver', 'Customer'], true)) {
    $roleFilter = '';
}

$baseWhereClauses = [];
$baseParams = [];

if ($search !== '') {
    $baseWhereClauses[] = "(al.action_description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
    $term = "%{$search}%";
    $baseParams[] = $term;
    $baseParams[] = $term;
    $baseParams[] = $term;
    $baseParams[] = $term;
}

if ($roleFilter !== '') {
    $baseWhereClauses[] = "u.role = ?";
    $baseParams[] = $roleFilter;
}

$sessionKeywords = ['%login%', '%logged out%', '%timeout%', '%inactivity%'];

$sessionWhere = $baseWhereClauses;
$sessionConditions = [];
foreach ($sessionKeywords as $kw) {
    $sessionConditions[] = "al.action_description LIKE ?";
}
$sessionWhere[] = '(' . implode(' OR ', $sessionConditions) . ')';
$sessionWhereSql = ' WHERE ' . implode(' AND ', $sessionWhere);

$sessionParams = array_merge($baseParams, $sessionKeywords);

$sessionCountQuery = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id" . $sessionWhereSql;
$sessionCountStmt = $pdo->prepare($sessionCountQuery);
$pos = 1;
foreach ($sessionParams as $p) {
    $sessionCountStmt->bindValue($pos++, $p, PDO::PARAM_STR);
}
$sessionCountStmt->execute();
$totalSessionLogs = (int)$sessionCountStmt->fetchColumn();

$totalSessionPages = max(1, (int)ceil($totalSessionLogs / $perPage));
$offset = ($currentPage - 1) * $perPage;

$sessionQuery = "SELECT 
            al.audit_log_id, 
            al.action_description, 
            al.ip_address, 
            al.created_at,
            u.full_name AS user_name,
            u.email,
            u.role AS user_role
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          {$sessionWhereSql}
          ORDER BY al.audit_log_id DESC
          LIMIT ? OFFSET ?";

$sessionStmt = $pdo->prepare($sessionQuery);
$pos = 1;
foreach ($sessionParams as $p) {
    $sessionStmt->bindValue($pos++, $p, PDO::PARAM_STR);
}
$sessionStmt->bindValue($pos++, (int)$perPage, PDO::PARAM_INT);
$sessionStmt->bindValue($pos, (int)$offset, PDO::PARAM_INT);
$sessionStmt->execute();
$sessionLogs = $sessionStmt->fetchAll();


$crudWhere = $baseWhereClauses;
$crudConditions = [];
foreach ($sessionKeywords as $kw) {
    $crudConditions[] = "al.action_description NOT LIKE ?";
}
$crudWhere[] = '(' . implode(' AND ', $crudConditions) . ')';
$crudWhereSql = ' WHERE ' . implode(' AND ', $crudWhere);

$crudParams = array_merge($baseParams, $sessionKeywords);

$crudCountQuery = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id" . $crudWhereSql;
$crudCountStmt = $pdo->prepare($crudCountQuery);
$pos = 1;
foreach ($crudParams as $p) {
    $crudCountStmt->bindValue($pos++, $p, PDO::PARAM_STR);
}
$crudCountStmt->execute();
$totalCrudLogs = (int)$crudCountStmt->fetchColumn();

$crudQuery = "SELECT 
            al.audit_log_id, 
            al.action_description, 
            al.ip_address, 
            al.created_at,
            u.full_name AS user_name,
            u.email,
            u.role AS user_role
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          {$crudWhereSql}
          ORDER BY al.audit_log_id DESC
          LIMIT ? OFFSET ?";

$crudStmt = $pdo->prepare($crudQuery);
$pos = 1;
foreach ($crudParams as $p) {
    $crudStmt->bindValue($pos++, $p, PDO::PARAM_STR);
}
$crudStmt->bindValue($pos++, (int)$perPage, PDO::PARAM_INT);
$crudStmt->bindValue($pos, (int)$offset, PDO::PARAM_INT);
$crudStmt->execute();
$crudLogs = $crudStmt->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header" style="margin-bottom: 25px;">
    <h2>System & Security Audit Logs</h2>
    <p class="text-muted-sm">Immutable activity log of user logins, order status modifications, and driver delivery updates.</p>
</div>

<form action="<?php echo $currentScript; ?>" method="GET" class="card-header-flex mb-20" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
        <input
            type="text"
            name="search"
            class="table-search-lg"
            placeholder="Search Action, User, Email, or IP..."
            value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
        >

        <select name="role" class="table-filter-lg" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <option value="Admin" <?php echo $roleFilter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="Staff" <?php echo $roleFilter === 'Staff' ? 'selected' : ''; ?>>Staff</option>
            <option value="Driver" <?php echo $roleFilter === 'Driver' ? 'selected' : ''; ?>>Driver</option>
            <option value="Customer" <?php echo $roleFilter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
        </select>

        <select name="per_page" class="table-filter-lg" onchange="this.form.submit()">
            <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
            <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
            <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
            <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 per page</option>
        </select>

        <button type="submit" class="btn-primary">Filter</button>
        <?php if ($search !== '' || $roleFilter !== '' || $perPage !== 10): ?>
            <a href="<?php echo $currentScript; ?>" class="btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="admin-card" style="margin-bottom: 30px;">
    <div style="padding: 15px 20px; border-bottom: 1px solid #eee;">
        <h3 style="margin: 0; font-size: 1.2rem;">User Session & Access Logs</h3>
        <span class="text-muted-sm">Logins, logouts, and inactivity timeouts.</span>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Log ID</th>
                <th>Timestamp</th>
                <th>User / Email</th>
                <th>Role</th>
                <th>Action Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($sessionLogs)): ?>
                <?php foreach ($sessionLogs as $log): ?>
                    <?php 
                        $formattedDate = date('M j, Y - g:i:s A', strtotime($log['created_at']));
                        $roleClass = htmlspecialchars(strtolower((string) $log['user_role']), ENT_QUOTES, 'UTF-8');
                        $displayUser = $log['user_name'] !== null ? htmlspecialchars((string) $log['user_name'], ENT_QUOTES, 'UTF-8') : '&mdash;';
                        if (!empty($log['email'])) {
                            $displayUser .= ' <span class="text-muted-sm">(' . htmlspecialchars((string) $log['email'], ENT_QUOTES, 'UTF-8') . ')</span>';
                        }
                    ?>
                    <tr>
                        <td>#LOG-<?php echo $log['audit_log_id']; ?></td>
                        <td><?php echo $formattedDate; ?></td>
                        <td><?php echo $displayUser; ?></td>
                        <td>
                            <?php if (!empty($log['user_role'])): ?>
                                <span class="badge badge-<?php echo $roleClass; ?>">
                                    <?php echo htmlspecialchars($log['user_role'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['action_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><code style="font-family: monospace; color: #555;"><?php echo htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="table-empty-notice">No session logs recorded matching criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <div style="padding: 15px 20px; border-bottom: 1px solid #eee;">
        <h3 style="margin: 0; font-size: 1.2rem;">System Activity & Management Logs</h3>
        <span class="text-muted-sm">Product modifications, order status updates, account unlocking, and CRUD operations.</span>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Log ID</th>
                <th>Timestamp</th>
                <th>User / Email</th>
                <th>Role</th>
                <th>Action Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($crudLogs)): ?>
                <?php foreach ($crudLogs as $log): ?>
                    <?php 
                        $formattedDate = date('M j, Y - g:i:s A', strtotime($log['created_at']));
                        $roleClass = htmlspecialchars(strtolower((string) $log['user_role']), ENT_QUOTES, 'UTF-8');
                        $displayUser = $log['user_name'] !== null ? htmlspecialchars((string) $log['user_name'], ENT_QUOTES, 'UTF-8') : '&mdash;';
                        if (!empty($log['email'])) {
                            $displayUser .= ' <span class="text-muted-sm">(' . htmlspecialchars((string) $log['email'], ENT_QUOTES, 'UTF-8') . ')</span>';
                        }
                    ?>
                    <tr>
                        <td>#LOG-<?php echo $log['audit_log_id']; ?></td>
                        <td><?php echo $formattedDate; ?></td>
                        <td><?php echo $displayUser; ?></td>
                        <td>
                            <?php if (!empty($log['user_role'])): ?>
                                <span class="badge badge-<?php echo $roleClass; ?>">
                                    <?php echo htmlspecialchars($log['user_role'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['action_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><code style="font-family: monospace; color: #555;"><?php echo htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="table-empty-notice">No system activity logs recorded matching criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalSessionPages > 1 || $totalSessionLogs > 0): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px; padding: 15px 20px;">
            <span class="text-muted-sm">Showing <?php echo $totalSessionLogs > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $perPage, $totalSessionLogs); ?> of <?php echo $totalSessionLogs; ?> session logs</span>
            <?php if ($totalSessionPages > 1): ?>
                <div style="display: flex; gap: 5px;">
                    <?php for ($i = 1; $i <= $totalSessionPages; $i++): ?>
                        <?php
                            $queryParams = $_GET;
                            $queryParams['p'] = $i;
                            $pageUrl = $currentScript . '?' . http_build_query($queryParams);
                        ?>
                        <a href="<?php echo $pageUrl; ?>" class="btn-secondary" style="<?php echo $i === $currentPage ? 'background-color: var(--primary-dark-red, #800000); color: #fff;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php echo '</main></div></div></body></html>'; ?>