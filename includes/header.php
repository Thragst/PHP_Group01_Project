<?php
if(session_status()===PHP_SESSION_NONE){ session_start(); }
$basePath = $basePath ?? '';
$currentPage=isset($_GET['page'])?$_GET['page']:'home';
$isLoggedIn = isLoggedIn();
$userName=$_SESSION['user_name']??'';
$sidebarUsername = $_SESSION['username'] ?? '';
$avatarLetter = strtoupper(mb_substr(trim($userName), 0, 1)) ?: 'U';
?>

<nav class="navbar">
    <a href="<?php echo $basePath; ?>index.php" class="logo">
        <span>S</span> SportMart
    </a>

    <div class="nav-links">
        <a href="<?php echo $basePath; ?>index.php" class="<?php echo $currentPage=='home'?'active':''; ?>">Home</a>
        <a href="<?php echo $basePath; ?>index.php?page=products" class="<?php echo $currentPage=='products'?'active':''; ?>">Products</a>
        <a href="<?php echo $basePath; ?>index.php?page=orders" class="<?php echo $currentPage=='orders'?'active':''; ?>">My Orders</a>
        <a href="<?php echo $basePath; ?>index.php?page=cart" class="<?php echo $currentPage === 'cart' ? 'active' : ''; ?>">Cart</a>
    </div>

    <div class="nav-right">
        <?php if($isLoggedIn): ?>
            <div class="account-menu">
                <button type="button" class="account-trigger" onclick="toggleAccountMenu()" aria-haspopup="true" aria-expanded="false" aria-controls="accountDropdown">
                    <span class="account-avatar"><?php echo htmlspecialchars($avatarLetter); ?></span>
                    <span class="account-name"><?php echo htmlspecialchars($userName); ?></span>
                    <span class="account-arrow">⌄</span>
                </button>
                <div class="account-dropdown" id="accountDropdown">
                    <a href="<?php echo $basePath; ?>index.php?page=profile" class="account-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                        <span class="account-icon">👤</span>
                        <span>Profile</span>
                    </a>
                    <a href="<?php echo $basePath; ?>index.php?page=settings" class="account-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <span class="account-icon">⚙</span>
                        <span>Settings</span>
                    </a>
                    <a href="<?php echo $basePath; ?>index.php?page=orders" class="account-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                        <span class="account-icon">📋</span>
                        <span>My Orders</span>
                    </a>
                    <a href="<?php echo $basePath; ?>index.php?page=cart" class="account-item <?php echo $currentPage === 'cart' ? 'active' : ''; ?>">
                        <span class="account-icon">🛒</span>
                        <span>Cart</span>
                    </a>
                    <div class="account-divider"></div>
                    <a href="<?php echo $basePath; ?>pages/logout.php" class="account-item account-logout">
                        <span class="account-icon">↪</span> <span>Logout</span>
                    </a>
                </div>
            </div>
            <?php else: ?>
                <a href="<?php echo $basePath; ?>index.php?login=required" class="login-link">Login</a>
                <a href="<?php echo $basePath; ?>index.php?signup=required" class="signup-link">Signup</a>
        <?php endif; ?>
    </div>
</nav>
