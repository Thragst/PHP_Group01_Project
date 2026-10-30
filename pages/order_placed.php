<?php
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
keepStaffOut();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: ../index.php?login=required');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    header('Location: ../index.php?page=orders');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ../index.php?page=orders');
    exit();
}

$order = decryptRow($order, ['shipping_address', 'contact_number']);

$itemStmt = $pdo->prepare(
    "SELECT oi.quantity, oi.price_at_purchase, p.name, p.image_path
     FROM order_items oi
     LEFT JOIN products p ON p.product_id = oi.product_id
     WHERE oi.order_id = ?"
);
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
$totalItems = 0;
foreach ($items as $item) {
    $subtotal += $item['price_at_purchase'] * $item['quantity'];
    $totalItems += (int) $item['quantity'];
}

$total = (float) $order['total_amount'];
$shipping = max(0, $total - $subtotal);
$orderNumber = '#ORD-' . (int) $order['order_id'];
$placedAt = date('M j, Y · g:i A', strtotime($order['created_at']));
$notes = trim($order['order_notes'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Order Placed | SportMart</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="../assets/css/account.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/account.css'); ?>">
    <style>
        .placed-wrap { max-width: 900px; margin: 0 auto; padding: 32px 20px 60px; }
        .placed-banner { background: #e4f2e3; color: #2d6a2f; border-radius: 18px; padding: 28px; text-align: center; margin-bottom: 24px; }
        .placed-check { width: 56px; height: 56px; border-radius: 50%; background: #2d6a2f; color: #fff; font-size: 30px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
        .placed-banner h1 { margin: 0 0 6px; font-size: 26px; }
        .placed-banner p { margin: 0; font-size: 15px; }
        .placed-card { background: #fff; border-radius: 18px; padding: 24px; margin-bottom: 20px; box-shadow: 0 6px 24px rgba(0,0,0,.05); }
        .placed-card h2 { margin: 0 0 16px; font-size: 18px; }
        .placed-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
        .placed-label { display: block; font-size: 12px; color: #7a6b68; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
        .placed-value { font-size: 15px; font-weight: 600; word-break: break-word; }
        .placed-full { grid-column: 1 / -1; }
        .placed-item { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f0e6e3; }
        .placed-item:last-child { border-bottom: none; }
        .placed-thumb { width: 64px; height: 64px; border-radius: 12px; overflow: hidden; background: #f5efed; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #8b2e2e; }
        .placed-thumb img { width: 100%; height: 100%; object-fit: contain; background: #fff; display: block; }
        .placed-item-info { flex: 1; min-width: 0; }
        .placed-item-info h3 { margin: 0 0 4px; font-size: 15px; }
        .placed-item-info span { font-size: 13px; color: #7a6b68; }
        .placed-item-total { font-weight: 700; }
        .placed-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
        .placed-actions .checkout-button { width: auto; min-width: 200px; margin: 0; text-decoration: none; }
        .placed-actions .placed-secondary { background: #fff; color: #8b2e2e; border: 2px solid #8b2e2e; }
        @media (max-width: 600px) { .placed-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php $basePath = '../'; include __DIR__ . '/../includes/header.php'; ?>

<main class="placed-wrap">
    <div class="placed-banner">
        <div class="placed-check">✓</div>
        <h1>Order Placed!</h1>
        <p>Thank you! Your order <strong><?php echo htmlspecialchars($orderNumber); ?></strong> has been placed successfully.</p>
    </div>

    <section class="placed-card">
        <h2>Order Information</h2>
        <div class="placed-grid">
            <div>
                <span class="placed-label">Order Number</span>
                <span class="placed-value"><?php echo htmlspecialchars($orderNumber); ?></span>
            </div>
            <div>
                <span class="placed-label">Date &amp; Time</span>
                <span class="placed-value"><?php echo htmlspecialchars($placedAt); ?></span>
            </div>
            <div>
                <span class="placed-label">Status</span>
                <span class="placed-value"><?php echo htmlspecialchars($order['status']); ?></span>
            </div>
            <div>
                <span class="placed-label">Payment Method</span>
                <span class="placed-value"><?php echo htmlspecialchars($order['payment_method'] === 'COD' ? 'Cash on Delivery' : $order['payment_method']); ?></span>
            </div>
            <?php if (!empty($order['contact_number'])): ?>
                <div>
                    <span class="placed-label">Mobile Number</span>
                    <span class="placed-value"><?php echo htmlspecialchars($order['contact_number']); ?></span>
                </div>
            <?php endif; ?>
            <div class="<?php echo empty($order['contact_number']) ? '' : 'placed-full'; ?>">
                <span class="placed-label">Delivery Address</span>
                <span class="placed-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
            </div>
            <?php if ($notes !== ''): ?>
                <div class="placed-full">
                    <span class="placed-label">Request</span>
                    <span class="placed-value"><?php echo htmlspecialchars($notes); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="placed-card">
        <h2>Items Ordered</h2>
        <?php foreach ($items as $item): ?>
            <?php
                $img = productImageSrc($item['image_path'] ?? '');
                if ($img !== '' && !preg_match('#^https?://#i', $img)) { $img = '../' . $img; }
                $itemName = $item['name'] ?? 'Product';
            ?>
            <div class="placed-item">
                <div class="placed-thumb">
                    <?php if ($img !== ''): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>"
                             alt="<?php echo htmlspecialchars($itemName); ?>"
                             referrerpolicy="no-referrer"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                        <span style="display:none"><?php echo htmlspecialchars(strtoupper(substr($itemName, 0, 1))); ?></span>
                    <?php else: ?>
                        <span><?php echo htmlspecialchars(strtoupper(substr($itemName, 0, 1))); ?></span>
                    <?php endif; ?>
                </div>
                <div class="placed-item-info">
                    <h3><?php echo htmlspecialchars($itemName); ?></h3>
                    <span>₱<?php echo number_format($item['price_at_purchase'], 2); ?> × <?php echo (int) $item['quantity']; ?></span>
                </div>
                <div class="placed-item-total">₱<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?></div>
            </div>
        <?php endforeach; ?>

        <hr class="summary-divider">

        <div class="summary-row">
            <span>Total Items</span>
            <strong><?php echo $totalItems; ?></strong>
        </div>
        <div class="summary-row">
            <span>Subtotal</span>
            <strong>₱<?php echo number_format($subtotal, 2); ?></strong>
        </div>
        <div class="summary-row">
            <span>Shipping Fee</span>
            <strong><?php echo $shipping == 0 ? 'FREE' : '₱' . number_format($shipping, 2); ?></strong>
        </div>
        <div class="summary-row summary-total">
            <span>Total Price</span>
            <strong>₱<?php echo number_format($total, 2); ?></strong>
        </div>
    </section>

    <div class="placed-actions">
        <a href="../track_order.php?order_id=<?php echo (int) $order['order_id']; ?>" class="checkout-button">Track Order</a>
        <a href="../index.php?page=products" class="checkout-button placed-secondary">Continue Shopping</a>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="../assets/js/main.js"></script>
</body>
</html>