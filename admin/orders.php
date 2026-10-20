<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $rawOrderId = trim($_POST['order_id']);
    $orderId = (int)str_replace('#ORD-', '', $rawOrderId);
    $newStatus = trim($_POST['new_status']);
    $driverId = !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
    
    $allowedStatuses = ['Pending', 'Processing', 'Out for Delivery', 'Cancelled'];

    if ($orderId >= 0 && in_array($newStatus, $allowedStatuses, true)) {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, driver_id = ? WHERE order_id = ?");
        $updateStmt->execute([$newStatus, $driverId, $orderId]);

        if (isset($_SESSION['user_id'])) {
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
            
            $driverInfo = $driverId ? " (Assigned to Driver ID: #$driverId)" : "";
            $logDetails = "Updated order #ORD-" . $orderId . " status to '" . $newStatus . "'" . $driverInfo;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            $logStmt->execute([
                $_SESSION['user_id'],
                $logDetails,
                $ipAddress
            ]);
        }
    }

    header('Location: orders.php');
    exit();
}

$driversQuery = "SELECT user_id, full_name FROM users WHERE role = 'Driver' AND account_status = 'Active' ORDER BY full_name ASC";
$driversList = $pdo->query($driversQuery)->fetchAll();

$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 25, 50, 100], true) ? (int)$_GET['per_page'] : 10;
$currentPage = isset($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = [];
$params = [];

if ($search !== '') {
    $cleanSearch = str_replace('#ORD-', '', $search);
    $whereClauses[] = "(o.order_id LIKE ? OR u.full_name LIKE ? OR d.full_name LIKE ?)";
    $term = "%{$cleanSearch}%";
    $params[] = $term;
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '') {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
}

$countQuery = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.user_id LEFT JOIN users d ON o.driver_id = d.user_id" . $whereSql;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$query = "SELECT 
            o.order_id, 
            o.created_at, 
            o.total_amount, 
            o.payment_method, 
            o.status, 
            o.driver_id,
            o.shipping_address,
            COALESCE(u.full_name, 'Guest Customer') AS customer_name,
            COALESCE(d.full_name, 'Unassigned') AS driver_name
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.user_id 
          LEFT JOIN users d ON o.driver_id = d.user_id 
          {$whereSql}
          ORDER BY o.order_id DESC
          LIMIT {$perPage} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
$orderIds = array_column($orders, 'order_id');
if (!empty($orderIds)) {
    $inClause = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsQuery = "SELECT oi.order_id, oi.quantity, oi.price_at_purchase, COALESCE(p.name, 'Unknown Product') AS product_name 
                   FROM order_items oi 
                   LEFT JOIN products p ON oi.product_id = p.product_id 
                   WHERE oi.order_id IN ({$inClause})";
    $itemsStmt = $pdo->prepare($itemsQuery);
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p class="text-muted-sm">Track customer orders, assign delivery drivers, and update order fulfillment statuses.</p>
    </div>
</div>

<div class="admin-card">
    <form action="orders.php" method="GET" class="card-header-flex mb-20">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input
                type="text"
                name="search"
                class="table-search-lg"
                placeholder="Search Order ID or Customer..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <select name="status" class="table-filter-lg" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Processing" <?php echo $statusFilter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="Out for Delivery" <?php echo $statusFilter === 'Out for Delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>

            <select name="per_page" class="table-filter-lg" onchange="this.form.submit()">
                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
                <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
                <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100 per page</option>
            </select>

            <button type="submit" class="btn-primary">Filter</button>
            <?php if ($search !== '' || $statusFilter !== '' || $perPage !== 10): ?>
                <a href="orders.php" class="btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="admin-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Assigned Driver</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <?php 
                        $formattedDate = date('M j, Y', strtotime($order['created_at']));
                        $formattedTotal = '₱' . number_format((float)$order['total_amount'], 2);
                        $paymentDisplay = ($order['payment_method'] === 'COD') ? 'Cash on Delivery' : htmlspecialchars($order['payment_method']);
                        $itemsJsonAttr = htmlspecialchars(json_encode($itemsByOrder[$order['order_id']] ?? []), ENT_QUOTES, 'UTF-8');
                        $addressAttr = htmlspecialchars($order['shipping_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="order-row">
                        <td class="order-id">#ORD-<?php echo $order['order_id']; ?></td>
                        <td class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo $formattedDate; ?></td>
                        <td><?php echo $formattedTotal; ?></td>
                        <td><?php echo $paymentDisplay; ?></td>
                        <td class="driver-name-text"><?php echo htmlspecialchars($order['driver_name']); ?></td>
                        <td class="order-status">
                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button 
                                type="button"
                                class="btn-action" 
                                data-order-id="<?php echo $order['order_id']; ?>"
                                data-status="<?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-driver-id="<?php echo $order['driver_id'] ?? ''; ?>"
                                data-address="<?php echo $addressAttr; ?>"
                                data-items="<?php echo $itemsJsonAttr; ?>"
                                onclick="openOrderModal(this)"
                            >
                                Manage Order
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="table-empty-notice">No orders found matching criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1 || $totalOrders > 0): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">
            <span class="text-muted-sm">Showing <?php echo $totalOrders > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $perPage, $totalOrders); ?> of <?php echo $totalOrders; ?> orders</span>
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; gap: 5px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                            $queryParams = $_GET;
                            $queryParams['p'] = $i;
                            $pageUrl = 'orders.php?' . http_build_query($queryParams);
                        ?>
                        <a href="<?php echo htmlspecialchars($pageUrl); ?>" class="btn-secondary" style="<?php echo $i === $currentPage ? 'background-color: var(--primary-dark-red, #800000); color: #fff;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h3>Update Order & Driver Assignment</h3>
            <button class="modal-close" onclick="closeModal('statusModal')" type="button">&times;</button>
        </div>
        
        <form action="orders.php" method="POST">
            <div class="form-group">
                <label>Order Reference</label>
                <input type="text" id="modalOrderId" name="order_id" readonly class="form-input-readonly">
            </div>

            <div class="form-group">
                <label>Shipping Address</label>
                <textarea id="modalShippingAddress" readonly class="form-input-readonly modal-textarea-readonly"></textarea>
            </div>

            <div class="form-group">
                <label>Purchased Items</label>
                <div id="modalItemsContainer" class="modal-items-box">
                </div>
            </div>

            <div class="form-group">
                <label>Order Status</label>
                <select id="modalStatusSelect" name="new_status">
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Out for Delivery">Out for Delivery</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label>Assign Delivery Driver</label>
                <select id="modalDriverSelect" name="driver_id">
                    <option value="">-- No Driver Assigned --</option>
                    <?php foreach ($driversList as $driver): ?>
                        <option value="<?php echo $driver['user_id']; ?>">
                            <?php echo htmlspecialchars($driver['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer-bordered">
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>