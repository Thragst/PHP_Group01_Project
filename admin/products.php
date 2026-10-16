<?php
session_start();

require_once '../includes/db.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff'])) {
    $_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Developer';
    $_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Developer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_product') {
        $productId = isset($_POST['product_id']) && !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $name = trim($_POST['name']);
        $brand = trim($_POST['brand']);
        $categoryId = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock_quantity'];
        $description = trim($_POST['description']);
        $status = $_POST['status'] ?? 'Active';
        
        $imagePath = $_POST['existing_image'] ?? 'assets/images/default.png';

        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['product_image']['tmp_name'];
            $fileName = $_FILES['product_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = '../assets/images/';
                
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $dest_path = $uploadFileDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagePath = 'assets/images/' . $newFileName;
                }
            }
        }

        if ($productId) {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, brand = ?, description = ?, price = ?, stock_quantity = ?, image_path = ?, status = ? WHERE product_id = ?");
            $stmt->execute([$categoryId, $name, $brand, $description, $price, $stock, $imagePath, $status, $productId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, brand, description, price, stock_quantity, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $name, $brand, $description, $price, $stock, $imagePath, $status]);
        }
        
        header('Location: products.php');
        exit();
    }

    if ($_POST['action'] === 'delete_product' && isset($_POST['product_id'])) {
        $productId = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        header('Location: products.php');
        exit();
    }
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

switch ($sort) {
    case 'stock_asc':
        $orderBy = "p.stock_quantity ASC, p.product_id DESC";
        break;
    case 'stock_desc':
        $orderBy = "p.stock_quantity DESC, p.product_id DESC";
        break;
    case 'price_asc':
        $orderBy = "p.price ASC";
        break;
    case 'price_desc':
        $orderBy = "p.price DESC";
        break;
    default:
        $orderBy = "p.product_id DESC";
        break;
}

if (!empty($search)) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ?");
    $searchTerm = "%$search%";
    $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $totalProducts = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.category_id 
                           WHERE p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ? 
                           ORDER BY $orderBy 
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(3, $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->bindValue(5, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
} else {
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.category_id 
                           ORDER BY $orderBy 
                           LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
}

$totalPages = ceil($totalProducts / $limit);
$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name ASC")->fetchAll();

include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Product Management</h2>
        <p class="text-muted-sm">Manage store products, pricing, and real-time inventory levels.</p>
    </div>
    <button class="btn-primary" onclick="openAddModal()">+ Add New Product</button>
</div>

<div class="admin-card">
    <div style="margin-bottom: 20px;">
        <form method="GET" action="products.php" class="filter-group">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search product name or brand..." style="padding: 10px 15px; border: 1px solid var(--border-color, #ccc); border-radius: 6px; width: 300px;">
            <button type="submit" class="btn-primary" style="padding: 10px 18px;">Search</button>

            <select name="sort" class="filter-select" onchange="this.form.submit()">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest Added</option>
                <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Sort: Lowest Stock First</option>
                <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Sort: Highest Stock First</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Sort: Price Low to High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Sort: Price High to Low</option>
            </select>
        </form>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Brand</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $prod): ?>
                    <?php 
                        $path = trim($prod['image_path'] ?? '');
                        if (empty($path)) {
                            $imgSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="%23aaa" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
                        } elseif (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                            $imgSrc = $path;
                        } else {
                            $cleanPath = ltrim($path, '/');
                            if (str_starts_with(strtolower($cleanPath), 'admin/')) {
                                $cleanPath = substr($cleanPath, 6);
                            }
                            $imgSrc = '../' . $cleanPath;
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" class="product-thumb" alt="<?= htmlspecialchars($prod['name']) ?>" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23aaa\' stroke-width=\'2\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><path d=\'M21 15l-5-5L5 21\'/></svg>';">
                                <span style="font-weight: 500; color: #222;"><?= htmlspecialchars($prod['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($prod['brand']) ?></td>
                        <td><?= htmlspecialchars($prod['category_name'] ?? 'Uncategorized') ?></td>
                        <td style="font-weight: 600;">₱<?= number_format($prod['price'], 2) ?></td>
                        <td>
                            <?php if ($prod['stock_quantity'] <= 5): ?>
                                <span class="badge badge-locked"><?= $prod['stock_quantity'] ?> Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-active"><?= $prod['stock_quantity'] ?> In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-action" onclick='openEditModal(<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #777;">No products found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <?= min($offset + 1, $totalProducts) ?> to <?= min($offset + $limit, $totalProducts) ?> of <?= $totalProducts ?> products
            </div>
            <div class="pagination-controls">
                <a href="?page=<?= max(1, $page - 1) ?>&sort=<?= $sort ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">Previous</a>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1) {
                    echo '<a href="?page=1&sort=' . $sort . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn">1</a>';
                    if ($startPage > 2) {
                        echo '<span style="padding: 0 4px; color: #888;">...</span>';
                    }
                }

                for ($i = $startPage; $i <= $endPage; $i++) {
                    $activeClass = ($i === $page) ? 'active' : '';
                    echo '<a href="?page=' . $i . '&sort=' . $sort . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn ' . $activeClass . '">' . $i . '</a>';
                }

                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span style="padding: 0 4px; color: #888;">...</span>';
                    }
                    echo '<a href="?page=' . $totalPages . '&sort=' . $sort . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="page-btn">' . $totalPages . '</a>';
                }
                ?>

                <a href="?page=<?= min($totalPages, $page + 1) ?>&sort=<?= $sort ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="productModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add / Edit Product</h3>
            <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
        </div>
        
        <form action="products.php" method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" id="productId">
            <input type="hidden" name="existing_image" id="existingImage">

            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" id="productName" placeholder="e.g. Nike Air Zoom Pegasus 40" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" id="productBrand" placeholder="e.g. Nike" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="productCategory" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" step="0.01" name="price" id="productPrice" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="productStock" placeholder="0" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="productDescription" style="width:100%; padding:10px; border:1px solid var(--border-color, #ccc); border-radius:6px; font-family:inherit;" rows="3" placeholder="Enter product details..."></textarea>
            </div>

            <div class="form-group">
                <label>Product Display Image <small style="color: #666;">(Max: 10MB)</small></label>
                <input type="file" id="productImage" name="product_image" accept="image/png, image/jpeg, image/webp" onchange="validateFileSize(this)">
                <small id="fileError" style="color: #C62828; display: none; margin-top: 5px; font-weight: bold;"></small>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; border-top: 1px solid var(--border-color, #eee); padding-top: 15px;">
                <button type="button" class="btn-danger" id="deleteBtn" style="display: none;" onclick="deleteProduct()">Delete</button>
                <div style="display: flex; gap: 10px; margin-left: auto;">
                    <button type="button" class="btn-secondary" onclick="closeModal('productModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Product</button>
                </div>
            </div>
        </form>

        <form action="products.php" method="POST" id="deleteForm" style="display:none;">
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" id="deleteProductId">
        </form>
    </div>
</div>

<?php echo '</main></div></div></body></html>'; ?>