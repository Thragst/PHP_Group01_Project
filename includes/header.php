
<?php
if(session_status()===PHP_SESSION_NONE){ session_start(); }
$basePath = $basePath ?? '';
$currentPage=isset($_GET['page'])?$_GET['page']:'home';
$isLoggedIn = isLoggedIn();
$userName=$_SESSION['user_name']??'';
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
            <button class="profile-button" onclick="showProfileMenu()">
                <?php echo htmlspecialchars($userName); ?>
            </button>
            <a href="pages/logout.php" class="logout-link">Logout</a>
                <?php else: ?>
            <a href="index.php?login=required" class="login-link">Login</a>
            <a href="index.php?signup=open" class="signup-link">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>