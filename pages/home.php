<?php
$stmt = $pdo->query(" SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.name AS category, COALESCE(AVG(r.rating), 0) AS average_rating, COUNT(r.review_id) AS review_count FROM products p JOIN categories c ON c.category_id = p.category_id LEFT JOIN product_reviews r ON r.product_id = p.product_id WHERE p.status = 'Active' AND c.status = 'Active' GROUP BY p.product_id, p.name, p.brand, p.price, p.image_path, c.name ORDER BY average_rating DESC, p.product_id DESC LIMIT 4 ");
$popularProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <article class="product home-product" onclick="window.location.href='index.php?page=productDetails&product_id=<?php echo (int)$product['product_id']; ?>'"
            tabindex="0" onkeydown="if (event.key === 'Enter') window.location.href='index.php?page=productDetails&product_id=<?php echo (int)$product['product_id']; ?>'">
            <div class="product-image">
                <?php $imgSrc = productImageSrc($product['image_path']); ?>
                <?php if($imgSrc !== ''): ?> 
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt"<?php echo htmlspecialchars($product['name']); ?>" loading="lazy" onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
            <div class="product-info">
                <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                <h3 title="<?php echo htmlspecialchars($product['name']); ?>"> <?php echo htmlspecialchars($product['name']); ?></h3>
                <p class="brand"><?php echo htmlspecialchars($product['brand'] ?? ''); ?></p>
                <div class="stars"><?php $rating = (float)$product['average_rating']; $fullStars = (int)floor($rating); ?> <?php for ($i = 1; $i <= 5; $i++): ?> <?php echo $i <= $fullStars ? '★' : '☆'; ?> <?php endfor; ?>
                <span><?php echo number_format($rating, 1); ?> (<?php echo (int)$product['review_count']; ?>)</span></div>
                <strong>₱<?php echo number_format($product['price'], 2); ?></strong>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if(empty($popularProducts)): ?> <p>No products available right now.</p><?php endif; ?>
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