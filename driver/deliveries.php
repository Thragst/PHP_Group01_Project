<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['Driver']);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
enforceIdleTimeout($pdo);

if (empty($_SESSION['logged_in']) || ($_SESSION['user_role'] ?? '') !== 'Driver') {
    header('Location: ../pages/login.php');
    exit();
}

$driverId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    securityCheck();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_location') {
    $orderId = (int)$_POST['order_id'];
    $city = trim($_POST['location_city']);

    if ($orderId > 0 && $city !== '' && mb_strlen($city) <= 100) {
        $allowedStmt = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = ? AND driver_id = ? AND status = 'Out for Delivery'");
        $allowedStmt->execute([$orderId, $driverId]);
        if (!$allowedStmt->fetch()) {
            header('Location: deliveries.php');
            exit();
        }
        $trackStmt = $pdo->prepare("INSERT INTO order_tracking (order_id, location_city, status_note, updated_by) VALUES (?, ?, 'Package processed at location', ?)");
        $trackStmt->execute([$orderId, $city, $driverId]);

        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
        $logStmt->execute([$driverId, "Driver updated order #ORD-" . $orderId . " location to " . $city, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    }

    header('Location: deliveries.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_delivered') {
    $orderId = (int)$_POST['order_id'];

    if ($orderId > 0) {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'Delivered' WHERE order_id = ? AND driver_id = ?");
        $updateStmt->execute([$orderId, $driverId]);

        if ($updateStmt->rowCount() === 0) {
            header('Location: deliveries.php');
            exit();
        }
        $trackStmt = $pdo->prepare("INSERT INTO order_tracking (order_id, location_city, status_note, updated_by) VALUES (?, 'Destination Address', 'Package successfully delivered', ?)");
        $trackStmt->execute([$orderId, $driverId]);

        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
        $logStmt->execute([$driverId, "Driver marked order #ORD-" . $orderId . " as Delivered.", $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    }

    header('Location: deliveries.php');
    exit();
}

$query = "SELECT 
            o.order_id, 
            o.created_at, 
            o.total_amount, 
            o.payment_method, 
            o.status, 
            o.shipping_address,
            COALESCE(u.full_name, 'Customer') AS customer_name,
            u.phone AS customer_phone
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.user_id 
          WHERE o.driver_id = ? 
          ORDER BY (o.status = 'Out for Delivery') DESC, o.order_id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$driverId]);
$deliveries = decryptRows($stmt->fetchAll(), ['shipping_address', 'customer_phone']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Delivery Dashboard - SportMart</title>
    <link rel="stylesheet" href="../assets/css/driver.css">
</head>
<body>

<div class="mobile-container">
    <div class="header">
        <div>
            <h2>📦 Driver Portal</h2>
            <span class="driver-name">Driver: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Rider'); ?></span>
        </div>
        <div class="header-actions">
            <a href="driver_settings.php" class="settings-btn">Settings</a>
            <a href="../pages/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <h3 class="section-title">Assigned Deliveries</h3>

    <?php if (!empty($deliveries)): ?>
        <?php foreach ($deliveries as $item): ?>
            <?php
                $historyStmt = $pdo->prepare("SELECT location_city, status_note, created_at FROM order_tracking WHERE order_id = ? ORDER BY tracking_id DESC");
                $historyStmt->execute([$item['order_id']]);
                $logs = $historyStmt->fetchAll();
            ?>
            <div class="card <?php echo htmlspecialchars(strtolower(str_replace(' ', '-', (string) $item['status'])), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-header">
                    <span>#ORD-<?php echo (int) $item['order_id']; ?></span>
                    <span class="badge badge-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', (string) $item['status'])), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['status']); ?>
                    </span>
                </div>
                
                <div class="details">
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($item['customer_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($item['shipping_address']); ?></p>
                    <p><strong>Total:</strong> ₱<?php echo number_format((float)$item['total_amount'], 2); ?> (<?php echo htmlspecialchars($item['payment_method']); ?>)</p>
                </div>

                <div class="tracking-history">
                    <strong>📍 Location History:</strong>
                    <?php if (!empty($logs)): ?>
                        <ul>
                            <?php foreach ($logs as $log): ?>
                                <li><strong><?php echo htmlspecialchars($log['location_city']); ?></strong>
                                <?php echo htmlspecialchars($log['status_note']); ?> (<?php echo date('M j, g:i a', strtotime($log['created_at'])); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-history">No location updates recorded yet.</p>
                    <?php endif; ?>
                </div>

                <?php if ($item['status'] === 'Out for Delivery'): ?>
                    <form action="deliveries.php" method="POST">
                    <?php echo securityField(); ?>
                        <input type="hidden" name="action" value="update_location">
                        <input type="hidden" name="order_id" value="<?php echo (int) $item['order_id']; ?>">
                        <input type="text" name="location_city" class="select-input" maxlength="100" placeholder="Enter current city or location" required>
                        <button type="submit" class="btn-update">Log Location Checkpoint</button>
                    </form>

                    <button type="button" class="btn-deliver" onclick="openConfirmModal(<?php echo (int) $item['order_id']; ?>)">
                        Mark as Delivered
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card empty-card">
            No packages assigned to you at the moment.
        </div>
    <?php endif; ?>
</div>

<div class="custom-modal-overlay" id="confirmDeliveryModal" style="display: none;">
    <div class="custom-modal-card">
        <h3>Confirm Delivery</h3>
        <p id="confirmModalText">Are you sure you want to mark this order as delivered?</p>
        <form action="deliveries.php" method="POST">
            <?php echo securityField(); ?>
            <input type="hidden" name="action" value="mark_delivered">
            <input type="hidden" name="order_id" id="confirmModalOrderId" value="">
            <div class="custom-modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button type="submit" class="btn-modal-confirm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/driver.js"></script>
</body>
</html>