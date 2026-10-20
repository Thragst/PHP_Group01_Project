<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../pages/login.php');
    exit();
}

$currentScript = htmlspecialchars($_SERVER['PHP_SELF']);
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 25, 50, 100], true) ? (int)$_GET['per_page'] : 10;
$currentPage = isset($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(al.action_description LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($roleFilter !== '') {
    $whereClauses[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
}

$countQuery = "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id" . $whereSql;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalLogs / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$query = "SELECT 
            al.audit_log_id, 
            al.action_description, 
            al.ip_address, 
            al.created_at,
            COALESCE(u.full_name, 'System / Guest') AS user_name,
            u.email,
            COALESCE(u.role, 'System') AS user_role
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          {$whereSql}
          ORDER BY al.audit_log_id DESC
          LIMIT {$perPage} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header" style="margin-bottom: 25px;">
    <h2>System & Security Audit Logs</h2>
    <p class="text-muted-sm">Immutable activity log of user logins, order status modifications, and driver delivery updates.</p>
</div>

<div class="admin-card">
    <form action="<?php echo $currentScript; ?>" method="GET" class="card-header-flex mb-20">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input
                type="text"
                name="search"
                class="table-search-lg"
                placeholder="Search Action, User, Email, or IP..."
                value="<?php echo htmlspecialchars($search); ?>"
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
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <?php 
                        $formattedDate = date('M j, Y - g:i:s A', strtotime($log['created_at']));
                        $roleClass = strtolower($log['user_role']);
                        $displayUser = htmlspecialchars($log['user_name']);
                        if (!empty($log['email'])) {
                            $displayUser .= ' <span class="text-muted-sm">(' . htmlspecialchars($log['email']) . ')</span>';
                        }
                    ?>
                    <tr>
                        <td>#LOG-<?php echo $log['audit_log_id']; ?></td>
                        <td><?php echo $formattedDate; ?></td>
                        <td><?php echo $displayUser; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $roleClass; ?>">
                                <?php echo htmlspecialchars($log['user_role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                        <td><code style="font-family: monospace; color: #555;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="table-empty-notice">No activity logs recorded matching criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1 || $totalLogs > 0): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">
            <span class="text-muted-sm">Showing <?php echo $totalLogs > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs</span>
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; gap: 5px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
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