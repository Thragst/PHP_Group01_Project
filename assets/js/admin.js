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