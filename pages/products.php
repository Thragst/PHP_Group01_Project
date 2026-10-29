<?php
require_once __DIR__ . '/../includes/search_engine.php';
$allowedPages = [10, 20, 30];
$productPage = max(1,(int)($_GET['product_page'] ?? 1));
$productsPerPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($productsPerPage, $allowedPages, true)) {$productsPerPage = 20;}
$selectedCategory = $_GET['category'] ?? 'All';
$searchQuery = sanitizeSearchQuery($_GET['q'] ?? '');
$isSearching = $searchQuery !== '';
$allowedSorts = ['relevance' => 'p.product_id DESC', 'newest' => 'p.product_id DESC', 'oldest' => 'p.product_id ASC', 'lowest' => 'p.price ASC', 'highest' => 'p.price DESC'];
$defaultSort = $isSearching ? 'relevance' : 'newest';
$selectedSort = $_GET['sort'] ?? $defaultSort;
if (!isset($allowedSorts[$selectedSort])) {$selectedSort = $defaultSort;}
$catStmt = $pdo->query("SELECT c.category_id, c.name, COUNT(p.product_id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.category_id AND p.status = 'Active' WHERE c.status = 'Active' GROUP BY c.category_id, c.name HAVING COUNT(p.product_id) > 0 ORDER BY c.category_id ASC");
$dbCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$where = ["p.status = 'Active'", "c.status = 'Active'"];
$params = [];
if ($selectedCategory !== 'All') {$categoryId = filter_var($selectedCategory, FILTER_VALIDATE_INT); 
if ($categoryId === false || $categoryId <= 0) {$selectedCategory = 'All';} else {
    $where[] = "p.category_id = ?";
    $params[] = $categoryId;}}
$whereSql = implode(' AND ', $where);
$searchInfo = null;

function renderStars($rating, $reviewsCount = 0) {
    $rating = round((float)$rating, 1);
    $fullStars = (int)floor($rating);
    $hasHalf = ($rating - $fullStars) >= 0.5;
    $out = '<div class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $out .= '★';
        } elseif ($i === $fullStars + 1 && $hasHalf) {
            $out .= '★';
        } else {
            $out .= '☆';
        }
    }
    $out .= ' <span class="rating-num">' . number_format($rating, 1) . ' (' . (int)$reviewsCount . ')</span></div>';
    return $out;
}

if ($isSearching) {
    $categoryFilterId = ($selectedCategory !== 'All') ? (int)$selectedCategory : null;
    $searchInfo = smartSearch($pdo, $searchQuery, ['category_id' => $categoryFilterId]);
    $matches = $searchInfo['results'];
    if ($selectedSort === 'lowest') { usort($matches, function ($a, $b) { return $a['price'] <=> $b['price']; }); }
    elseif ($selectedSort === 'highest') { usort($matches, function ($a, $b) { return $b['price'] <=> $a['price']; }); }
    elseif ($selectedSort === 'newest') { usort($matches, function ($a, $b) { return $b['product_id'] <=> $a['product_id']; }); }
    elseif ($selectedSort === 'oldest') { usort($matches, function ($a, $b) { return $a['product_id'] <=> $b['product_id']; }); }
    $totalProducts = count($matches);
    $totalPages = max(1,(int)ceil($totalProducts / $productsPerPage));
    if ($productPage > $totalPages) {$productPage = $totalPages;}
    $offset = ($productPage - 1) * $productsPerPage;
    $dbProducts = array_slice($matches, $offset, $productsPerPage);
    foreach ($dbCategories as &$catRow) { $catRow['product_count'] = (int)($searchInfo['categoryCounts'][$catRow['category_id']] ?? 0); }
    unset($catRow);
    logSearchQuery($pdo, $searchQuery, $totalProducts);
} else {
    $countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON c.category_id = p.category_id WHERE $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = (int)$countStmt->fetchColumn();
    $totalPages = max(1,(int)ceil($totalProducts / $productsPerPage));
    if ($productPage > $totalPages) {$productPage = $totalPages;}
    $offset = ($productPage - 1) * $productsPerPage;

    $productSql = "SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.category_id, c.name AS category,
                          COALESCE(AVG(r.rating), 0) AS avg_rating,
                          COUNT(r.review_id) AS total_reviews
                   FROM products p
                   JOIN categories c ON c.category_id = p.category_id
                   LEFT JOIN product_reviews r ON r.product_id = p.product_id
                   WHERE $whereSql
                   GROUP BY p.product_id, p.name, p.brand, p.price, p.image_path, c.category_id, c.name
                   ORDER BY {$allowedSorts[$selectedSort]}
                   LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($productSql);
    $pos = 1;
    foreach ($params as $p) {
        $stmt->bindValue($pos++, $p, PDO::PARAM_INT);
    }
    $stmt->bindValue($pos++, (int)$productsPerPage, PDO::PARAM_INT);
    $stmt->bindValue($pos, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function productPageUrl(int $page, string $category, string $sort, int $perPage): string { global $searchQuery; $args = [ 'page' => 'products', 'category' => $category, 'sort' => $sort, 'per_page' => $perPage, 'product_page' => $page ]; if (!empty($searchQuery)) { $args['q'] = $searchQuery; } return 'index.php?' . http_build_query($args); }
?>

<section class="products-page">
    <div class="products-heading">
        <p class="small-title">SPORTMART SHOP</p>
        <?php if ($isSearching): ?>
            <h1>Search results</h1>
            <p><?php echo (int)$totalProducts; ?> result<?php echo $totalProducts === 1 ? '' : 's'; ?> for “<?php echo htmlspecialchars($searchQuery); ?>”</p>
        <?php else: ?>
            <h1>Sports Products</h1>
            <p>Find the sports equipment you need for your game.</p>
        <?php endif; ?>
    </div>

    <div class="products-layout">
        <aside class="category-sidebar">
            <div class="category-sidebar-title">
                <span class="category-icon">☷</span>
                <span>Categories</span>
            </div>

            <div class="category-list">
                <a href="<?php echo htmlspecialchars(productPageUrl(1, 'All', $selectedSort, $productsPerPage)); ?>"
                   class="category-item <?php echo $selectedCategory === 'All' ? 'active' : ''; ?>">
                    <span>All Products</span>
                    <small><?php echo $totalProducts; ?></small>
                </a>

                <?php foreach ($dbCategories as $cat): ?>
                    <a href="<?php echo htmlspecialchars(productPageUrl(1, (string)$cat['category_id'], $selectedSort, $productsPerPage)); ?>"
                       class="category-item <?php echo (string)$cat['category_id'] === (string)$selectedCategory ? 'active' : ''; ?>">
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        <small><?php echo (int)$cat['product_count']; ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <div class="products-content">
            <?php if ($isSearching && $searchInfo): ?>
                <div class="search-insight">
                    <?php if (!empty($searchInfo['didYouMean'])): ?>
                        <p class="search-insight-dym">Did you mean
                            <a href="<?php echo htmlspecialchars('index.php?' . http_build_query(['page' => 'products', 'q' => $searchInfo['didYouMean']])); ?>"><?php echo htmlspecialchars($searchInfo['didYouMean']); ?></a>?
                        </p>
                    <?php endif; ?>
                    <?php if ($searchInfo['relaxed'] > 0): ?>
                        <p class="search-insight-note">No exact match for everything you asked, so these are the closest results.</p>
                    <?php endif; ?>
                    <?php if (!empty($searchInfo['chips'])): ?>
                        <div class="search-chips">
                            <span>We understood:</span>
                            <?php foreach ($searchInfo['chips'] as $chip): ?>
                                <span class="search-chip"><?php echo htmlspecialchars($chip); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <a class="search-clear" href="index.php?page=products">Clear search</a>
                </div>
            <?php endif; ?>
            <div class="filter-area">
                <div class="filter-main">
                    <div class="filter-heading">
                        <span class="filter-mark">☷</span>
                        <div>
                            <strong>Shop by category</strong>
                            <span><?php echo $totalProducts; ?> products</span>
                        </div>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo htmlspecialchars(productPageUrl(1, 'All', $selectedSort, $productsPerPage)); ?>"
                           class="filter-button <?php echo $selectedCategory === 'All' ? 'active' : ''; ?>">All</a>

                        <?php foreach ($dbCategories as $cat): ?>
                            <a href="<?php echo htmlspecialchars(productPageUrl(1, (string)$cat['category_id'], $selectedSort, $productsPerPage)); ?>"
                               class="filter-button <?php echo (string)$cat['category_id'] === (string)$selectedCategory ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="GET" action="index.php" class="product-controls">
                        <input type="hidden" name="page" value="products">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>">
                        <input type="hidden" name="product_page" value="1">
                        <?php if ($isSearching): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>"><?php endif; ?>

                        <label class="sort-label">
                            <span>Sort</span>
                            <select name="sort" onchange="this.form.submit()">
                                <?php if ($isSearching): ?><option value="relevance" <?php echo $selectedSort === 'relevance' ? 'selected' : ''; ?>>Best match</option><?php endif; ?>
                                <option value="newest" <?php echo $selectedSort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="oldest" <?php echo $selectedSort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                <option value="lowest" <?php echo $selectedSort === 'lowest' ? 'selected' : ''; ?>>Lowest Price</option>
                                <option value="highest" <?php echo $selectedSort === 'highest' ? 'selected' : ''; ?>>Highest Price</option>
                            </select>
                        </label>

                        <label class="sort-label">
                            <span>View</span>
                            <select name="per_page" onchange="this.form.submit()">
                                <option value="10" <?php echo $productsPerPage === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $productsPerPage === 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo $productsPerPage === 30 ? 'selected' : ''; ?>>30</option>
                            </select>
                        </label>
                    </form>
                </div>

                <div class="filter-meta">
                    <span>Showing <?php echo $totalProducts ? ($offset + 1) : 0; ?>–<?php echo min($offset + count($dbProducts), $totalProducts); ?> of <?php echo $totalProducts; ?></span>
                    <span class="filter-divider"></span>
                    <span>Page <?php echo $productPage; ?> of <?php echo $totalPages; ?></span>
                </div>
            </div>

            <div class="product-list products-grid" id="productList">
                <?php foreach ($dbProducts as $product): ?>
                    <div class="product"
                        data-id="<?php echo (int)$product['product_id']; ?>"
                        data-category="<?php echo (int)$product['category_id']; ?>"
                        data-price="<?php echo htmlspecialchars($product['price']); ?>"
                        role="link" tabindex="0" onclick="if (!event.target.closest('button, a')) window.location.href='index.php?page=productDetails&product_id=<?php echo (int)$product['product_id']; ?>'"
                        onkeydown="if (event.key === 'Enter') window.location.href='index.php?page=productDetails&product_id=<?php echo (int)$product['product_id']; ?>'">
                        <div class="product-image">
                            <?php $imgSrc = productImageSrc($product['image_path']); ?>
                            <?php if ($imgSrc !== ''): ?>
                                <img
                                    src="<?php echo htmlspecialchars($imgSrc); ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    loading="lazy"
                                    style="width:100%;height:100%;object-fit:contain;display:block;"
                                    onerror="this.style.display='none'">
                            <?php endif; ?>
                            <button class="heart">♡</button>
                        </div>

                        <div class="product-info">
                            <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                            <h3 title="<?php echo htmlspecialchars($product['name']); ?>"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="brand"><?php echo htmlspecialchars($product['brand'] ?? ''); ?></p>
                            <?php echo renderStars($product['avg_rating'] ?? 0, $product['total_reviews'] ?? 0); ?>

                            <div class="product-bottom">
                                <strong>₱<?php echo number_format($product['price'], 2); ?></strong>
                                <div class="product-buttons">
                                    <button class="add-cart-button" data-id="<?php echo (int)$product['product_id']; ?>">Add to Cart</button>
                                    <button class="buy-button" data-id="<?php echo (int)$product['product_id']; ?>">Buy</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($dbProducts)): ?>
                    <div class="no-products"><?php echo $isSearching ? 'No products found for “' . htmlspecialchars($searchQuery) . '”. Try a different word, a brand, or a category like running or basketball.' : 'No products available right now.'; ?></div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination" id="productPagination">
                    <?php if ($productPage > 1): ?>
                        <a href="<?php echo htmlspecialchars(productPageUrl($productPage - 1, $selectedCategory, $selectedSort, $productsPerPage)); ?>" class="pagination-button">‹</a>
                    <?php endif; ?>

                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a href="<?php echo htmlspecialchars(productPageUrl($pageNumber, $selectedCategory, $selectedSort, $productsPerPage)); ?>"
                           class="pagination-button <?php echo $pageNumber === $productPage ? 'active' : ''; ?>">
                            <?php echo $pageNumber; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($productPage < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars(productPageUrl($productPage + 1, $selectedCategory, $selectedSort, $productsPerPage)); ?>" class="pagination-button">›</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>