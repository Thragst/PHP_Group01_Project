<?php
if (!function_exists('logAudit')) {
    function logAudit($pdo, $userId, $actionDescription) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userId = !empty($userId) ? (int)$userId : null;

            $actionDescription = mb_substr((string)$actionDescription, 0, 500);

            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_description, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $actionDescription, $ipAddress]);
        } catch (PDOException $e) {
            error_log('logAudit failed: ' . $e->getMessage());
        }
    }
}