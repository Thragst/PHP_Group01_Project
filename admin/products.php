<?php
session_start();
include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Product Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Manage store products, pricing, and real-time inventory levels.</p>
    </div>
    <button class="btn-primary" onclick="openModal('productModal')">+ Add New Product</button>
</div>

<div class="admin-card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <input type="text" placeholder="Search product name or brand..." style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;">
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
            <tr>
                <td>Nike Air Zoom Pegasus 40</td>
                <td>Nike</td>
                <td>Running</td>
                <td>₱5,995.00</td>
                <td><span class="badge badge-active">18 In Stock</span></td>
                <td><button class="btn-action" onclick="openModal('productModal')">Edit</button></td>
            </tr>
            <tr>
                <td>Molten Official Basketball</td>
                <td>Molten</td>
                <td>Basketball</td>
                <td>₱2,450.00</td>
                <td><span class="badge badge-locked">2 Low Stock</span></td>
                <td><button class="btn-action" onclick="openModal('productModal')">Edit</button></td>
            </tr>
            <tr>
                <td>Adidas Defender Duffel Bag</td>
                <td>Adidas</td>
                <td>Fitness</td>
                <td>₱1,795.00</td>
                <td><span class="badge badge-active">12 In Stock</span></td>
                <td><button class="btn-action" onclick="openModal('productModal')">Edit</button></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="productModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add / Edit Product</h3>
            <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
        </div>
        
        <form action="products.php" method="POST" enctype="multipart/form-data" onsubmit="return false;">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" placeholder="e.g. Nike Air Zoom Pegasus 40">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" placeholder="e.g. Nike">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select>
                        <option>Basketball</option>
                        <option>Football</option>
                        <option>Running</option>
                        <option>Fitness</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" placeholder="0">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; font-family:inherit;" rows="3" placeholder="Enter product details..."></textarea>
            </div>

            <div class="form-group">
                <label>Product Display Image <small style="color: #666;">(Max: 20MB)</small></label>
                <input type="file" id="productImage" name="product_image" accept="image/png, image/jpeg, image/webp" onchange="validateFileSize(this)">
                <small id="fileError" style="color: #C62828; display: none; margin-top: 5px; font-weight: bold;"></small>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <button type="button" class="btn-danger" onclick="closeModal('productModal')">Delete</button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('productModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function validateFileSize(input) {
    const errorElement = document.getElementById('fileError');
    const maxSizeBytes = 20 * 1024 * 1024; // 20MB

    if (input.files && input.files[0]) {
        if (input.files[0].size > maxSizeBytes) {
            errorElement.textContent = "File size exceeds 20MB limit! Please select a smaller image.";
            errorElement.style.display = "block";
            input.value = ""; 
        } else {
            errorElement.style.display = "none";
        }
    }
}
</script>

<?php echo '</main></div></div></body></html>'; ?>