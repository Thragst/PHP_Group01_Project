<?php
$stmt = $pdo->query(" SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id WHERE p.status = 'Active' AND c.status = 'Active' ORDER BY p.product_id ASC LIMIT 4 ");
$popularProducts = $stmt->fetchAll();
?>

<section class="hero">
    <div class="hero-text">
        <p class="small-title">SPORTS EQUIPMENT & GEAR</p>
        <h1> Gear up.<br> <span>Game on.</span>
        </h1>
        <p class="hero-description">
            Find the sports equipment you need
            for training, exercise, and your
            favorite sports.
        </p>
        <div class="hero-buttons">
            <a href="index.php?page=products" class="main-button"> Shop Now </a>
        </div>
    </div>
    <div class="hero-picture">
        <div class="circle">
            SPORT<br>
            <b>MART</b>
        </div>
    </div>
</section>
<section class="benefits">
    <div class="benefit-box">
        <div class="benefit-icon">🚚</div>
        <div>
            <b>Fast Delivery</b>
            <p>Quick and reliable delivery.</p>
        </div>
    </div>
    <div class="benefit-box">
        <div class="benefit-icon">✓</div>
        <div>
            <b>100% Authentic</b>
            <p>Original sports products.</p>
        </div>
    </div>
    <div class="benefit-box">
        <div class="benefit-icon">↩</div>
        <div>
            <b>Easy Returns</b>
            <p>Simple and hassle-free returns.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="small-title">OUR COLLECTION</p>
            <h2>Popular Products</h2>
        </div>
        <a href="index.php?page=products" class="view-all">
            View All Products →
        </a>
    </div>

    <div class="product-list">
        <?php foreach ($popularProducts as $product): ?>
            <div class="product">
                <div class="product-image">
                    <?php $imgSrc = productImageSrc($product['image_path']); ?>
                    <?php if ($imgSrc !== ''): ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy" style="width:100%;height:100%;object-fit:contain;display:block;" onerror="this.style.display='none'" >
                    <?php endif; ?>
                    <button class="heart">♡</button>
                </div>
                <div class="product-info">
                    <p class="product-category"> <?php echo htmlspecialchars($product['category']); ?> </p>
                    <h3 title="<?php echo htmlspecialchars($product['name']); ?>"> <?php echo htmlspecialchars($product['name']); ?> </h3>
                    <p class="brand"> <?php echo htmlspecialchars($product['brand'] ?? ''); ?>
                    </p>
                    <div class="stars"> ★★★★★ </div>
                    <strong> ₱<?php echo number_format($product['price'], 2); ?> </strong>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($popularProducts)): ?> <p>No products available right now.</p>
        <?php endif; ?>
    </div>
</section>

<section class="orders" id="orders">
    <div class="orders-content">
        <p class="small-title"> SPORTMART ACCOUNT </p>
        <h2> Track Your Orders </h2>
        <p>
            Check your previous orders and
            keep track of your purchases.
        </p>
        <a href="index.php?page=orders"> View My Orders </a>
    </div>
</section>