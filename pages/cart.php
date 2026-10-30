<?php
require_once __DIR__ . '/../includes/ssl.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/auth_guard.php';
keepStaffOut();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
enforceIdleTimeout($pdo);

$cartIsLoggedIn = isLoggedIn();
$cartUserId = $cartIsLoggedIn ? (int) $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        if (!$cartIsLoggedIn) {
            if (isset($_POST['buy_now'])) { header('Location: ../index.php?login=required'); exit; }
            header('Content-Type: application/json');
            echo json_encode([ 'success' => false, 'login_required' => true, 'message' => 'Please login to add items to your cart.' ]); exit;
        }
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([ 'success' => false, 'message' => 'Invalid request. Refresh the page and try again.' ]); exit;
        }
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode([ 'success' => false, 'message' => 'Invalid product.' ]); exit;
        }
        $stmt = $pdo->prepare(" SELECT product_id, name, brand, price, image_path FROM products WHERE product_id = ? AND status = 'Active' ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            header('Content-Type: application/json');
            echo json_encode([ 'success' => false, 'message' => 'Product not found.' ]); exit;
        }
        if (isset($_POST['buy_now'])) {
            $_SESSION['buy_now'] = [ $productId => 1 ];
            header('Location: checkout.php'); exit;
        }
        addToCart($pdo, $cartUserId, $productId);
        header('Content-Type: application/json');
        echo json_encode([ 'success' => true, 'name' => $product['name'] ]); exit;
    }

    if (!$cartIsLoggedIn) { header('Location: ../index.php?login=required'); exit; }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request.');
    }

    if (isset($_POST['update_cart'])) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        setCartQuantity($pdo, $cartUserId, $productId, $quantity);
        header('Location: ../index.php?page=cart'); exit;
    }
    if (isset($_POST['remove_from_cart'])) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        removeFromCart($pdo, $cartUserId, $productId);
        header('Location: ../index.php?page=cart'); exit;
    }
    if (isset($_POST['clear_cart'])) {
        clearCart($pdo, $cartUserId);
        header('Location: ../index.php?page=cart'); exit;
    }
}
$cart = $cartIsLoggedIn ? getCart($pdo, $cartUserId) : [];
$subtotal = getCartSubtotal($cart);
$itemCount = getCartItemCount($cart);
$freeShippingMin = 3000;
$shippingFee = 100;
$shipping = $subtotal == 0 ? 0 : ($subtotal >= $freeShippingMin ? 0 : $shippingFee);
$total = $subtotal + $shipping;
?>
<section class="cart-page">
    <div class="cart-heading">
        <p class="small-title"> SPORTMART SHOP </p>
        <h1> Shopping Cart </h1>
        <p> Review your items before checkout. </p>
    </div>
    <?php if (!$cartIsLoggedIn): ?>
        <div class="panel">
            <div class="empty-cart">
                <div class="empty-cart-icon"> 🛒 </div>
                <h2> Login to see your cart </h2>
                <p> Please login to add products to your cart and check out. </p>
                <a href="#" class="checkout-button" onclick="showLogin();return false;" > Login </a>
            </div>
        </div>
    <?php elseif (empty($cart)): ?>
        <div class="panel">
            <div class="empty-cart">
                <div class="empty-cart-icon"> 🛒 </div>
                <h2> Your cart is empty </h2>
                <p> Add some sports products to your cart and they will appear here. </p>
                <a href="index.php?page=products" class="checkout-button" > Browse Products </a>
            </div>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-list">
                <div class="cart-list-heading">
                    <h2> Cart Items </h2>
                    <span class="item-count"> <?php echo $itemCount; ?> item(s) </span>
                </div>
                <?php foreach ($cart as $item): ?>
                    <?php
                    $productId = (int) $item['product_id'];
                    $name = $item['name'] ?? 'Product';
                    $brand = $item['brand'] ?? '';
                    $price = (float)($item['price'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 1);
                    $image = productImageSrc($item['image_path'] ?? '');
                    $itemTotal = $price * $quantity;
                    ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <?php if ($image !== ''): ?>
                                <img
                                    src="<?php echo htmlspecialchars($image); ?>"
                                    alt="<?php echo htmlspecialchars($name); ?>"
                                    referrerpolicy="no-referrer" style="width:100%;height:100%;object-fit:contain;display:block;background:#fff;"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" >
                                <span style="display:none"> <?php echo htmlspecialchars(strtoupper(substr($name, 0, 1))); ?> </span>
                            <?php else: ?>
                                <span> <?php echo htmlspecialchars(strtoupper(substr($name, 0, 1))); ?> </span>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-info">
                            <h3> <?php echo htmlspecialchars($name); ?> </h3>
                            <?php if ($brand !== ''): ?>
                                <p> <?php echo htmlspecialchars($brand); ?> </p>
                            <?php endif; ?>
                            <span class="cart-item-price"> ₱<?php echo number_format($price, 2); ?> </span>
                        </div>
                        <form method="POST" action="pages/cart.php" class="quantity-control">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="update_cart" value="1">
                            <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                            <button type="submit" name="quantity" value="<?php echo $quantity - 1; ?>" class="quantity-minus" aria-label="Decrease quantity" > − </button>
                            <input type="number" value="<?php echo $quantity; ?>" min="1" readonly >
                            <button type="submit" name="quantity" value="<?php echo $quantity + 1; ?>" class="quantity-plus" aria-label="Increase quantity" > + </button>
                        </form>
                        <div class="item-side">
                            <div class="item-total"> ₱<?php echo number_format($itemTotal, 2); ?> </div>
                            <form method="POST" action="pages/cart.php" class="remove-form">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="remove_from_cart" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                <button type="submit" class="remove-button" style="background:#d32f2f;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-weight:600;font-size:13px;cursor:pointer;" > Remove </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <aside class="summary-panel">
                <h2> Order Summary </h2>
                <div class="summary-row">
                    <span> Subtotal </span>
                    <strong> ₱<?php echo number_format($subtotal, 2); ?> </strong>
                </div>
                <div class="summary-row">
                    <span> Shipping </span>
                    <strong>
                        <?php
                        echo $shipping == 0
                            ? 'FREE'
                            : '₱' . number_format($shipping, 2);
                        ?>
                    </strong>
                </div>
                <?php if ($subtotal > 0 && $subtotal < $freeShippingMin): ?>
                    <div class="shipping-note">
                        Add ₱<?php echo number_format($freeShippingMin - $subtotal, 2); ?>
                        more to get free shipping.
                    </div>
                <?php endif; ?>
                <hr class="summary-divider">
                <div class="summary-row summary-total">
                    <span> Total </span>
                    <strong> ₱<?php echo number_format($total, 2); ?> </strong>
                </div>
                <a href="pages/checkout.php?from=cart" class="checkout-button" > Proceed to Checkout </a>
                <a href="index.php?page=products" class="continue-shopping" > Continue Shopping </a>
            </aside>
        </div>
    <?php endif; ?>
</section>