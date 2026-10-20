<?php
session_start();

require_once '../includes/db.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff'])) {
    $_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Developer';
    $_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Developer';
}

$salesStmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales, COUNT(order_id) AS total_orders FROM orders WHERE status != 'Cancelled'");
$salesData = $salesStmt->fetch();
$totalSales = $salesData['total_sales'];
$totalOrders = $salesData['total_orders'];

$usersStmt = $pdo->query("SELECT COUNT(user_id) AS total_users FROM users");
$totalUsers = $usersStmt->fetch()['total_users'];

$activityStmt = $pdo->query("SELECT COUNT(audit_log_id) AS total_activity FROM audit_logs");
$totalActivity = $activityStmt->fetch()['total_activity'];

$topSearchesStmt = $pdo->query("SELECT keyword_searched, COUNT(*) AS search_count 
                                FROM search_analytics 
                                WHERE results_found > 0 
                                GROUP BY keyword_searched 
                                ORDER BY search_count DESC 
                                LIMIT 4");
$topSearches = $topSearchesStmt->fetchAll();

$zeroResultsStmt = $pdo->query("SELECT keyword_searched, COUNT(*) AS attempts 
                                 FROM search_analytics 
                                 WHERE results_found = 0 
                                 GROUP BY keyword_searched 
                                 ORDER BY attempts DESC 
                                 LIMIT 3");
$zeroResults = $zeroResultsStmt->fetchAll();

include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Admin Dashboard</h2>
        <p class="text-muted-sm">Platform-wide sales, site activity, users, and search insights</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h4>Platform-Wide Sales</h4>
        <div class="stat-value">₱<?= number_format($totalSales, 2) ?></div>
    </div>
    <div class="stat-card">
        <h4>Site Activity</h4>
        <div class="stat-value"><?= number_format($totalActivity) ?> Logs</div>
    </div>
    <div class="stat-card">
        <h4>Total Users</h4>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
    </div>
    <div class="stat-card">
        <h4>Total Orders</h4>
        <div class="stat-value"><?= number_format($totalOrders) ?></div>
    </div>
</div>

<div class="insights-container">
    <div class="insight-card">
        <h3>Top Searched Keywords</h3>
        <ul class="insight-list">
            <?php if (!empty($topSearches)): ?>
                <?php foreach ($topSearches as $index => $search): ?>
                    <li>
                        <span><?= ($index + 1) ?>. <?= htmlspecialchars($search['keyword_searched']) ?></span>
                        <strong class="text-primary-red"><?= $search['search_count'] ?> searches</strong>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li><span>No search data available yet.</span></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="insight-card">
        <h3>Queries with 0 Results</h3>
        <ul class="insight-list">
            <?php if (!empty($zeroResults)): ?>
                <?php foreach ($zeroResults as $zero): ?>
                    <li>
                        <span><?= htmlspecialchars($zero['keyword_searched']) ?></span>
                        <span class="text-subtle-sm"><?= $zero['attempts'] ?> attempts</span>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li><span>No zero-result searches logged.</span></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>