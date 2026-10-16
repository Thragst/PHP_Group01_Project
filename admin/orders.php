
<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tracking_config.php';

if (!canUpdateOrders()) {
    http_response_code(403);
    exit('Access denied.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$sql = "SELECT order_id, status
        FROM orders
        ORDER BY order_id DESC";

$result = $conn->query($sql);
$orders = $result->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p style="color: #666; font-size: 0.95rem; margin-top: 5px;">
            Track customer orders, review payment methods, and update order statuses.
        </p>
    </div>
</div>

<div class="admin-card">

    <div style="margin-bottom: 20px; display: flex; gap: 10px; justify-content: space-between; align-items: center;">

        <input
            type="text"
            id="searchInput"
            onkeyup="filterTable()"
            placeholder="Search by Order ID..."
            style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; width: 320px;"
        >

        <select
            id="statusFilter"
            onchange="filterTable()"
            style="padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px;"
        >
            <option value="">All Statuses</option>

            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>">
                    <?= e($status) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <table class="admin-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($orders as $order): ?>

                <?php
                $orderId = (int) $order['order_id'];
                $currentStatus = $order['status'];
                $nextStatuses = $allowedTransitions[$currentStatus] ?? [];
                ?>

                <tr class="order-row">

                    <td class="order-id">
                        #ORD-<?= $orderId ?>
                    </td>

                    <td class="order-status">
                        <span class="status-badge">
                            <?= e($currentStatus) ?>
                        </span>
                    </td>

                    <td>
                        <?php if (!empty($nextStatuses)): ?>
                            <button
                                type="button"
                                class="btn-primary"
                                onclick="openStatusModal(<?= $orderId ?>, <?= e(json_encode($currentStatus)) ?>)"
                            >
                                Update Status
                            </button>
                        <?php endif; ?>

                        <a
                            href="../track_order.php?order_id=<?= $orderId ?>"
                            class="btn-secondary"
                        >
                            View Tracking
                        </a>
                    </td>

                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($orders)): ?>
        <p>No orders found in the database.</p>
    <?php endif; ?>

</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-content" style="max-width: 450px;">

        <div class="modal-header">
            <h3>Update Order Status</h3>

            <button
                class="modal-close"
                onclick="closeModal('statusModal')"
                type="button"
            >&times;</button>
        </div>

        <form action="update_order_status.php" method="POST">

            <input
                type="hidden"
                id="modalOrderId"
                name="order_id"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">
                <label>Order Reference</label>
                <input
                    type="text"
                    id="modalOrderReference"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>New Order Status</label>

                <select
                    id="modalStatusSelect"
                    name="status"
                    required
                ></select>
            </div>

            <div class="form-group">
                <label>Current NCR Location</label>

                <select name="current_location" id="modalLocation">
                    <option value="">Select NCR City</option>

                    <?php foreach ($ncrCities as $city): ?>
                        <option value="<?= e($city) ?>">
                            <?= e($city) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tracking Remarks</label>

                <textarea
                    name="remarks"
                    rows="3"
                    maxlength="255"
                    placeholder="Example: Arrived at Pasig Sorting Hub"
                ></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 15px;">

                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeModal('statusModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="btn-primary">
                    Save Status
                </button>

            </div>
        </form>
    </div>
</div>

<script>
const allowedTransitions = <?= json_encode(
    $allowedTransitions,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.order-row');

    rows.forEach(row => {
        const orderId = row.querySelector('.order-id').textContent.toLowerCase();
        const currentStatus = row.querySelector('.order-status').textContent.trim().toLowerCase();

        const matchesSearch = orderId.includes(search);
        const matchesStatus = status === '' || currentStatus === status;

        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

function openStatusModal(orderId, currentStatus) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalOrderReference').value = '#ORD-' + orderId;

    const select = document.getElementById('modalStatusSelect');
    select.innerHTML = '';

    const nextStatuses = allowedTransitions[currentStatus] || [];

    nextStatuses.forEach(status => {
        const option = document.createElement('option');
        option.value = status;
        option.textContent = status;
        select.appendChild(option);
    });

    document.getElementById('modalLocation').value = '';

    document.getElementById('statusModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>

<?php echo '</main></div></div></body></html>'; ?>
