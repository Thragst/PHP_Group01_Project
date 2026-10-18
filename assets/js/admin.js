//Admin Includes
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

function validateFileSize(input) {
    const errorElement = document.getElementById('fileError');
    const maxSizeBytes = 10 * 1024 * 1024;

    if (input.files && input.files[0]) {
        if (input.files[0].size > maxSizeBytes) {
            if (errorElement) {
                errorElement.textContent = "File size exceeds 10MB limit! Please select a smaller image.";
                errorElement.style.display = "block";
            }
            input.value = ""; 
        } else if (errorElement) {
            errorElement.style.display = "none";
        }
    }
}

// Product Management
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Product';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productBrand').value = '';
    document.getElementById('productCategory').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productStock').value = '';
    document.getElementById('productDescription').value = '';
    document.getElementById('existingImage').value = '';
    
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) deleteBtn.style.display = 'none';
    
    openModal('productModal');
}

function openEditModal(product) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('productId').value = product.product_id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productBrand').value = product.brand;
    document.getElementById('productCategory').value = product.category_id;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productStock').value = product.stock_quantity;
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('existingImage').value = product.image_path || '';
    
    const deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) deleteBtn.style.display = 'block';
    
    openModal('productModal');
}

function deleteProduct() {
    const id = document.getElementById('productId').value;
    if (id && confirm('Are you sure you want to delete this product?')) {
        document.getElementById('deleteProductId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

//Orders
function filterTable() {
    let searchFilter = document.getElementById('searchInput').value.toLowerCase();
    let statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    let rows = document.getElementsByClassName('order-row');

    for (let i = 0; i < rows.length; i++) {
        let orderId = rows[i].querySelector('.order-id').innerText.toLowerCase();
        let customer = rows[i].querySelector('.customer-name').innerText.toLowerCase();
        let status = rows[i].querySelector('.order-status').innerText.toLowerCase();

        let matchesSearch = orderId.includes(searchFilter) || customer.includes(searchFilter);
        let matchesStatus = statusFilter === "" || status === statusFilter;

        if (matchesSearch && matchesStatus) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}

function openStatusModal(orderId, currentStatus, currentDriverId = '') {
    document.getElementById('modalOrderId').value = '#ORD-' + orderId;
    document.getElementById('modalStatusSelect').value = currentStatus;
    
    let driverSelect = document.getElementById('modalDriverSelect');
    if (driverSelect) {
        driverSelect.value = currentDriverId;
    }
    
    document.getElementById('statusModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

//Categories
let isCategoryEditMode = false;

function openAddCategoryModal() {
    isCategoryEditMode = false;
    document.getElementById('categoryModalTitle').textContent = 'Add New Category';
    document.getElementById('categoryAction').value = 'save';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categorySlug').value = '';
    document.getElementById('categoryDescription').value = '';
    document.getElementById('categoryStatus').value = 'Active';
    document.getElementById('deleteCategoryBtn').style.display = 'none';
    
    openModal('categoryModal');
}

function openEditCategoryModal(id, name, slug, description, status) {
    isCategoryEditMode = true;
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    document.getElementById('categoryAction').value = 'save';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('categorySlug').value = slug;
    document.getElementById('categoryDescription').value = description;
    document.getElementById('categoryStatus').value = status;
    document.getElementById('deleteCategoryBtn').style.display = 'block';
    
    openModal('categoryModal');
}

function autoGenerateSlug() {
    if (!isCategoryEditMode) {
        const nameInput = document.getElementById('categoryName').value;
        const slugInput = nameInput
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9 -]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        document.getElementById('categorySlug').value = slugInput;
    }
}

function confirmDeleteCategory() {
    const catName = document.getElementById('categoryName').value;
    if (confirm(`Are you sure you want to delete category "${catName}"?`)) {
        document.getElementById('categoryAction').value = 'delete';
        document.getElementById('categoryForm').submit();
    }
}

function filterCategories() {
    const filter = document.getElementById('categorySearch').value.toLowerCase();
    const rows = document.getElementsByClassName('category-row');

    for (let i = 0; i < rows.length; i++) {
        const name = rows[i].querySelector('.cat-name').innerText.toLowerCase();
        const slug = rows[i].querySelector('.cat-slug').innerText.toLowerCase();

        if (name.includes(filter) || slug.includes(filter)) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}