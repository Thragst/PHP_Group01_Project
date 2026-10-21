<?php
$isLoggedIn = isLoggedIn();

/*
|--------------------------------------------------------------------------
| Loads the logged-in customer's orders. Every checkout is ONE separate order
| with its own order id, date & time, items, payment, delivery info and totals.
|--------------------------------------------------------------------------
*/
if (!function_exists('getMyOrdersDetailed')) {
    function getMyOrdersDetailed(PDO $pdo, $userId)
    {
        $stmt = $pdo->prepare(
            "SELECT o.*, u.phone AS user_phone, d.full_name AS driver_name
             FROM orders o
             LEFT JOIN users u ON u.user_id = o.user_id
             LEFT JOIN users d ON d.user_id = o.driver_id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC, o.order_id DESC"
        );
        $stmt->execute([(int) $userId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return [];
        }

        $orders = [];
        foreach ($rows as $row) {
            $id = (int) $row['order_id'];
            $time = strtotime($row['created_at']);

            $orders[$id] = [
                'id'        => $id,
                'number'    => '#ORD-' . $id,
                'date'      => date('M j, Y', $time),
                'time'      => date('g:i A', $time),
                'status'    => $row['status'],
                'payment'   => $row['payment_method'] === 'COD' ? 'Cash on Delivery' : $row['payment_method'],
                'address'   => $row['shipping_address'],
                'mobile'    => !empty($row['contact_number']) ? $row['contact_number'] : ($row['user_phone'] ?? ''),
                'request'   => $row['order_notes'] ?? '',
                'rider'     => $row['driver_name'] ?? '',
                'total'     => (float) $row['total_amount'],
                'subtotal'  => 0.0,
                'shipping'  => 0.0,
                'items'     => [],
            ];
        }

        $marks = implode(',', array_fill(0, count($orders), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT oi.order_id, oi.quantity, oi.price_at_purchase,
                    COALESCE(p.name, 'Unavailable product') AS name, p.image_path
             FROM order_items oi
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id IN ($marks)
             ORDER BY oi.order_item_id ASC"
        );
        $itemStmt->execute(array_keys($orders));

        foreach ($itemStmt->fetchAll() as $item) {
            $id = (int) $item['order_id'];
            $price = (float) $item['price_at_purchase'];
            $qty = (int) $item['quantity'];

            $orders[$id]['items'][] = [
                'name'       => $item['name'],
                'image_path' => $item['image_path'] ?? '',
                'price'      => $price,
                'quantity'   => $qty,
            ];
            $orders[$id]['subtotal'] += $price * $qty;
        }

        foreach ($orders as $id => $order) {
            $orders[$id]['shipping'] = max(0, round($order['total'] - $order['subtotal'], 2));
        }

        return array_values($orders);
    }
}

$orders = $isLoggedIn ? getMyOrdersDetailed($pdo, $_SESSION['user_id']) : [];
?>
<style>
    .order-card { margin-bottom: 18px; }
</style>
<section class="orders-page">
    <div class="orders-header">
        <div>
            <p class="small-title"> SPORTMART ACCOUNT </p>
            <h1> My Orders </h1>
            <p> View and track your recent orders. Each checkout is shown as its own order. </p>
        </div>
        <select class="order-filter" id="orderFilter" >
            <option value="All"> FILTER </option>
            <option value="Pending"> Pending </option>
            <option value="Processing"> Processing </option>
            <option value="Shipped"> Shipped </option>
            <option value="Out for Delivery"> Out for Delivery </option>
            <option value="Delivered"> Delivered </option>
            <option value="Cancelled"> Cancelled </option>
        </select>
    </div>
    <?php if (isset($_GET['placed']) && $isLoggedIn && !empty($orders)): ?>
        <div class="order-notice">
            ✓ Your order <strong><?php echo htmlspecialchars($orders[0]['number']); ?></strong> has been placed!
            You can track its status below.
        </div>
    <?php endif; ?>
    <?php if (!$isLoggedIn): ?>
        <div class="orders-empty">
            <div class="orders-empty-icon"> S </div>
            <h2> Login to see your orders </h2>
            <p> Please login to view and track your orders. </p>
            <a href="#" class="checkout-button" onclick="showLogin();return false;" > Login </a>
        </div>
    <?php elseif (empty($orders)): ?>
        <div class="orders-empty">
            <div class="orders-empty-icon"> S </div>
            <h2> No orders yet </h2>
            <p> Once you place an order, it will show up here. </p>
            <a href="index.php?page=products" class="checkout-button" > Browse Products </a>
        </div>
    <?php else: ?>
        <div class="orders-scroll">
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $statusClass = getOrderStatusClass($order['status']);
                    $itemCount = array_sum(array_column($order['items'], 'quantity'));
                    ?>
                    <div class="order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>" >
                        <div class="order-card-head">
                            <div>
                                <span class="order-code">
                                    <?php echo htmlspecialchars($order['number']); ?>
                                </span>
                                <span class="order-date">
                                    Placed <?php echo htmlspecialchars($order['date']); ?> · <?php echo htmlspecialchars($order['time']); ?>
                                </span>
                            </div>
                            <span class="order-status <?php echo $statusClass; ?>" >
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>

                        <div class="order-items">
                            <?php foreach ($order['items'] as $orderItem): ?>
                                <div class="order-line">
                                    <div class="order-line-image" style="overflow:hidden;">
                                        <?php $olImg = productImageSrc($orderItem['image_path'] ?? ''); ?>
                                        <?php if ($olImg !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($olImg); ?>"
                                                 alt="<?php echo htmlspecialchars($orderItem['name']); ?>"
                                                 referrerpolicy="no-referrer" style="width:100%;height:100%;object-fit:contain;display:block;background:#fff;"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                            <span style="display:none"> <?php echo htmlspecialchars(strtoupper(substr($orderItem['name'], 0, 1))); ?> </span>
                                        <?php else: ?>
                                            <span> <?php echo htmlspecialchars(strtoupper(substr($orderItem['name'], 0, 1))); ?> </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-line-info">
                                        <h3> <?php echo htmlspecialchars($orderItem['name']); ?> </h3>
                                        <p> Qty: <?php echo (int) $orderItem['quantity']; ?> × ₱<?php echo number_format($orderItem['price'], 2); ?> </p>
                                    </div>
                                    <div class="order-line-total">
                                        ₱<?php echo number_format($orderItem['price'] * $orderItem['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($order['items'])): ?>
                                <p class="order-request"> No item details available for this order. </p>
                            <?php endif; ?>
                        </div>

                        <div class="order-card-foot">
                            <div class="order-delivery">
                                <span class="order-delivery-label"> Deliver to </span>
                                <p><?php echo htmlspecialchars($order['address']); ?></p>
                                <p>Payment: <?php echo htmlspecialchars($order['payment']); ?> · Rider: <?php echo $order['rider'] !== '' ? htmlspecialchars($order['rider']) : 'Not assigned yet'; ?></p>
                                <?php if ($order['mobile'] !== ''): ?>
                                    <p>Contact: <?php echo htmlspecialchars($order['mobile']); ?></p>
                                <?php endif; ?>
                                <?php if ($order['request'] !== ''): ?>
                                    <p class="order-request">Request: <?php echo htmlspecialchars($order['request']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="order-totals">
                                <span class="order-shipping">
                                    Subtotal: ₱<?php echo number_format($order['subtotal'], 2); ?>
                                </span>
                                <span class="order-shipping">
                                    Shipping: <?php echo $order['shipping'] == 0 ? 'FREE' : '₱' . number_format($order['shipping'], 2); ?>
                                </span>
                                <span class="order-total-label"> Total </span>
                                <strong class="order-total-price">
                                    ₱<?php echo number_format($order['total'], 2); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>