<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
date_default_timezone_set('Asia/Manila');

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
    header('Location: ../index.php?login=required');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!isset($_SESSION['orders']) || !is_array($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$mobile = '';
$address = '';
$request = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        foreach ($_POST['quantity'] ?? [] as $id => $quantity) {
            $id = (int)$id;
            $quantity = (int)$quantity;

            if (isset($_SESSION['cart'][$id])) {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    $_SESSION['cart'][$id]['quantity'] = min($quantity, 99);
                }
            }
        }

        header('Location: checkout.php');
        exit;
    }

    if ($action === 'place_order') {
        $mobile = preg_replace('/[\s\-]/', '', trim($_POST['mobile'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $request = trim($_POST['request'] ?? '');

        if (empty($_SESSION['cart'])) {
            $errors['cart'] = 'Your cart is empty.';
        }

        if (!preg_match('/^(09|\+639)\d{9}$/', $mobile)) {
            $errors['mobile'] = 'Please enter a valid mobile number (e.g. 09123456789).';
        }

        if (strlen($address) < 10) {
            $errors['address'] = 'Please enter your complete address.';
        }

        if (strlen($request) > 255) {
            $errors['request'] = 'Request must be 255 characters or less.';
        }

        if (empty($errors)) {
            $orderItems = [];
            $orderSubtotal = 0;

            foreach ($_SESSION['cart'] as $item) {
                $qty = (int)$item['quantity'];
                $price = (float)$item['price'];
                $orderSubtotal += $price * $qty;

                $orderItems[] = [
                    'name' => $item['name'],
                    'price' => $price,
                    'quantity' => $qty
                ];
            }

            $orderShipping = $orderSubtotal >= 3000 ? 0 : 100;

            $order = [
                'number' => 'SM-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)),
                'date' => date('M j, Y · g:i A'),
                'status' => 'Processing',
                'user_id' => $_SESSION['user_id'] ?? '',
                'items' => $orderItems,
                'subtotal' => $orderSubtotal,
                'shipping' => $orderShipping,
                'total' => $orderSubtotal + $orderShipping,
                'mobile' => $mobile,
                'address' => $address,
                'request' => $request
            ];

            array_unshift($_SESSION['orders'], $order);
            $_SESSION['cart'] = [];

            header('Location: ../index.php?page=orders&placed=1');
            exit;
        }
    }
}

$cart = $_SESSION['cart'];
$totalItems = 0;
$subtotal = 0;

foreach ($cart as $item) {
    $totalItems += (int)$item['quantity'];
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

$shipping = $subtotal == 0 ? 0 : ($subtotal >= 3000 ? 0 : 100);
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Checkout | SportMart</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="checkout-page">

    <?php if (empty($cart)): ?>

        <section class="panel empty-cart">
            <div class="empty-cart-icon">S</div>
            <h2>Nothing to checkout</h2>
            <p>Your cart is empty. Add some products first.</p>
            <a href="index.php?page=products" class="checkout-button">Browse Products</a>
        </section>

    <?php else: ?>

        <div class="checkout-heading">
            <div>
                <p class="small-title">SPORTMART CHECKOUT</p>
                <h1>Checkout</h1>
            </div>
            <span class="items-badge">Items: <?php echo $totalItems; ?></span>
        </div>

        <div class="checkout-layout">
            <form method="POST" action="checkout.php" class="checkout-items panel" id="quantityForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="update">

                <div class="checkout-table-head">
                    <span>Product Details</span>
                    <span>Quantity</span>
                    <span>Total</span>
                </div>

                <?php foreach ($cart as $id => $item): ?>
                    <div class="checkout-item">
                        <div class="checkout-product">
                            <div class="cart-item-image small">
                                <span><?php echo htmlspecialchars(strtoupper(substr($item['name'], 0, 1))); ?></span>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        </div>

                        <div class="quantity-control">
                            <button type="button" onclick="changeQuantity(<?php echo (int)$id; ?>,-1)">−</button>
                            <input type="number"
                                   name="quantity[<?php echo (int)$id; ?>]"
                                   id="quantity-<?php echo (int)$id; ?>"
                                   value="<?php echo (int)$item['quantity']; ?>"
                                   min="0"
                                   max="99"
                                   readonly>
                            <button type="button" onclick="changeQuantity(<?php echo (int)$id; ?>,1)">+</button>
                        </div>

                        <div class="checkout-total">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </form>

            <aside class="summary-panel">
                <h2>Order Summary</h2>

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

                <hr class="summary-divider">

                <div class="summary-row summary-total">
                    <span>Total Price</span>
                    <strong>₱<?php echo number_format($total, 2); ?></strong>
                </div>

                <form method="POST" action="checkout.php" id="placeOrderForm" class="delivery-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="place_order">

                    <?php if (isset($errors['cart'])): ?>
                        <p class="form-alert"><?php echo htmlspecialchars($errors['cart']); ?></p>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="checkoutMobile">Mobile Number</label>
                        <input type="text"
                               class="form-input"
                               name="mobile"
                               id="checkoutMobile"
                               placeholder="09123456789"
                               value="<?php echo htmlspecialchars($mobile); ?>">
                        <?php if (isset($errors['mobile'])): ?>
                            <p class="field-error"><?php echo htmlspecialchars($errors['mobile']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="checkoutAddress">Address</label>
                        <textarea class="form-input"
                                  name="address"
                                  id="checkoutAddress"
                                  rows="2"
                                  placeholder="House no., street, barangay, city"><?php echo htmlspecialchars($address); ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <p class="field-error"><?php echo htmlspecialchars($errors['address']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="checkoutRequest">Request <span class="optional">(optional)</span></label>
                        <textarea class="form-input"
                                  name="request"
                                  id="checkoutRequest"
                                  rows="4"
                                  maxlength="255"
                                  placeholder="Special instructions for your order"><?php echo htmlspecialchars($request); ?></textarea>
                        <?php if (isset($errors['request'])): ?>
                            <p class="field-error"><?php echo htmlspecialchars($errors['request']); ?></p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="checkout-button" id="placeOrderButton">Place Order</button>
                </form>

                <a href="index.php?page=cart" class="continue-shopping">← Back to Cart</a>
            </aside>
        </div>

    <?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/main.js"></script>
</body>
</html>