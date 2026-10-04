<?php
session_start();
include 'includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">Track customer orders, review payment methods, and update order statuses.</p>
    </div>
</div>

<div class="admin-card">
    <div style="margin-bottom: 20px; display: flex; gap: 10px; justify-content: space-between; align-items: center;">
        <input type="text" placeholder="Search by Order ID or Customer Name..." style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;">
        <select style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px;">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Shipped">Shipped</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>

    <table class="admin-table">
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
            <tr>
                <td>#ORD-1001</td>
                <td>Juan Dela Cruz</td>
                <td>Oct 2, 2026</td>
                <td>₱3,450.00</td>
                <td>GCash</td>
                <td><span class="badge badge-pending">Pending</span></td>
                <td><button class="btn-action" onclick="openStatusModal('ORD-1001', 'Pending')">Update Status</button></td>
            </tr>
            <tr>
                <td>#ORD-1002</td>
                <td>Maria Santos</td>
                <td>Oct 1, 2026</td>
                <td>₱5,995.00</td>
                <td>Cash on Delivery</td>
                <td><span class="badge badge-shipped">Shipped</span></td>
                <td><button class="btn-action" onclick="openStatusModal('ORD-1002', 'Shipped')">Update Status</button></td>
            </tr>
            <tr>
                <td>#ORD-1003</td>
                <td>Mark Reyes</td>
                <td>Sep 28, 2026</td>
                <td>₱1,850.00</td>
                <td>Credit Card</td>
                <td><span class="badge badge-delivered">Delivered</span></td>
                <td><button class="btn-action" onclick="openStatusModal('ORD-1003', 'Delivered')">Update Status</button></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Update Order Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        
        <form action="orders.php" method="POST" onsubmit="return false;">
            <div class="form-group">
                <label>Order Reference</label>
                <input type="text" id="modalOrderId" readonly style="background-color: #F3F3F3; font-weight: bold;">
            </div>

            <div class="form-group">
                <label>Order Status</label>
                <select id="modalStatusSelect">
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
function openStatusModal(orderId, currentStatus) {
    document.getElementById('modalOrderId').value = '#' + orderId;
    document.getElementById('modalStatusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>

<?php echo '</main></div></div></body></html>'; ?>