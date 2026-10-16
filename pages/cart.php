<?php
$cart = getCart();
$subtotal = getCartSubtotal();
$itemCount = getCartItemCount();
$shipping = $subtotal >= 1000 || $subtotal <= 0 ? 0 : 100;
$total = $subtotal + $shipping;
?>
<section class="cart-page">
    <div class="cart-heading">
        <p class="small-title"> SPORTMART SHOP </p>
        <h1> Shopping Cart </h1>
        <p> Review your items before checkout. </p>
    </div>
    <?php if (empty($cart)): ?>
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
                <?php foreach ($cart as $index => $item): ?>
                    <?php
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
                                    style="width:100%;height:100%;object-fit:contain;display:block;"
                                    onerror="this.style.display='none'" >
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
                        <div class="quantity-control">
                            <button type="button" class="quantity-minus" data-index="<?php echo $index; ?>" > − </button>
                            <input type="number" value="<?php echo $quantity; ?>" min="1" readonly >
                            <button type="button" class="quantity-plus" data-index="<?php echo $index; ?>" > + </button>
                        </div>
                        <div class="item-side">
                            <div class="item-total"> ₱<?php echo number_format($itemTotal, 2); ?> </div>
                            <button type="button" class="remove-button" data-index="<?php echo $index; ?>" > Remove </button>
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
                <?php if ($subtotal > 0 && $subtotal < 1000): ?>
                    <div class="shipping-note">
                        Add ₱<?php echo number_format(1000 - $subtotal, 2); ?>
                        more to get free shipping.
                    </div>
                <?php endif; ?>
                <hr class="summary-divider">
                <div class="summary-row summary-total">
                    <span> Total </span>
                    <strong> ₱<?php echo number_format($total, 2); ?> </strong>
                </div>
                <a href="checkout.php" class="checkout-button" > Proceed to Checkout </a>
                <a href="index.php?page=products" class="continue-shopping" > Continue Shopping </a>
            </aside>
        </div>
    <?php endif; ?>
</section>