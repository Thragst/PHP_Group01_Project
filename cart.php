<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$products = [
    1 => ['name' => 'Basketball', 'price' => 1250],
    2 => ['name' => 'Running Shoes', 'price' => 3499],
    3 => ['name' => 'Football', 'price' => 1899],
    4 => ['name' => 'Tennis Racket', 'price' => 2799],
    5 => ['name' => 'Boxing Gloves', 'price' => 1599],
    6 => ['name' => 'Badminton Racket', 'price' => 2299]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $added = false;

        if (isset($products[$productId])) {
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] =
                    min(99, (int)$_SESSION['cart'][$productId]['quantity'] + 1);
            } else {
                $_SESSION['cart'][$productId] = [
                    'name' => $products[$productId]['name'],
                    'price' => $products[$productId]['price'],
                    'quantity' => 1
                ];
            }

            $added = true;
        }

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            $cartCount = 0;

            foreach ($_SESSION['cart'] as $cartItem) {
                $cartCount += (int)$cartItem['quantity'];
            }

            header('Content-Type: application/json');

            echo json_encode([
                'success' => $added,
                'name' => $added ? $products[$productId]['name'] : '',
                'cart_count' => $cartCount
            ]);
            exit;
        }

        if ($added && isset($_POST['buy_now'])) {
            header('Location: checkout.php');
            exit;
        }

        header('Location: index.php?page=products');
        exit;
    }

    if (isset($_POST['update'])) {
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
    }

    if (isset($_POST['remove'])) {
        $id = (int)$_POST['remove'];
        unset($_SESSION['cart'][$id]);
    }

    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'];
$totalItems = 0;
$subtotal = 0;

foreach ($cart as $item) {
    $quantity = (int)$item['quantity'];
    $price = (float)$item['price'];
    $totalItems += $quantity;
    $subtotal += $price * $quantity;
}

$freeShippingMin = 3000;
$shippingFee = 100;

$shipping = $subtotal == 0 ? 0 : ($subtotal >= $freeShippingMin ? 0 : $shippingFee);
$total = $subtotal + $shipping;
$progress = $subtotal > 0 ? min(100, ($subtotal / $freeShippingMin) * 100) : 0;

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Shopping Cart | SportMart</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="cart-page">
    <div class="cart-heading">
        <p class="small-title">YOUR SPORTMART ORDER</p>
        <h1>Shopping Cart</h1>
        <p>Review your items before checking out.</p>
    </div>

    <?php if (empty($cart)): ?>

        <section class="panel empty-cart">
            <div class="empty-cart-icon">S</div>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added any sports products yet.</p>
            <a href="index.php?page=products" class="checkout-button">Browse Products</a>
        </section>

    <?php else: ?>

        <form method="POST" action="cart.php">
            <input type="hidden" name="update" value="1">

            <div class="cart-layout">
                <section class="cart-list">
                    <div class="cart-list-heading">
                        <h2>Your Items</h2>
                        <span class="item-count"><?php echo $totalItems; ?> item(s)</span>
                    </div>

                    <?php foreach ($cart as $id => $item): ?>
                        <article class="cart-item">
                            <div class="cart-item-image">
                                <span><?php echo htmlspecialchars(strtoupper(substr($item['name'], 0, 1))); ?></span>
                            </div>

                            <div class="cart-item-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p>SportMart Product</p>
                                <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
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

                            <div class="item-side">
                                <div class="item-total">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                <button type="submit" name="remove" value="<?php echo (int)$id; ?>" class="remove-button">Remove</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

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

                    <div class="shipping-note">
                        <div class="shipping-progress">
                            <div class="shipping-progress-bar" style="width:<?php echo (int)$progress; ?>%"></div>
                        </div>
                        <?php if ($subtotal >= $freeShippingMin): ?>
                            You've unlocked free shipping!
                        <?php else: ?>
                            Spend ₱<?php echo number_format($freeShippingMin - $subtotal, 2); ?> more to get free shipping.
                        <?php endif; ?>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <a href="checkout.php" class="checkout-button">Checkout</a>
                    <?php else: ?>
                        <a href="index.php?login=required" class="checkout-button">Login to Checkout</a>
                    <?php endif; ?>

                    <a href="index.php?page=products" class="continue-shopping">← Continue Shopping</a>
                </aside>
            </div>
        </form>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/main.js"></script>
</body>
</html>