<?php
$isLoggedIn = isLoggedIn();
$orders = $_SESSION['orders'] ?? [];
?>
<section class="orders-page">
    <div class="orders-header">
        <div>
            <p class="small-title"> SPORTMART ACCOUNT </p>
            <h1> My Orders </h1>
            <p> View and track your recent orders. </p>
        </div>
        <select class="order-filter" id="orderFilter" >
            <option value="All"> FILTER </option>
            <option value="Processing"> Processing </option>
            <option value="Shipped"> Shipped </option>
            <option value="Out for Delivery"> Out for Delivery </option>
            <option value="Delivered"> Delivered </option>
        </select>
    </div>
    <?php if (isset($_GET['placed']) && $isLoggedIn && !empty($orders)): ?>
        <div class="order-notice">
            ✓ Your order has been placed!
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
                    $itemCount =
                        array_sum(
                            array_column( $order['items'], 'quantity' )
                        );
                    ?>
                    <div class="order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>" >
                        <div class="order-card-head">
                            <div>
                                <span class="order-code">
                                    <?php echo htmlspecialchars( $order['number'] ); ?>
                                </span>
                                <span class="order-date">
                                    <?php echo htmlspecialchars( $order['date'] ); ?>

                                    ·

                                    <?php echo (int)$itemCount; ?> item(s)
                                </span>
                            </div>
                            <span
                                class="order-status <?php echo $statusClass; ?>" >
                                <?php echo htmlspecialchars( $order['status'] ); ?>
                            </span>
                        </div>
                        <div class="order-items">
                            <?php foreach ($order['items'] as $orderItem): ?>
                                <div class="order-line">
                                    <div class="order-line-image">
                                        <span> <?php echo htmlspecialchars( strtoupper( substr( $orderItem['name'], 0, 1 ) ) ); ?> </span>
                                    </div>
                                    <div class="order-line-info">
                                        <h3> <?php echo htmlspecialchars( $orderItem['name'] ); ?> </h3>
                                        <p> Qty: <?php echo (int)$orderItem['quantity']; ?>

                                            ×

                                            ₱<?php echo number_format( $orderItem['price'], 2 ); ?>
                                        </p>
                                    </div>
                                    <div class="order-line-total">
                                        ₱<?php echo number_format( $orderItem['price'] * $orderItem['quantity'], 2 ); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-card-foot">
                            <div class="order-delivery">
                                <span class="order-delivery-label"> Deliver to </span>
                                <p>
                                    <?php echo htmlspecialchars( $order['address'] ); ?>
                                </p>
                                <p>
                                    <?php echo htmlspecialchars( $order['mobile'] ); ?>
                                </p>
                                <?php if ($order['request'] !== ''): ?>
                                    <p class="order-request">

                                        Request:

                                        <?php echo htmlspecialchars( $order['request'] ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="order-totals">
                                <span class="order-shipping">

                                    Shipping:

                                    <?php echo $order['shipping'] == 0
                                        ? 'FREE'
                                        : '₱' . number_format(
                                            $order['shipping'],
                                            2
                                        );
                                    ?>
                                </span>
                                <span class="order-total-label"> Total </span>
                                <strong class="order-total-price">
                                    ₱<?php echo number_format( $order['total'], 2 ); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>