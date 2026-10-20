<?php
if (!function_exists('logAudit')) {
    function logAudit($pdo, $userId, $actionDescription) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $actionDescription, $ipAddress]);
    }
}