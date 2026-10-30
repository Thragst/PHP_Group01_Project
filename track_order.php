<?php
require_once __DIR__ . '/includes/ssl.php';
session_start();
require_once __DIR__ . '/includes/auth_guard.php';
keepStaffOut();
require_once __DIR__ . '/includes/db.php';
enforceIdleTimeout($pdo);

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: index.php?login=required');
    exit();
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('Order not found.');
}

$stmt = $pdo->prepare("SELECT o.*, u.full_name AS rider_name FROM orders o LEFT JOIN users u ON u.user_id = o.driver_id WHERE o.order_id = ? AND o.user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();
if ($order) {
    $order = decryptRow($order, ['shipping_address', 'contact_number']);
}
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$stmt = $pdo->prepare("SELECT location_city, status_note, created_at FROM order_tracking WHERE order_id = ? ORDER BY created_at ASC, tracking_id ASC");
$stmt->execute([$orderId]);
$tracking = $stmt->fetchAll();
$events = [['location_city' => 'Order placed', 'status_note' => 'Your order has been received', 'created_at' => $order['created_at']]];
foreach ($tracking as $entry) {
    $events[] = $entry;
}
if ($order['status'] === 'Delivered' && (empty($tracking) || end($tracking)['status_note'] !== 'Package successfully delivered')) {
    $events[] = ['location_city' => 'Delivered', 'status_note' => 'Your order has been delivered', 'created_at' => null];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Order #ORD-<?php echo $orderId; ?> - SportMart</title>
    <link rel="stylesheet" href="assets/css/tracking.css">
</head>
<body>
    <main class="tracking-container">
        <a class="back-link" href="index.php?page=orders">← Back to My Orders</a>
        <header class="tracking-header">
            <div>
                <p class="eyebrow">SPORTMART DELIVERY TRACKING</p>
                <h1>Order #ORD-<?php echo $orderId; ?></h1>
            </div>
            <span class="status"><?php echo htmlspecialchars($order['status']); ?></span>
        </header>
        <section class="summary">
            <div><span>Delivery address</span><strong><?php echo htmlspecialchars($order['shipping_address']); ?></strong></div>
            <div><span>Delivery rider</span><strong><?php echo htmlspecialchars($order['rider_name'] ?: 'Not assigned yet'); ?></strong></div>
            <div><span>Payment</span><strong><?php echo htmlspecialchars($order['payment_method']); ?></strong></div>
            <div><span>Order total</span><strong>₱<?php echo number_format((float)$order['total_amount'], 2); ?></strong></div>
        </section>
        <section class="history">
            <h2>Delivery journey</h2>
            <p class="subtitle">Location checkpoints appear here as your rider updates the delivery.</p>
            <ol class="timeline">
                <?php foreach ($events as $event): ?>
                    <li class="checkpoint">
                        <span class="dot"></span>
                        <div class="checkpoint-content">
                            <strong><?php echo htmlspecialchars($event['location_city']); ?></strong>
                            <p><?php echo htmlspecialchars($event['status_note']); ?></p>
                            <?php if (!empty($event['created_at'])): ?>
                                <time><?php echo date('M j, Y · g:i A', strtotime($event['created_at'])); ?></time>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($order['status'] === 'Cancelled'): ?>
                <p class="notice">This order has been cancelled.</p>
            <?php elseif ($order['status'] !== 'Delivered'): ?>
                <p class="notice">Waiting for the next delivery update.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>