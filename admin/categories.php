<?php
session_start();
include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Category Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Organize store products by sports discipline, gear types, and collections.</p>
    </div>
    <button class="btn-primary" onclick="openCategoryModal()">+ Add New Category</button>
</div>

<div class="admin-card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <input type="text" placeholder="Search category name..." style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;">
    </div>

    <table class="admin-table">
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
            <tr>
                <td>1</td>
                <td>Basketball</td>
                <td>basketball</td>
                <td>24 Products</td>
                <td><span class="badge badge-active">Active</span></td>
                <td><button class="btn-action" onclick="openCategoryModal('1', 'Basketball', 'basketball', 'Footwear, balls, and jerseys for basketball.')">Edit</button></td>
            </tr>
            <tr>
                <td>2</td>
                <td>Running</td>
                <td>running</td>
                <td>18 Products</td>
                <td><span class="badge badge-active">Active</span></td>
                <td><button class="btn-action" onclick="openCategoryModal('2', 'Running', 'running', 'Road running shoes, track gear, and hydration accessories.')">Edit</button></td>
            </tr>
            <tr>
                <td>3</td>
                <td>Fitness</td>
                <td>fitness</td>
                <td>15 Products</td>
                <td><span class="badge badge-active">Active</span></td>
                <td><button class="btn-action" onclick="openCategoryModal('3', 'Fitness', 'fitness', 'Gym accessories, duffel bags, and training equipment.')">Edit</button></td>
            </tr>
            <tr>
                <td>4</td>
                <td>Football</td>
                <td>football</td>
                <td>12 Products</td>
                <td><span class="badge badge-locked">Inactive</span></td>
                <td><button class="btn-action" onclick="openCategoryModal('4', 'Football', 'football', 'Cleats, shin guards, and soccer balls.')">Edit</button></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3 id="categoryModalTitle">Add New Category</h3>
            <button class="modal-close" onclick="closeModal('categoryModal')">&times;</button>
        </div>
        
        <form action="categories.php" method="POST" onsubmit="return false;">
            <input type="hidden" id="categoryId">

            <div class="form-group">
                <label>Category Name</label>
                <input type="text" id="categoryName" placeholder="e.g. Badminton">
            </div>

            <div class="form-group">
                <label>Slug</label>
                <input type="text" id="categorySlug" placeholder="e.g. badminton">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="categoryDescription" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; font-family:inherit;" rows="3" placeholder="Enter category details..."></textarea>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="categoryStatus">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <button type="button" class="btn-danger" onclick="closeModal('categoryModal')">Delete</button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal(id = '', name = '', slug = '', description = '', status = 'Active') {
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('categorySlug').value = slug;
    document.getElementById('categoryDescription').value = description;
    document.getElementById('categoryStatus').value = status;
    
    document.getElementById('categoryModalTitle').textContent = id ? 'Edit Category' : 'Add New Category';
    document.getElementById('categoryModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>

<?php echo '</main></div></div></body></html>'; ?>