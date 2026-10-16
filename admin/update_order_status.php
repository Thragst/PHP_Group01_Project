<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tracking_config.php';

if (!canUpdateOrders()) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request.');
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid security token.');
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$newStatus = trim($_POST['status'] ?? '');
$city = trim($_POST['current_location'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$staffId = (int) $_SESSION['user_id'];

if (!$orderId || !in_array($newStatus, $statuses, true)) {
    exit('Invalid order or status.');
}

if ($city !== '' && !in_array($city, $ncrCities, true)) {
    exit('Invalid NCR city.');
}

if (
    in_array($newStatus, ['In Transit', 'Out for Delivery'], true)
    && $city === ''
) {
    exit('Please select an NCR city.');
}

if (mb_strlen($remarks) > 255) {
    exit('Remarks are too long.');
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "SELECT status FROM orders WHERE order_id = ? FOR UPDATE"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new RuntimeException('Order not found.');
    }

    $oldStatus = $order['status'];

    if (!in_array(
        $newStatus,
        $allowedTransitions[$oldStatus] ?? [],
        true
    )) {
        throw new RuntimeException('Invalid status transition.');
    }

    $stmt = $conn->prepare(
        "UPDATE orders SET status = ? WHERE order_id = ?"
    );
    $stmt->bind_param('si', $newStatus, $orderId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO sportmart_tracking_events
        (order_id, status_from, status_to, current_location, remarks, updated_by_id)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        'issssi',
        $orderId,
        $oldStatus,
        $newStatus,
        $city,
        $remarks,
        $staffId
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    header('Location: orders.php?updated=1');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update order. Check the server logs.');
}
