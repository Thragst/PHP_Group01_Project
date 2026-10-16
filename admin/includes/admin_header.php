<?php
$userName = $_SESSION['user_name'] ?? 'Developer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportMart - Back-Office Portal</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
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