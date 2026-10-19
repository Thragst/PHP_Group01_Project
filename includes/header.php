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
    <a href="index.php" class="logo">
        <span>S</span> SportMart
    </a>

    <div class="nav-links">
        <a href="index.php" class="<?php echo $currentPage=='home'?'active':''; ?>">Home</a>
        <a href="index.php?page=products" class="<?php echo $currentPage=='products'?'active':''; ?>">Products</a>
        <a href="index.php?page=orders" class="<?php echo $currentPage=='orders'?'active':''; ?>">My Orders</a>
        <a href="index.php?page=cart" class="<?php echo $currentPage === 'cart' ? 'active' : ''; ?>">Cart</a>
    </div>

    <div class="nav-right">
        <?php if($isLoggedIn): ?>
            <button type="button" class="profile-button" onclick="showProfileMenu()" aria-haspopup="true" aria-controls="profileSidebar">
                <?php echo htmlspecialchars($userName); ?>
            </button>
        <?php else: ?>
            <a href="index.php?login=required" class="login-link">Login</a>
            <a href="index.php?signup=open" class="signup-link">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<?php if($isLoggedIn): ?>
    <div class="profile-overlay" id="profileOverlay" onclick="closeProfileMenu()"></div>

    <aside class="profile-sidebar" id="profileSidebar" aria-label="Account menu">
        <div class="profile-sidebar-header">
            <h2>My Account</h2>
            <button type="button" class="profile-close" onclick="closeProfileMenu()" aria-label="Close menu">&times;</button>
        </div>

        <div class="profile-user">
            <div class="profile-avatar"><?php echo htmlspecialchars($avatarLetter); ?></div>
            <div>
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p>@<?php echo htmlspecialchars($sidebarUsername); ?></p>
            </div>
        </div>

        <nav class="profile-menu">
            <a href="index.php?page=profile" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">Profile</a>
            <a href="index.php?page=settings" class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">Settings</a>
            <a href="pages/logout.php" class="logout-link">Logout</a>
        </nav>
    </aside>
<?php endif; ?>