<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    header('Location: ../pages/login.php');
    exit();
}

$allStatuses = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled'];

/*
|--------------------------------------------------------------------------
| SAVE CHANGES (status, driver, shipping address, contact number)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
       
    securityCheck();

    $orderId   = (int) preg_replace('/\D+/', '', (string) $_POST['order_id']);
    $newStatus = trim($_POST['new_status']);
    $driverId  = !empty($_POST['driver_id']) ? (int) $_POST['driver_id'] : null;
    $address   = trim($_POST['shipping_address'] ?? '');
    $contact   = trim($_POST['contact_number'] ?? '');

    $redirect = 'orders.php';
    $qs = [];
    foreach (['search', 'status', 'per_page', 'p'] as $keep) {
        if (!empty($_POST['return_' . $keep])) {
            $qs[$keep] = $_POST['return_' . $keep];
        }
    }

    $error = '';

    if ($orderId <= 0) {
        $error = 'invalid_order';
    } elseif (!in_array($newStatus, $allStatuses, true)) {
        $error = 'invalid_status';
    } elseif ($address === '' || mb_strlen($address) > 255) {
        $error = 'invalid_address';
    } elseif ($contact !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $contact)) {
        $error = 'invalid_contact';
    }

    if ($error === '' && $driverId !== null) {
        $driverCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND role = 'Driver'");
        $driverCheck->execute([$driverId]);
        if ((int) $driverCheck->fetchColumn() === 0) {
            $error = 'invalid_driver';
        }
    }

    if ($error === '') {
        $oldStmt = $pdo->prepare("SELECT status, driver_id, shipping_address FROM orders WHERE order_id = ?");
        $oldStmt->execute([$orderId]);
        $old = decryptRow($oldStmt->fetch(), ['shipping_address']);

        if (!$old) {
            $error = 'invalid_order';
        } else {
            $hasContact = tableHasColumnLocal($pdo, 'orders', 'contact_number');

            $sql = "UPDATE orders SET status = ?, driver_id = ?, shipping_address = ?";
            $params = [$newStatus, $driverId, encryptField($address)];

            if ($hasContact) {
                $sql .= ", contact_number = ?";
                $params[] = $contact === '' ? null : encryptField($contact);
            }

            $sql .= " WHERE order_id = ?";
            $params[] = $orderId;

            $pdo->prepare($sql)->execute($params);

            if (isset($_SESSION['user_id'])) {
                $changes = [];
                if ($old['status'] !== $newStatus) {
                    $changes[] = "status '" . $old['status'] . "' -> '" . $newStatus . "'";
                }
                if ((int) $old['driver_id'] !== (int) $driverId) {
                    $changes[] = $driverId ? "assigned to Driver ID #" . $driverId : "driver unassigned";
                }
                if ($old['shipping_address'] !== $address) {
                    $changes[] = "shipping address edited";
                }
                if (empty($changes)) {
                    $changes[] = "details saved";
                }

                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    "Updated order #ORD-" . $orderId . ": " . implode(', ', $changes),
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
            }

            $qs['updated'] = $orderId;
        }
    }

    if ($error !== '') {
        $qs['error'] = $error;
    }

    header('Location: ' . $redirect . (empty($qs) ? '' : '?' . http_build_query($qs)));
    exit();
}

function tableHasColumnLocal(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return ((int) $stmt->fetchColumn()) > 0;
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/
$driversList = $pdo->query(
    "SELECT user_id, full_name, account_status FROM users WHERE role = 'Driver' ORDER BY full_name ASC"
)->fetchAll();

$perPage = isset($_GET['per_page']) && in_array((int) $_GET['per_page'], [10, 25, 50, 100], true) ? (int) $_GET['per_page'] : 10;
$currentPage = isset($_GET['p']) && (int) $_GET['p'] > 0 ? (int) $_GET['p'] : 1;
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereClauses = [];
$params = [];

if ($search !== '') {
    $cleanSearch = str_ireplace('#ORD-', '', $search);
    $whereClauses[] = "(o.order_id LIKE ? OR u.full_name LIKE ? OR d.full_name LIKE ?)";
    $params[] = "%{$cleanSearch}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '' && in_array($statusFilter, $allStatuses, true)) {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
} else {
    $statusFilter = '';
}

$whereSql = empty($whereClauses) ? '' : ' WHERE ' . implode(' AND ', $whereClauses);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders o
     LEFT JOIN users u ON o.user_id = u.user_id
     LEFT JOIN users d ON o.driver_id = d.user_id" . $whereSql
);
$countStmt->execute($params);
$totalOrders = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalOrders / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT o.*,
            COALESCE(u.full_name, 'Guest Customer') AS customer_name,
            u.phone AS customer_phone,
            COALESCE(d.full_name, 'Unassigned') AS driver_name
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.user_id
     LEFT JOIN users d ON o.driver_id = d.user_id
     {$whereSql}
     ORDER BY o.order_id DESC
     LIMIT ? OFFSET ?"
);
$pos = 1;
foreach ($params as $p) {
    $stmt->bindValue($pos++, $p, PDO::PARAM_STR);
}
$stmt->bindValue($pos++, (int) $perPage, PDO::PARAM_INT);
$stmt->bindValue($pos, (int) $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = decryptRows($stmt->fetchAll(), ['shipping_address', 'contact_number', 'customer_phone']);

$itemsByOrder = [];
$orderIds = array_map('intval', array_column($orders, 'order_id'));
if (!empty($orderIds)) {
    $inClause = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price_at_purchase,
                COALESCE(p.name, 'Unknown Product') AS product_name
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id IN ({$inClause})
         ORDER BY oi.order_item_id ASC"
    );
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll() as $item) {
        $itemsByOrder[(int) $item['order_id']][] = [
            'name'     => $item['product_name'],
            'quantity' => (int) $item['quantity'],
            'price'    => (float) $item['price_at_purchase'],
        ];
    }
}

// Everything the "Manage Order" window needs, per order id.
$orderData = [];
foreach ($orders as $order) {
    $id = (int) $order['order_id'];
    $items = $itemsByOrder[$id] ?? [];
    $subtotal = 0;
    $qty = 0;
    foreach ($items as $it) {
        $subtotal += $it['price'] * $it['quantity'];
        $qty += $it['quantity'];
    }
    $total = (float) $order['total_amount'];

    $orderData[$id] = [
        'id'        => $id,
        'customer'  => $order['customer_name'],
        'placed'    => date('M j, Y · g:i A', strtotime($order['created_at'])),
        'payment'   => $order['payment_method'] === 'COD' ? 'Cash on Delivery' : $order['payment_method'],
        'status'    => $order['status'],
        'driver_id' => $order['driver_id'] !== null ? (int) $order['driver_id'] : '',
        'address'   => $order['shipping_address'],
        'contact'   => $order['contact_number'] ?? '',
        'phone'     => $order['customer_phone'] ?? '',
        'notes'     => $order['order_notes'] ?? '',
        'items'     => $items,
        'subtotal'  => round($subtotal, 2),
        'shipping'  => max(0, round($total - $subtotal, 2)),
        'total'     => $total,
        'qty'       => $qty,
    ];
}

$returnParams = [
    'search'   => $search,
    'status'   => $statusFilter,
    'per_page' => $perPage !== 10 ? $perPage : '',
    'p'        => $currentPage > 1 ? $currentPage : '',
];

$errorMessages = [
    'invalid_order'   => 'That order could not be found.',
    'invalid_status'  => 'Invalid order status.',
    'invalid_address' => 'Shipping address is required (max 255 characters).',
    'invalid_contact' => 'Contact number format is not valid.',
    'invalid_driver'  => 'The selected driver is not valid.',
];

include __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
    <div>
        <h2>Order Management</h2>
        <p class="text-muted-sm">Every checkout from the store appears here. Review the order, edit the details, assign a driver and update the status.</p>
    </div>
</div>

<?php if (!empty($_GET['updated'])): ?>
    <div class="admin-card" style="border-left: 4px solid #16a34a; padding: 12px 16px; margin-bottom: 16px;">
        Order #ORD-<?php echo (int) $_GET['updated']; ?> was updated.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error']) && isset($errorMessages[$_GET['error']])): ?>
    <div class="admin-card" style="border-left: 4px solid #dc2626; padding: 12px 16px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($errorMessages[$_GET['error']]); ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <form action="orders.php" method="GET" class="card-header-flex mb-20">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; width: 100%;">
            <input
                type="text"
                name="search"
                class="table-search-lg"
                placeholder="Search Order ID, Customer or Driver..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <select name="status" class="table-filter-lg" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($allStatuses as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="per_page" class="table-filter-lg" onchange="this.form.submit()">
                <?php foreach ([10, 25, 50, 100] as $pp): ?>
                    <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?> per page</option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-primary">Filter</button>
            <?php if ($search !== '' || $statusFilter !== '' || $perPage !== 10): ?>
                <a href="orders.php" class="btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="admin-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date &amp; Time</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Assigned Driver</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <?php $d = $orderData[(int) $order['order_id']]; ?>
                    <tr class="order-row">
                        <td class="order-id">#ORD-<?php echo (int) $d['id']; ?></td>
                        <td class="customer-name"><?php echo htmlspecialchars($d['customer']); ?></td>
                        <td><?php echo htmlspecialchars($d['placed']); ?></td>
                        <td><?php echo (int) $d['qty']; ?></td>
                        <td>₱<?php echo number_format($d['total'], 2); ?></td>
                        <td><?php echo htmlspecialchars($d['payment']); ?></td>
                        <td class="driver-name-text"><?php echo htmlspecialchars($order['driver_name']); ?></td>
                        <td class="order-status">
                            <span class="badge badge-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', (string) $order['status'])), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button
                                type="button"
                                class="btn-action"
                                data-order-id="<?php echo (int) $d['id']; ?>"
                                onclick="smOpenOrder(this.dataset.orderId)"
                            >
                                Manage Order
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="table-empty-notice">No orders found matching criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1 || $totalOrders > 0): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px;">
            <span class="text-muted-sm">Showing <?php echo $totalOrders > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $perPage, $totalOrders); ?> of <?php echo $totalOrders; ?> orders</span>
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; gap: 5px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                            $queryParams = $_GET;
                            unset($queryParams['updated'], $queryParams['error']);
                            $queryParams['p'] = $i;
                            $pageUrl = 'orders.php?' . http_build_query($queryParams);
                        ?>
                        <a href="<?php echo htmlspecialchars($pageUrl); ?>" class="btn-secondary" style="<?php echo $i === $currentPage ? 'background-color: var(--primary-dark-red, #800000); color: #fff;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="statusModal">
    <div class="modal-content modal-md">
        <div class="modal-header">
            <h3>Manage Order <span id="smOrderTitle"></span></h3>
            <button class="modal-close" onclick="closeModal('statusModal')" type="button">&times;</button>
        </div>

        <form action="orders.php" method="POST">

            <?php echo securityField(); ?> 
                        
            <input type="hidden" id="modalOrderId" name="order_id" value="">
            <?php foreach ($returnParams as $rk => $rv): ?>
                <?php if ($rv !== '' && $rv !== null): ?>
                    <input type="hidden" name="return_<?php echo $rk; ?>" value="<?php echo htmlspecialchars((string) $rv); ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="form-group">
                <label>Customer</label>
                <input type="text" id="smCustomer" readonly class="form-input-readonly">
            </div>

            <div class="form-group">
                <label>Placed On / Payment</label>
                <input type="text" id="smPlaced" readonly class="form-input-readonly">
            </div>

            <div class="form-group">
                <label>Shipping Address (editable)</label>
                <textarea id="modalShippingAddress" name="shipping_address" maxlength="255" required class="modal-textarea-readonly" style="background:#fff; cursor:text;"></textarea>
            </div>

            <div class="form-group">
                <label>Contact Number (editable)</label>
                <input type="text" id="smContact" name="contact_number" maxlength="30" placeholder="e.g. 09123456789">
            </div>

            <div class="form-group" id="smNotesGroup" style="display:none;">
                <label>Customer Request / Notes</label>
                <textarea id="smNotes" readonly class="form-input-readonly modal-textarea-readonly"></textarea>
            </div>

            <div class="form-group">
                <label>Purchased Items</label>
                <div id="modalItemsContainer" class="modal-items-box"></div>
                <p class="text-muted-sm" id="smTotals" style="margin: 8px 0 0;"></p>
            </div>

            <div class="form-group">
                <label>Order Status</label>
                <select id="modalStatusSelect" name="new_status">
                    <?php foreach ($allStatuses as $st): ?>
                        <option value="<?php echo $st; ?>"><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Assign Delivery Driver</label>
                <select id="modalDriverSelect" name="driver_id">
                    <option value="">-- No Driver Assigned --</option>
                    <?php foreach ($driversList as $driver): ?>
                        <option value="<?php echo (int) $driver['user_id']; ?>">
                            <?php echo htmlspecialchars($driver['full_name']); ?><?php echo $driver['account_status'] !== 'Active' ? ' (' . htmlspecialchars($driver['account_status']) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer-bordered">
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="smOrdersData"><?php echo json_encode($orderData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script>
(function () {
    var ORDERS = {};
    try {
        ORDERS = JSON.parse(document.getElementById('smOrdersData').textContent || '{}');
    } catch (e) {
        ORDERS = {};
    }

    function money(n) {
        return '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Opens the "Manage Order" window with the real data of the clicked order.
    window.smOpenOrder = function (id) {
        var o = ORDERS[id];
        if (!o) return;

        document.getElementById('modalOrderId').value = o.id;
        document.getElementById('smOrderTitle').textContent = '#ORD-' + o.id;
        document.getElementById('smCustomer').value = o.customer + (o.phone ? ' · ' + o.phone : '');
        document.getElementById('smPlaced').value = o.placed + ' · ' + o.payment;
        document.getElementById('modalShippingAddress').value = o.address || '';
        document.getElementById('smContact').value = o.contact || '';
        document.getElementById('modalStatusSelect').value = o.status;
        document.getElementById('modalDriverSelect').value = o.driver_id === '' ? '' : String(o.driver_id);

        var notesGroup = document.getElementById('smNotesGroup');
        document.getElementById('smNotes').value = o.notes || '';
        notesGroup.style.display = o.notes ? '' : 'none';

        var box = document.getElementById('modalItemsContainer');
        box.innerHTML = '';
        if (!o.items || o.items.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'text-muted-sm';
            empty.style.margin = '0';
            empty.textContent = 'No item details available.';
            box.appendChild(empty);
        } else {
            var ul = document.createElement('ul');
            ul.className = 'modal-item-list';
            o.items.forEach(function (it) {
                var li = document.createElement('li');
                var strong = document.createElement('strong');
                strong.textContent = it.name;
                li.appendChild(strong);
                li.appendChild(document.createTextNode(' × ' + it.quantity + ' '));
                var small = document.createElement('span');
                small.className = 'text-muted-sm';
                small.textContent = '(' + money(it.price) + ' each)';
                li.appendChild(small);
                ul.appendChild(li);
            });
            box.appendChild(ul);
        }

        document.getElementById('smTotals').textContent =
            'Subtotal ' + money(o.subtotal) + ' · Shipping ' + (o.shipping > 0 ? money(o.shipping) : 'FREE') + ' · Total ' + money(o.total);

        openModal('statusModal');
    };
})();
</script>

<?php echo '</main></div></div></body></html>'; ?>