<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user_role'] ?? 'Developer';
?>

<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <h2>SportMart</h2>
        <span><?php echo strtoupper(htmlspecialchars($userRole)); ?></span>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="index.php" class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
                Dashboard
            </a>
        </li>
        <li>
            <a href="products.php" class="<?php echo ($currentPage == 'products.php') ? 'active' : ''; ?>">
                Products
            </a>
        </li>
        <li>
            <a href="orders.php" class="<?php echo ($currentPage == 'orders.php') ? 'active' : ''; ?>">
                Orders
            </a>
        </li>

        <?php if (in_array($userRole, ['Admin', 'Developer'])): ?>
            <li class="sidebar-divider"></li>
            
            <li>
                <a href="categories.php" class="<?php echo ($currentPage == 'categories.php') ? 'active' : ''; ?>">
                    Category Management
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                    User Management
                </a>
            </li>
            <li>
                <a href="security-settings.php" class="<?php echo ($currentPage == 'security-settings.php') ? 'active' : ''; ?>">
                    Security & Policies
                </a>
            </li>
            <li>
                <a href="audit-logs.php" class="<?php echo ($currentPage == 'audit-logs.php') ? 'active' : ''; ?>">
                    Audit Logs
                </a>
            </li>
        <?php endif; ?>

        <li class="sidebar-divider"></li>

        <li>
            <a href="settings.php" class="<?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>">
                Account Settings
            </a>
        </li>

        <li>
            <a href="../pages/logout.php">
                Logout
            </a>
        </li>
    </ul>
</aside>