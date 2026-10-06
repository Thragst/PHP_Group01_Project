<?php
session_start();
include 'includes/admin_header.php';

if (!isset($_SESSION['mock_orders'])) {
    $_SESSION['mock_orders'] = [
        '1001' => ['customer' => 'Juan Dela Cruz', 'date' => 'Oct 2, 2026', 'total' => '₱3,450.00', 'payment' => 'GCash', 'status' => 'Pending'],
        '1002' => ['customer' => 'Maria Santos', 'date' => 'Oct 1, 2026', 'total' => '₱5,995.00', 'payment' => 'Cash on Delivery', 'status' => 'Shipped'],
        '1003' => ['customer' => 'Mark Reyes', 'date' => 'Sep 28, 2026', 'total' => '₱1,850.00', 'payment' => 'Credit Card', 'status' => 'Delivered'],
        '1004' => ['customer' => 'Alex Medina', 'date' => 'Oct 5, 2026', 'total' => '₱2,100.00', 'payment' => 'Bank Transfer', 'status' => 'Processing'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $id = str_replace('#ORD-', '', $_POST['order_id']); 
    if (isset($_SESSION['mock_orders'][$id])) {
        $_SESSION['mock_orders'][$id]['status'] = $_POST['new_status'];
    }
}
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Track customer orders, review payment methods, and update order statuses.</p>
    </div>
</div>

<div class="admin-card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px; justify-content: space-between; align-items: center;">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by Order ID or Customer Name..." style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;">
        <select id="statusFilter" onchange="filterTable()" style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px;">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Processing">Processing</option>
            <option value="Shipped">Shipped</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>

    <table class="admin-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['mock_orders'] as $id => $order): ?>
                <tr class="order-row">
                    <td class="order-id">#ORD-<?php echo $id; ?></td>
                    <td class="customer-name"><?php echo htmlspecialchars($order['customer']); ?></td>
                    <td><?php echo $order['date']; ?></td>
                    <td><?php echo $order['total']; ?></td>
                    <td><?php echo $order['payment']; ?></td>
                    <td class="order-status"><span class="badge badge-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                    <td><button class="btn-action" onclick="openStatusModal('<?php echo $id; ?>', '<?php echo $order['status']; ?>')">Update Status</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Update Order Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')" type="button">&times;</button>
        </div>
        
        <form action="orders.php" method="POST">
            <div class="form-group">
                <label>Order Reference</label>
                <input type="text" id="modalOrderId" name="order_id" readonly style="background-color: #F3F3F3; font-weight: bold;">
            </div>

            <div class="form-group">
                <label>Order Status</label>
                <select id="modalStatusSelect" name="new_status">
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Shipped">Shipped</option>
                    <option value="Delivered">Delivered</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Status</button>
            </div>
        </form>
    </div>
</div>

<script>
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

function openStatusModal(orderId, currentStatus) {
    document.getElementById('modalOrderId').value = '#ORD-' + orderId;
    document.getElementById('modalStatusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>

<?php echo '</main></div></div></body></html>'; ?>