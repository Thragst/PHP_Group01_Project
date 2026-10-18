
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
    
    $allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled'];

    if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
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


$query = "SELECT 
            o.order_id, 
            o.created_at, 
            o.total_amount, 
            o.payment_method, 
            o.status, 
            o.driver_id,
            COALESCE(u.full_name, 'Guest Customer') AS customer_name,
            COALESCE(d.full_name, 'Unassigned') AS driver_name
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.user_id 
          LEFT JOIN users d ON o.driver_id = d.user_id 
          ORDER BY o.order_id DESC";

$orders = $pdo->query($query)->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Track customer orders, assign delivery drivers, and update order fulfillment statuses.</p>
    </div>
</div>

<div class="admin-card">

    <div style="margin-bottom: 20px; display: flex; gap: 10px; justify-content: space-between; align-items: center;">

        <input
            type="text"
            id="searchInput"
            onkeyup="filterTable()"
            placeholder="Search by Order ID..."
            style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;"
        >

        <select
            id="statusFilter"
            onchange="filterTable()"
            style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px;"
        >
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Processing">Processing</option>
            <option value="Shipped">Shipped</option>
            <option value="Out for Delivery">Out for Delivery</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>

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
                    ?>
                    <tr class="order-row">
                        <td class="order-id">#ORD-<?php echo $order['order_id']; ?></td>
                        <td class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo $formattedDate; ?></td>
                        <td><?php echo $formattedTotal; ?></td>
                        <td><?php echo $paymentDisplay; ?></td>
                        <td style="font-weight: 500; color: #444;"><?php echo htmlspecialchars($order['driver_name']); ?></td>
                        <td class="order-status">
                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-action" onclick="openStatusModal('<?php echo $order['order_id']; ?>', '<?php echo htmlspecialchars($order['status']); ?>', '<?php echo $order['driver_id'] ?? ''; ?>')">
                                Manage Order
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #777;">No orders found in database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (empty($orders)): ?>
        <p>No orders found in the database.</p>
    <?php endif; ?>

</div>

<!-- Modal for Admin / Staff Management -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-content" style="max-width: 450px;">

        <div class="modal-header">
            <h3>Update Order & Driver Assignment</h3>
            <button class="modal-close" onclick="closeModal('statusModal')" type="button">&times;</button>
        </div>
        
        <form action="orders.php" method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Order Reference</label>
                <input type="text" id="modalOrderId" name="order_id" readonly style="background-color: #F3F3F3; font-weight: bold; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Order Status</label>
                <select id="modalStatusSelect" name="new_status" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Shipped">Shipped</option>
                    <option value="Out for Delivery">Out for Delivery</option>
                    <option value="Delivered">Delivered</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Assign Delivery Driver</label>
                <select id="modalDriverSelect" name="driver_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- No Driver Assigned --</option>
                    <?php foreach ($driversList as $driver): ?>
                        <option value="<?php echo $driver['user_id']; ?>">
                            <?php echo htmlspecialchars($driver['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>
