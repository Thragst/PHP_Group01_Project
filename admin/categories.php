<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';

    if ($action === 'save') {
        if (!empty($name)) {
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            }

            if ($categoryId > 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, status = ? WHERE category_id = ?");
                $stmt->execute([$name, $slug, $description, $status, $categoryId]);
                $logDetails = "Updated category #$categoryId ($name)";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $status]);
                $newId = $pdo->lastInsertId();
                $logDetails = "Created new category #$newId ($name)";
            }

            if (isset($_SESSION['user_id'])) {
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    $logDetails,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
            }
        }
    } elseif ($action === 'delete' && $categoryId > 0) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $checkStmt->execute([$categoryId]);
        $assignedProducts = (int)$checkStmt->fetchColumn();

        if ($assignedProducts > 0) {
            $_SESSION['flash_error'] = "Cannot delete category #$categoryId ($name) because it currently has $assignedProducts assigned product(s).";
        } else {
            $delStmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
            $delStmt->execute([$categoryId]);

            if (isset($_SESSION['user_id'])) {
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    "Deleted category #$categoryId ($name)",
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
            }
        }
    }

    header('Location: categories.php');
    exit();
}

$categoriesQuery = "SELECT 
                        c.category_id, 
                        c.name, 
                        c.slug, 
                        c.description, 
                        c.status,
                        COUNT(p.product_id) AS product_count
                    FROM categories c
                    LEFT JOIN products p ON c.category_id = p.category_id
                    GROUP BY c.category_id
                    ORDER BY c.category_id ASC";

$categories = $pdo->query($categoriesQuery)->fetchAll();

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Category Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Organize store products by sports discipline, gear types, and collections.</p>
    </div>
    <button class="btn-primary" onclick="openAddCategoryModal()">+ Add New Category</button>
</div>

<?php if (!empty($flashError)): ?>
    <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.95rem;">
        ⚠️ <?php echo htmlspecialchars($flashError); ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <input type="text" id="categorySearch" onkeyup="filterCategories()" placeholder="Search category name or slug..." style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;">
    </div>

    <table class="admin-table" id="categoriesTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Category Name</th>
                <th>Slug</th>
                <th>Products Count</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <tr class="category-row">
                        <td><?php echo $cat['category_id']; ?></td>
                        <td class="cat-name" style="font-weight: 600;"><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td class="cat-slug" style="color: #666; font-family: monospace;"><?php echo htmlspecialchars($cat['slug']); ?></td>
                        <td><?php echo (int)$cat['product_count']; ?> Product<?php echo $cat['product_count'] == 1 ? '' : 's'; ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($cat['status']) === 'active' ? 'active' : 'locked'; ?>">
                                <?php echo htmlspecialchars($cat['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn-action" onclick="openEditCategoryModal(
                                '<?php echo $cat['category_id']; ?>', 
                                '<?php echo htmlspecialchars(addslashes($cat['name'])); ?>', 
                                '<?php echo htmlspecialchars(addslashes($cat['slug'])); ?>', 
                                '<?php echo htmlspecialchars(addslashes($cat['description'] ?? '')); ?>', 
                                '<?php echo $cat['status']; ?>'
                            )">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #777;">No categories found in database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3 id="categoryModalTitle">Add New Category</h3>
            <button class="modal-close" onclick="closeModal('categoryModal')" type="button">&times;</button>
        </div>
        
        <form action="categories.php" method="POST" id="categoryForm">
            <input type="hidden" name="action" id="categoryAction" value="save">
            <input type="hidden" name="category_id" id="categoryId">

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Category Name</label>
                <input type="text" name="name" id="categoryName" required placeholder="e.g. Badminton" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;" onkeyup="autoGenerateSlug()">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Slug</label>
                <input type="text" name="slug" id="categorySlug" required placeholder="e.g. badminton" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Description</label>
                <textarea name="description" id="categoryDescription" rows="3" placeholder="Enter category details..." style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:inherit;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Status</label>
                <select name="status" id="categoryStatus" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <button type="button" class="btn-danger" id="deleteCategoryBtn" onclick="confirmDeleteCategory()" style="display: none;">Delete</button>
                <div style="display: flex; gap: 10px; margin-left: auto;">
                    <button type="button" class="btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>

<?php echo '</main></div></div></body></html>'; ?>