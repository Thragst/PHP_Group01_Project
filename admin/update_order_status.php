<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit_helper.php';

if (empty($_SESSION['logged_in']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request.');
}

securityCheck();

$statuses = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled'];

$orderId   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$newStatus = trim((string)($_POST['status'] ?? ''));
$city      = trim((string)($_POST['current_location'] ?? ''));
$remarks   = trim((string)($_POST['remarks'] ?? ''));
$staffId   = (int)($_SESSION['user_id'] ?? 0);

if (!$orderId || $orderId <= 0 || !in_array($newStatus, $statuses, true)) {
    exit('Invalid order or status.');
}
if (mb_strlen($city) > 100) {
    exit('Invalid location.');
}
if (in_array($newStatus, ['Shipped', 'Out for Delivery'], true) && $city === '') {
    exit('Please enter the current location.');
}
if (mb_strlen($remarks) > 255) {
    exit('Remarks are too long.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException('Order not found.');
    }
    $oldStatus = $order['status'];

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$newStatus, $orderId]);

    $note = $remarks !== '' ? $remarks : "Status changed from {$oldStatus} to {$newStatus}";
    $stmt = $pdo->prepare(
        "INSERT INTO order_tracking (order_id, location_city, status_note, updated_by) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$orderId, $city !== '' ? $city : 'N/A', $note, $staffId]);

    $pdo->commit();

    logAudit($pdo, $staffId, "Updated order #ORD-{$orderId} status '{$oldStatus}' -> '{$newStatus}'");

    header('Location: orders.php?updated=' . $orderId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update order. Check the server logs.');
}