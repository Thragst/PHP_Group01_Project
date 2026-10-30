<?php
require_once __DIR__ . '/../includes/ssl.php';
session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
keepStaffOut();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_helper.php';
date_default_timezone_set('Asia/Manila');

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
    header('Location: ../index.php?login=required');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

if (isset($_GET['from']) && $_GET['from'] === 'cart') {
    unset($_SESSION['buy_now']);
}

/*
| Checks if orders.order_id is AUTO_INCREMENT. If the database was set up wrongly
| (no AUTO_INCREMENT), every order would get id 0 and all orders would merge into
| "#ORD-0". We detect that and assign the next id ourselves so each order is unique.
*/
function ordersIdIsAutoIncrement(PDO $pdo)
{
    $stmt = $pdo->query(
        "SELECT EXTRA FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_id'"
    );
    return stripos((string) $stmt->fetchColumn(), 'auto_increment') !== false;
}

function getBuyNowItems(PDO $pdo)
{
    $items = [];
    foreach ($_SESSION['buy_now'] ?? [] as $pid => $qty) {
        $stmt = $pdo->prepare("SELECT product_id, name, brand, price, image_path FROM products WHERE product_id = ? AND status = 'Active'");
        $stmt->execute([(int) $pid]);
        $row = $stmt->fetch();
        if ($row && (int) $qty > 0) {
            $row['quantity'] = min((int) $qty, 99);
            $items[(int) $row['product_id']] = $row;
        }
    }
    return $items;
}

function getCheckoutCart(PDO $pdo, $userId)
{
    return isset($_SESSION['buy_now']) ? getBuyNowItems($pdo) : getCart($pdo, $userId);
}

$stmt = $pdo->prepare("SELECT phone, physical_address FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = decryptRow($stmt->fetch() ?: [], ['phone', 'physical_address']);

$mobile = $profile['phone'] ?? '';
$address = $profile['physical_address'] ?? '';
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
            if (isset($_SESSION['buy_now'])) {
                if ((int)$quantity <= 0) { unset($_SESSION['buy_now'][(int)$id]); }
                else { $_SESSION['buy_now'][(int)$id] = min((int)$quantity, 99); }
                if (empty($_SESSION['buy_now'])) { unset($_SESSION['buy_now']); }
            } else {
                setCartQuantity($pdo, $userId, (int)$id, (int)$quantity);
            }
        }

        header('Location: checkout.php');
        exit;
    }

    if ($action === 'place_order') {
        $mobile = preg_replace('/[\s\-]/', '', trim($_POST['mobile'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $request = trim($_POST['request'] ?? '');

        if (empty(getCheckoutCart($pdo, $userId))) {
            $errors['cart'] = 'Your cart is empty.';
        }

        if (!preg_match('/^(09|\+639)\d{9}$/', $mobile)) {
            $errors['mobile'] = 'Please enter a valid mobile number (e.g. 09123456789).';
        }

        if (strlen($address) < 10) {
            $errors['address'] = 'Please enter your complete address.';
        } elseif (mb_strlen($address) > 255) {
            $errors['address'] = 'Address must be 255 characters or less.';
        }

        if (strlen($request) > 255) {
            $errors['request'] = 'Request must be 255 characters or less.';
        }

        if (empty($errors)) {
            $hasContact = tableHasColumn($pdo, 'orders', 'contact_number');
            $hasNotes = tableHasColumn($pdo, 'orders', 'order_notes');

            try {
                $pdo->beginTransaction();

                $orderCart = getCheckoutCart($pdo, $userId);

                if (empty($orderCart)) {
                    throw new DomainException('Cart is empty.');
                }

                $orderSubtotal = getCartSubtotal($orderCart);
                $orderShipping = $orderSubtotal >= 3000 ? 0 : 100;
                $orderTotal = $orderSubtotal + $orderShipping;

                $columns = ['user_id', 'total_amount', 'payment_method', 'status', 'shipping_address'];
                $values = [$userId, number_format($orderTotal, 2, '.', ''), 'COD', 'Pending', encryptField($address)];

                // Every checkout must get its OWN new order id (#1, #2, #3 ...)
                $newOrderId = null;
                if (!ordersIdIsAutoIncrement($pdo)) {
                    $newOrderId = (int) $pdo->query('SELECT COALESCE(MAX(order_id), 0) + 1 FROM orders FOR UPDATE')->fetchColumn();
                    array_unshift($columns, 'order_id');
                    array_unshift($values, $newOrderId);
                }

                if ($hasContact) {
                    $columns[] = 'contact_number';
                    $values[] = encryptField($mobile);
                }

                if ($hasNotes) {
                    $columns[] = 'order_notes';
                    $values[] = $request === '' ? null : $request;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO orders (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
                );
                $stmt->execute($values);

                $orderId = $newOrderId !== null ? $newOrderId : (int) $pdo->lastInsertId();

                if ($orderId <= 0) {
                    throw new RuntimeException('Order id was not generated.');
                }

                $itemStmt = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase)
                     VALUES (?, ?, ?, ?)'
                );

                foreach ($orderCart as $item) {
                    $itemStmt->execute([
                        $orderId,
                        (int) $item['product_id'],
                        (int) $item['quantity'],
                        $item['price']
                    ]);
                }

                if (isset($_SESSION['buy_now'])) { unset($_SESSION['buy_now']); } else { clearCart($pdo, $userId); }

                logAudit($pdo, $userId, "Customer placed order #ORD-" . $orderId . " (Total: ₱" . number_format($orderTotal, 2) . ")");

                $pdo->commit();

                header('Location: ../index.php?page=orders&placed=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Checkout failed: ' . $e->getMessage());

                $errors['cart'] = $e instanceof DomainException
                    ? 'Your cart is empty.'
                    : 'Unable to place your order. Please try again.';
            }
        }
    }
}

$cart = getCheckoutCart($pdo, $userId);
$totalItems = getCartItemCount($cart);
$subtotal = getCartSubtotal($cart);

$shipping = $subtotal == 0 ? 0 : ($subtotal >= 3000 ? 0 : 100);
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Checkout | SportMart</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/styles.css'); ?>">
    <link rel="stylesheet" href="../assets/css/account.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/account.css'); ?>">
</head>
<body>
<?php $basePath = '../'; include __DIR__ . '/../includes/header.php'; ?>

<main class="checkout-page">

    <?php if (empty($cart)): ?>

        <section class="panel empty-cart">
            <div class="empty-cart-icon">S</div>
            <h2>Nothing to checkout</h2>
            <p>Your cart is empty. Add some products first.</p>
            <a href="../index.php?page=products" class="checkout-button">Browse Products</a>
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
                            <div class="cart-item-image small" style="overflow:hidden;">
                                <?php $coImg = productImageSrc($item['image_path'] ?? '', '../'); ?>
                                <?php if ($coImg !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($coImg); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         referrerpolicy="no-referrer" style="width:100%;height:100%;object-fit:contain;display:block;background:#fff;"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <span style="display:none"><?php echo htmlspecialchars(strtoupper(substr($item['name'], 0, 1))); ?></span>
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars(strtoupper(substr($item['name'], 0, 1))); ?></span>
                                <?php endif; ?>
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

                <a href="../index.php?page=cart" class="continue-shopping">← Back to Cart</a>
            </aside>
        </div>

    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="../assets/js/main.js"></script>
</body>
</html>