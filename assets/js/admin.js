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

function validateFileSize(input) {
    const fileError = document.getElementById('fileError');
    if (input.files && input.files[0]) {
        const fileSizeMB = input.files[0].size / (1024 * 1024);
        if (fileSizeMB > 10) {
            if (fileError) {
                fileError.textContent = 'File size exceeds 10MB limit.';
                fileError.style.display = 'block';
            }
            input.value = '';
        } else if (fileError) {
            fileError.style.display = 'none';
        }
    }
}

//Orders
window.openOrderModal = function(btn) {
    if (!btn) return;

    var orderId = btn.getAttribute('data-order-id');
    var status = btn.getAttribute('data-status');
    var driverId = btn.getAttribute('data-driver-id');
    var address = btn.getAttribute('data-address');
    var itemsJson = btn.getAttribute('data-items');

    var modalOrderId = document.getElementById('modalOrderId');
    var modalStatusSelect = document.getElementById('modalStatusSelect');
    var modalDriverSelect = document.getElementById('modalDriverSelect');
    var modalShippingAddress = document.getElementById('modalShippingAddress');
    var container = document.getElementById('modalItemsContainer');

    if (modalOrderId) modalOrderId.value = '#ORD-' + orderId;
    if (modalStatusSelect) modalStatusSelect.value = status;
    if (modalDriverSelect) modalDriverSelect.value = driverId || '';
    if (modalShippingAddress) modalShippingAddress.value = address || 'N/A';

    if (container) {
        container.innerHTML = '';
        try {
            var items = JSON.parse(itemsJson || '[]');
            if (!items || items.length === 0) {
                container.innerHTML = '<p class="text-muted-sm" style="margin: 0;">No item details available.</p>';
            } else {
                var listHtml = '<ul class="modal-item-list">';
                items.forEach(function(item) {
                    var price = parseFloat(item.price_at_purchase).toFixed(2);
                    listHtml += '<li><strong>' + item.product_name + '</strong> &times; ' + item.quantity + ' <span class="text-muted-sm">(₱' + price + ' each)</span></li>';
                });
                listHtml += '</ul>';
                container.innerHTML = listHtml;
            }
        } catch (e) {
            container.innerHTML = '<p class="text-muted-sm" style="margin: 0;">Error loading items.</p>';
        }
    }

    var modal = document.getElementById('statusModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
};

window.closeModal = function(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
};

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
        e.target.classList.remove('active');
    }
});

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

//Users
function setElementText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

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

function openStaffModal() {
    setElementText('staffModalTitle', 'Create Staff Account');
    
    const actionEl = document.getElementById('staffAction');
    if (actionEl) actionEl.value = 'create_staff';

    const userIdEl = document.getElementById('staffUserId');
    if (userIdEl) userIdEl.value = '';

    const nameEl = document.getElementById('staffName');
    if (nameEl) nameEl.value = '';

    const emailEl = document.getElementById('staffEmail');
    if (emailEl) emailEl.value = '';

    const roleEl = document.getElementById('staffRole');
    if (roleEl) roleEl.value = 'Staff';

    const statusGroupEl = document.getElementById('staffStatusGroup');
    if (statusGroupEl) statusGroupEl.style.display = 'none';

    const passEl = document.getElementById('staffPassword');
    if (passEl) passEl.required = true;

    setElementText('staffPasswordLabel', 'Password');
    
    openModal('staffModal');
}

function openEditPermissionsModal(user) {
    setElementText('staffModalTitle', 'Edit Account Permissions');

    const actionEl = document.getElementById('staffAction');
    if (actionEl) actionEl.value = 'update_staff';

    const userIdEl = document.getElementById('staffUserId');
    if (userIdEl) userIdEl.value = user.user_id;

    const nameEl = document.getElementById('staffName');
    if (nameEl) nameEl.value = user.full_name || user.name || '';

    const emailEl = document.getElementById('staffEmail');
    if (emailEl) emailEl.value = user.email || '';

    const roleEl = document.getElementById('staffRole');
    if (roleEl) roleEl.value = user.role || 'Staff';

    const statusEl = document.getElementById('staffStatus');
    if (statusEl) statusEl.value = user.status || 'Active';

    const statusGroupEl = document.getElementById('staffStatusGroup');
    if (statusGroupEl) statusGroupEl.style.display = 'block';

    const passEl = document.getElementById('staffPassword');
    if (passEl) passEl.required = false;

    setElementText('staffPasswordLabel', 'New Password (Leave blank to keep current)');
    
    openModal('staffModal');
}

function openCustomerProfileModal(customer) {
    setElementText('profileName', customer.full_name || customer.name || 'N/A');
    setElementText('profileUsername', customer.username ? customer.username : 'N/A');
    setElementText('profileEmail', customer.email || 'N/A');
    setElementText('profileDate', customer.created_at || 'N/A');
    setElementText('profileStatus', customer.status || 'Active');
    setElementText('profilePhone', customer.phone || 'Not Provided');
    setElementText('profileAddress', customer.physical_address || 'No address saved');
    setElementText('profileOrdersCount', customer.total_orders || '0');
    setElementText('profileTotalSpent', customer.total_spent ? '₱' + parseFloat(customer.total_spent).toFixed(2) : '₱0.00');
    setElementText('profileFailedAttempts', customer.failed_attempts || '0');

    openModal('customerProfileModal');
}

function filterStaffTable() {
    const searchInput = document.getElementById('staffSearchInput');
    const roleSelect = document.getElementById('staffRoleFilter');
    if (!searchInput || !roleSelect) return;

    const search = searchInput.value.toLowerCase();
    const role = roleSelect.value.toLowerCase();
    const rows = document.getElementsByClassName('staff-row');

    for (let i = 0; i < rows.length; i++) {
        const nameEl = rows[i].querySelector('.staff-name');
        const emailEl = rows[i].querySelector('.staff-email');
        const roleEl = rows[i].querySelector('.staff-role');

        const name = nameEl ? nameEl.innerText.toLowerCase() : '';
        const email = emailEl ? emailEl.innerText.toLowerCase() : '';
        const staffRole = roleEl ? roleEl.innerText.toLowerCase() : '';

        const matchesSearch = name.includes(search) || email.includes(search);
        const matchesRole = role === 'all' || staffRole === role;

        rows[i].style.display = (matchesSearch && matchesRole) ? '' : 'none';
    }
}

function filterCustomerTable() {
    const searchInput = document.getElementById('customerSearchInput');
    const statusSelect = document.getElementById('customerStatusFilter');
    if (!searchInput || !statusSelect) return;

    const search = searchInput.value.toLowerCase();
    const status = statusSelect.value.toLowerCase();
    const rows = document.getElementsByClassName('customer-row');

    for (let i = 0; i < rows.length; i++) {
        const nameEl = rows[i].querySelector('.cust-name');
        const emailEl = rows[i].querySelector('.cust-email');
        const statusEl = rows[i].querySelector('.cust-status');

        const name = nameEl ? nameEl.innerText.toLowerCase() : '';
        const email = emailEl ? emailEl.innerText.toLowerCase() : '';
        const custStatus = statusEl ? statusEl.innerText.toLowerCase() : '';

        const matchesSearch = name.includes(search) || email.includes(search);
        const matchesStatus = status === 'all' || 
            (status === 'locked' && custStatus.includes('locked')) || 
            (status === 'active' && custStatus === 'active');

        rows[i].style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    }
}

//Inactivity
if (typeof SESSION_TIMEOUT_MINUTES !== 'undefined' && SESSION_TIMEOUT_MINUTES > 0) {
    let idleTimer;
    const idleTimeMs = SESSION_TIMEOUT_MINUTES * 60 * 1000;

    function createInactivityModal() {
        if (document.getElementById('inactivityModal')) return;

        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';
        modalOverlay.id = 'inactivityModal';

        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content modal-xs';

        const modalHeader = document.createElement('div');
        modalHeader.className = 'modal-header-center';
        
        const title = document.createElement('h3');
        title.textContent = 'Session Expired';
        modalHeader.appendChild(title);

        const modalBody = document.createElement('div');
        modalBody.className = 'inactivity-body';

        const message = document.createElement('p');
        message.className = 'inactivity-text';
        message.textContent = 'You have been logged out due to inactivity.';

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'btn-primary';
        confirmBtn.textContent = 'OK / Login Again';
        confirmBtn.onclick = function() {
            window.location.href = '../pages/login.php?action=logout&timeout=1';
        };

        modalBody.appendChild(message);
        modalBody.appendChild(confirmBtn);

        modalContent.appendChild(modalHeader);
        modalContent.appendChild(modalBody);
        modalOverlay.appendChild(modalContent);

        document.body.appendChild(modalOverlay);
    }

    function triggerInactivityLogout() {
        createInactivityModal();
        openModal('inactivityModal');
    }

    function resetIdleTimer() {
        const modal = document.getElementById('inactivityModal');
        if (modal && modal.classList.contains('active')) return;

        clearTimeout(idleTimer);
        idleTimer = setTimeout(triggerInactivityLogout, idleTimeMs);
    }

    ['mousemove', 'keydown', 'click', 'scroll'].forEach(function(evt) {
        document.addEventListener(evt, resetIdleTimer, true);
    });

    resetIdleTimer();
}