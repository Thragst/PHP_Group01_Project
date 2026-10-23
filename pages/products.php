<?php
$allowedPages = [10, 20, 30];
$productPage = max(1,(int)($_GET['product_page'] ?? 1));
$productsPerPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($productsPerPage, $allowedPages, true)) {$productsPerPage = 20;}
$selectedCategory = $_GET['category'] ?? 'All';
$allowedSorts = ['newest' => 'p.product_id DESC', 'oldest' => 'p.product_id ASC', 'lowest' => 'p.price ASC', 'highest' => 'p.price DESC'];
$selectedSort = $_GET['sort'] ?? 'newest';
if (!isset($allowedSorts[$selectedSort])) {$selectedSort = 'newest';}
$catStmt = $pdo->query("SELECT c.category_id, c.name, COUNT(p.product_id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.category_id AND p.status = 'Active' WHERE c.status = 'Active' GROUP BY c.category_id, c.name HAVING COUNT(p.product_id) > 0 ORDER BY c.category_id ASC");
$dbCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
$where = ["p.status = 'Active'", "c.status = 'Active'"];
$params = [];
if ($selectedCategory !== 'All') {$categoryId = filter_var($selectedCategory, FILTER_VALIDATE_INT); 
if ($categoryId === false || $categoryId <= 0) {$selectedCategory = 'All';} else {
    $where[] = "p.category_id = ?";
    $params[] = $categoryId;}}
$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON c.category_id = p.category_id WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1,(int)ceil($totalProducts / $productsPerPage));
if ($productPage > $totalPages) {$productPage = $totalPages;}
$offset = ($productPage - 1) * $productsPerPage;
$productSql = "SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.category_id, c.name AS category FROM products p JOIN categories c ON c.category_id = p.category_id WHERE $whereSql ORDER BY {$allowedSorts[$selectedSort]} LIMIT $productsPerPage OFFSET $offset";
$stmt = $pdo->prepare($productSql);
$stmt->execute($params);
$dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
function productPageUrl(int $page, string $category, string $sort, int $perPage): string { return 'index.php?' . http_build_query([ 'page' => 'products', 'category' => $category, 'sort' => $sort, 'per_page' => $perPage, 'product_page' => $page ]); }
?>

<section class="products-page">
    <div class="products-heading">
        <p class="small-title">SPORTMART SHOP</p>
        <h1>Sports Products</h1>
        <p>Find the sports equipment you need for your game.</p>
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

                        <label class="sort-label">
                            <span>Sort</span>
                            <select name="sort" onchange="this.form.submit()">
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
                            <div class="stars">★★★★★</div>

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
                    <div class="no-products">No products available right now.</div>
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
