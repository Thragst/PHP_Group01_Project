<?php
$stmt = $pdo->query(" SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id  WHERE p.status = 'Active' AND c.status = 'Active' ORDER BY p.product_id ASC ");
$dbProducts = $stmt->fetchAll();
?>

<section class="products-page">
    <div class="products-heading">
        <p class="small-title"> SPORTMART SHOP </p>
        <h1> Sports Products </h1>
        <p> Find the sports equipment you need for your game. </p>
    </div>
    <div class="filter-area">
        <div class="filter-group">
            <button class="filter-button active" data-category="All" > All </button>
            <button class="filter-button" data-category="Basketball" > Basketball </button>
            <button class="filter-button" data-category="Football" > Football </button>
            <button class="filter-button" data-category="Running" > Running </button>
            <button class="filter-button" data-category="Tennis" > Tennis </button>
        </div>
        <div class="sort-label"> Sort:
            <select id="sortProducts">
                <option value="newest"> Newest </option>
                <option value="oldest"> Oldest </option>
                <option value="lowest"> Lowest Price </option>
                <option value="highest"> Highest Price </option>
            </select>
        </div>
    </div>
    <div class="product-list products-grid" id="productList" >
        <?php foreach ($dbProducts as $index => $product): ?>
            <div class="product"
                data-id="<?php echo (int)$product['product_id']; ?>"
                data-category="<?php echo htmlspecialchars($product['category']); ?>"
                data-price="<?php echo htmlspecialchars($product['price']); ?>"
                data-order="<?php echo $index + 1; ?>" >
                <div class="product-image">
                    <?php $imgSrc = productImageSrc($product['image_path']); ?>
                    <?php if ($imgSrc !== ''): ?>
                        <img
                            src="<?php echo htmlspecialchars($imgSrc); ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            loading="lazy"
                            style="width:100%;height:100%;object-fit:contain;display:block;"
                            onerror="this.style.display='none'" >
                    <?php endif; ?>
                    <button class="heart"> ♡ </button>
                </div>
                <div class="product-info">
                    <p class="product-category"> <?php echo htmlspecialchars($product['category']); ?> </p>
                    <h3 title="<?php echo htmlspecialchars($product['name']); ?>" > <?php echo htmlspecialchars($product['name']); ?> </h3>
                    <p class="brand"> <?php echo htmlspecialchars($product['brand'] ?? ''); ?> </p>
                    <div class="stars"> ★★★★★ </div>
                    <div class="product-bottom">
                        <strong> ₱<?php echo number_format($product['price'], 2); ?> </strong>
                        <div class="product-buttons">
                            <button class="add-cart-button" data-id="<?php echo (int)$product['product_id']; ?>" > Add to Cart </button>
                            <button class="buy-button" data-id="<?php echo (int)$product['product_id']; ?>" > Buy </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($dbProducts)): ?>
            <p> No products available right now.</p>
        <?php endif; ?>
    </div>
        <div class="pagination" id="productPagination"></div>    
</section>