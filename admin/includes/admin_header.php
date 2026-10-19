<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../includes/db.php';

$timeoutMinutes = 2;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout_minutes' LIMIT 1");
    $stmt->execute();
    $settingVal = $stmt->fetchColumn();
    if ($settingVal !== false && (int)$settingVal > 0) {
        $timeoutMinutes = (int)$settingVal;
    }
} catch (PDOException $e) {
}

if (!empty($_SESSION['logged_in'])) {
    $timeoutSeconds = $timeoutMinutes * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?login=timeout');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

$userName = $_SESSION['user_name'] ?? 'Developer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportMart - Back-Office Portal</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script>
        const SESSION_TIMEOUT_MINUTES = <?php echo (int)$timeoutMinutes; ?>;
    </script>
    <script src="../assets/js/admin.js" defer></script>
</head>
<body>
<div class="admin-container">
    <?php include __DIR__ . '/admin_sidebar.php'; ?>

    <div class="admin-main">
        <header class="admin-header">
            <div class="header-title">
                <h3>Back-Office Administration Portal</h3>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
            </div>
        </header>

        <main class="content-wrapper">