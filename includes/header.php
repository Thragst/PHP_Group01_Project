<?php
if(session_status()===PHP_SESSION_NONE){
    session_start();
}

$currentPage=isset($_GET['page'])?$_GET['page']:'home';
$isLoggedIn=isset($_SESSION['logged_in'])&&
           $_SESSION['logged_in']===true;
$userName=$_SESSION['user_name']??'';
?>

<nav class="navbar">
    <div class="logo">
        <span>S</span>
        SportMart
    </div>

    <div class="nav-links">
        <a href="index.php"
           class="<?php echo $currentPage=='home'?'active':''; ?>">
            Home
        </a>

        <a href="index.php?page=products"
           class="<?php echo $currentPage=='products'?'active':''; ?>">
            Products
        </a>

        <a href="index.php?page=orders"
           class="<?php echo $currentPage=='orders'?'active':''; ?>">
            My Orders
        </a>

        <a href="#"
           class="cart-link"
           onclick="showCart();return false;">
            Cart
        </a>
    </div>

    <div class="nav-right">
        <?php if($isLoggedIn): ?>
            <button class="profile-button"
                    onclick="showProfileMenu()">
                <?php echo htmlspecialchars($userName); ?>
            </button>
        <?php else: ?>
            <a href="#"
               class="login-link"
               onclick="showLogin();return false;">
                Login
            </a>

            <a href="#"
               class="signup-link"
               onclick="showSignup();return false;">
                Sign Up
            </a>
        <?php endif; ?>
    </div>
</nav>