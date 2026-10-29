<?php
$isLoggedIn = isLoggedIn();
$productId = filter_var($_GET['product_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$productId || $productId <= 0) {
    header('Location: index.php?page=products');
    exit();
}

$productStmt = $pdo->prepare("SELECT p.product_id, p.name, p.brand, p.description, p.price, p.stock_quantity, p.image_path, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id WHERE p.product_id = ? AND p.status = 'Active' AND c.status = 'Active'");
$productStmt->execute([$productId]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php?page=products');
    exit();
}

$reviewMessage = '';
$reviewError = '';
$canReview = false;
$alreadyReviewed = false;

if ($isLoggedIn) {
    $reviewCheck = $pdo->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id WHERE o.user_id = ? AND o.status = 'Delivered' AND oi.product_id = ?");
    $reviewCheck->execute([(int)$_SESSION['user_id'], $productId]);
    $canReview = (int)$reviewCheck->fetchColumn() > 0;

    $existingReview = $pdo->prepare("SELECT review_id FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
    $existingReview->execute([(int)$_SESSION['user_id'], $productId]);
    $alreadyReviewed = (bool)$existingReview->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        exit('Invalid request. Refresh the page and try again.');
    }
    if (!$isLoggedIn) {
        $reviewError = 'Please login to leave a review.';
    } elseif (!$canReview) {
        $reviewError = 'You can review this product only after your order containing it has been delivered.';
    } elseif ($alreadyReviewed) {
        $reviewError = 'You have already reviewed this product.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = trim($_POST['review_text'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Please select a rating from 1 to 5 stars.';
        } elseif ($reviewText === '') {
            $reviewError = 'Please write your review.';
        } else {
            $reviewStmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $reviewStmt->execute([$productId, (int)$_SESSION['user_id'], $rating, $reviewText]);
            $reviewMessage = 'Your review has been posted.';
            $alreadyReviewed = true;
        }
    }
}

$ratingStmt = $pdo->prepare("SELECT COALESCE(AVG(rating), 0) AS average_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id = ?");
$ratingStmt->execute([$productId]);
$ratingSummary = $ratingStmt->fetch(PDO::FETCH_ASSOC);
$averageRating = (float)$ratingSummary['average_rating'];
$reviewCount = (int)$ratingSummary['review_count'];

$reviewsStmt = $pdo->prepare("SELECT r.rating, r.review_text, r.created_at, u.full_name, u.username FROM product_reviews r JOIN users u ON u.user_id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

$imgSrc = productImageSrc($product['image_path']);
?>

<section class="product-details-page">
    <a href="index.php?page=products" class="product-back">← Back to Products</a>

    <div class="product-details-main">
        <div class="product-details-image">
            <?php if ($imgSrc !== ''): ?>
                <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     referrerpolicy="no-referrer">
            <?php else: ?>
                <div class="product-image-empty">No image available</div>
            <?php endif; ?>
        </div>

        <div class="product-details-info">
            <p class="product-details-category"><?php echo htmlspecialchars($product['category']); ?></p>
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="product-details-rating">
                <span class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php echo $i <= round($averageRating) ? '★' : '☆'; ?>
                    <?php endfor; ?>
                </span>
                <strong><?php echo number_format($averageRating, 1); ?></strong>
                <span>(<?php echo $reviewCount; ?> review<?php echo $reviewCount === 1 ? '' : 's'; ?>)</span>
            </div>

            <div class="product-details-price">₱<?php echo number_format($product['price'], 2); ?></div>

            <div class="product-details-meta">
                <div><span>Brand</span><strong><?php echo htmlspecialchars($product['brand'] ?: 'Not specified'); ?></strong></div>
                <div><span>Category</span><strong><?php echo htmlspecialchars($product['category']); ?></strong></div>
                <div><span>Availability</span><strong><?php echo (int)$product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?></strong></div>
            </div>

            <?php if (!empty($product['description'])): ?>
                <div class="product-description">
                    <h2>Product Description</h2>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="product-details-actions">
                <button class="add-cart-button" data-id="<?php echo (int)$product['product_id']; ?>" <?php echo (int)$product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                <button class="buy-button" data-id="<?php echo (int)$product['product_id']; ?>" <?php echo (int)$product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>Buy Now</button>
            </div>
        </div>
    </div>

    <section class="product-reviews-section">
        <div class="reviews-header">
            <div>
                <p class="small-title">CUSTOMER FEEDBACK</p>
                <h2>Ratings & Reviews</h2>
            </div>
            <div class="reviews-summary">
                <strong><?php echo number_format($averageRating, 1); ?></strong>
                <span class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php echo $i <= round($averageRating) ? '★' : '☆'; ?>
                    <?php endfor; ?>
                </span>
                <span><?php echo $reviewCount; ?> review<?php echo $reviewCount === 1 ? '' : 's'; ?></span>
            </div>
        </div>

        <?php if ($reviewMessage): ?>
            <div class="review-message success"><?php echo htmlspecialchars($reviewMessage); ?></div>
        <?php endif; ?>
        <?php if ($reviewError): ?>
            <div class="review-message error"><?php echo htmlspecialchars($reviewError); ?></div>
        <?php endif; ?>

        <?php if ($isLoggedIn && $canReview && !$alreadyReviewed): ?>
            <form method="POST" class="review-form">
                <?php echo csrfField(); ?>
                <h3>Review this product</h3>
                <label>Rating</label>
                <select name="rating" required>
                    <option value="">Choose rating</option>
                    <option value="5">★★★★★ — Excellent</option>
                    <option value="4">★★★★☆ — Good</option>
                    <option value="3">★★★☆☆ — Average</option>
                    <option value="2">★★☆☆☆ — Poor</option>
                    <option value="1">★☆☆☆☆ — Very Poor</option>
                </select>
                <label>Review</label>
                <textarea name="review_text" rows="5" maxlength="1000" placeholder="Tell other SportMart customers about your experience..." required></textarea>
                <button type="submit" name="submit_review" class="review-submit">Submit Review</button>
            </form>
        <?php elseif (!$isLoggedIn): ?>
            <div class="review-note">Please <a href="index.php?login=required">login</a> to review this product.</div>
        <?php elseif ($alreadyReviewed): ?>
            <div class="review-note">You have already reviewed this product.</div>
        <?php else: ?>
            <div class="review-note">You can leave a review after this product has been delivered to you.</div>
        <?php endif; ?>

        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <h3>No reviews yet</h3>
                    <p>This product has a 0.0 rating until a verified customer leaves a review.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <article class="review-item">
                        <div class="review-top">
                            <strong><?php echo htmlspecialchars($review['full_name'] ?: $review['username']); ?></strong>
                            <span class="rating-stars"><?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?></span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                        <small><?php echo htmlspecialchars(date('M j, Y', strtotime($review['created_at']))); ?></small>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</section>
