<?php
session_start();
require_once('db.php');

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

$userName = $_SESSION['user_name'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$username = $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

function productImageSrc($path) {
    $path = trim($path ?? '');

    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    $clean = ltrim($path, '/');

    if (str_starts_with(strtolower($clean), 'admin/')) {
        $clean = substr($clean, 6);
    }

    return $clean;
}

$dbProducts = [];
$popularProducts = [];

if ($page === 'products') {
    $stmt = $pdo->query("
        SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.name AS category
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.status = 'Active' AND c.status = 'Active'
        ORDER BY p.product_id ASC
    ");
    $dbProducts = $stmt->fetchAll();
} elseif ($page !== 'orders') {
    $stmt = $pdo->query("
        SELECT p.product_id, p.name, p.brand, p.price, p.image_path, c.name AS category
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.status = 'Active' AND c.status = 'Active'
        ORDER BY p.product_id ASC
        LIMIT 4
    ");
    $popularProducts = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css">
    <title>SportMart</title>
</head>
<body>

<?php include 'includes/header.php'; ?>

<?php if ($page == 'products'): ?>

    <section class="products-page">
        <div class="products-heading">
            <p class="small-title">SPORTMART SHOP</p>
            <h1>Sports Products</h1>
            <p>Find the sports equipment you need for your game.</p>
        </div>

        <div class="filter-area">
            <div class="filter-group">
                <button class="filter-button active" data-category="All">All</button>
                <button class="filter-button" data-category="Basketball">Basketball</button>
                <button class="filter-button" data-category="Football">Football</button>
                <button class="filter-button" data-category="Running">Running</button>
                <button class="filter-button" data-category="Tennis">Tennis</button>
            </div>

            <div class="sort-label">
                Sort:
                <select id="sortProducts">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="lowest">Lowest Price</option>
                    <option value="highest">Highest Price</option>
                </select>
            </div>
        </div>

        <div class="product-list products-grid" id="productList">

            <?php foreach ($dbProducts as $index => $product): ?>
                <div class="product"
                     data-id="<?php echo (int)$product['product_id']; ?>"
                     data-category="<?php echo htmlspecialchars($product['category']); ?>"
                     data-price="<?php echo htmlspecialchars($product['price']); ?>"
                     data-order="<?php echo $index + 1; ?>">
                    <div class="product-image">
                        <?php $imgSrc = productImageSrc($product['image_path']); ?>
                        <?php if ($imgSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 loading="lazy"
                                 style="width:100%;height:100%;object-fit:contain;display:block;"
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                        <button class="heart">♡</button>
                    </div>
                    <div class="product-info">
                        <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                        <h3 title="<?php echo htmlspecialchars($product['name']); ?>"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="brand"><?php echo htmlspecialchars($product['brand'] ?? ''); ?></p>
                        <div class="stars">★★★★★</div>
                        <div class="product-bottom">
                            <strong>₱<?php echo number_format($product['price'], 2); ?></strong>
                            <div class="product-buttons">
                                <button class="add-cart-button">Add to Cart</button>
                                <button class="buy-button">Buy</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($dbProducts)): ?>
                <p>No products available right now.</p>
            <?php endif; ?>

        </div>
    </section>

<?php elseif ($page == 'orders'): ?>

    <?php
    $orders = $_SESSION['orders'] ?? [];

    $statusClasses = [
        'Processing' => 'processing',
        'Shipped' => 'shipped',
        'Out for Delivery' => 'delivery',
        'Delivered' => 'delivered'
    ];
    ?>

    <section class="orders-page">
        <div class="orders-header">
            <div>
                <p class="small-title">SPORTMART ACCOUNT</p>
                <h1>My Orders</h1>
                <p>View and track your recent orders.</p>
            </div>

            <select class="order-filter" id="orderFilter">
                <option value="All">FILTER</option>
                <option value="Processing">Processing</option>
                <option value="Shipped">Shipped</option>
                <option value="Out for Delivery">Out for Delivery</option>
                <option value="Delivered">Delivered</option>
            </select>
        </div>

        <?php if (isset($_GET['placed']) && $isLoggedIn && !empty($orders)): ?>
            <div class="order-notice">
                ✓ Your order has been placed! You can track its status below.
            </div>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>

            <div class="orders-empty">
                <div class="orders-empty-icon">S</div>
                <h2>Login to see your orders</h2>
                <p>Please login to view and track your orders.</p>
                <a href="#" class="checkout-button" onclick="showLogin();return false;">Login</a>
            </div>

        <?php elseif (empty($orders)): ?>

            <div class="orders-empty">
                <div class="orders-empty-icon">S</div>
                <h2>No orders yet</h2>
                <p>Once you place an order, it will show up here.</p>
                <a href="index.php?page=products" class="checkout-button">Browse Products</a>
            </div>

        <?php else: ?>

            <div class="orders-scroll">
                <div class="orders-list">

                    <?php foreach ($orders as $order): ?>
                        <?php
                        $statusClass = $statusClasses[$order['status']] ?? 'processing';
                        $itemCount = array_sum(array_column($order['items'], 'quantity'));
                        ?>

                        <div class="order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>">

                            <div class="order-card-head">
                                <div>
                                    <span class="order-code"><?php echo htmlspecialchars($order['number']); ?></span>
                                    <span class="order-date">
                                        <?php echo htmlspecialchars($order['date']); ?>
                                        · <?php echo (int)$itemCount; ?> item(s)
                                    </span>
                                </div>

                                <span class="order-status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>

                            <div class="order-items">
                                <?php foreach ($order['items'] as $orderItem): ?>
                                    <div class="order-line">
                                        <div class="order-line-image">
                                            <span><?php echo htmlspecialchars(strtoupper(substr($orderItem['name'], 0, 1))); ?></span>
                                        </div>

                                        <div class="order-line-info">
                                            <h3><?php echo htmlspecialchars($orderItem['name']); ?></h3>
                                            <p>
                                                Qty: <?php echo (int)$orderItem['quantity']; ?>
                                                × ₱<?php echo number_format($orderItem['price'], 2); ?>
                                            </p>
                                        </div>

                                        <div class="order-line-total">
                                            ₱<?php echo number_format($orderItem['price'] * $orderItem['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="order-card-foot">
                                <div class="order-delivery">
                                    <span class="order-delivery-label">Deliver to</span>
                                    <p><?php echo htmlspecialchars($order['address']); ?></p>
                                    <p><?php echo htmlspecialchars($order['mobile']); ?></p>

                                    <?php if ($order['request'] !== ''): ?>
                                        <p class="order-request">
                                            Request: <?php echo htmlspecialchars($order['request']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="order-totals">
                                    <span class="order-shipping">
                                        Shipping:
                                        <?php echo $order['shipping'] == 0 ? 'FREE' : '₱' . number_format($order['shipping'], 2); ?>
                                    </span>
                                    <span class="order-total-label">Total</span>
                                    <strong class="order-total-price">
                                        ₱<?php echo number_format($order['total'], 2); ?>
                                    </strong>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

        <?php endif; ?>
    </section>

<?php else: ?>

    <section class="hero">
        <div class="hero-text">
            <p class="small-title">SPORTS EQUIPMENT & GEAR</p>
            <h1>
                Gear up.<br>
                <span>Game on.</span>
            </h1>
            <p class="hero-description">
                Find the sports equipment you need
                for training, exercise, and your
                favorite sports.
            </p>

            <div class="hero-buttons">
                <a href="index.php?page=products" class="main-button">Shop Now</a>
            </div>
        </div>

        <div class="hero-picture">
            <div class="circle">
                SPORT<br>
                <b>MART</b>
            </div>
        </div>
    </section>

    <section class="benefits">
        <div class="benefit-box">
            <div class="benefit-icon">🚚</div>
            <div>
                <b>Fast Delivery</b>
                <p>Quick and reliable delivery.</p>
            </div>
        </div>

        <div class="benefit-box">
            <div class="benefit-icon">✓</div>
            <div>
                <b>100% Authentic</b>
                <p>Original sports products.</p>
            </div>
        </div>

        <div class="benefit-box">
            <div class="benefit-icon">↩</div>
            <div>
                <b>Easy Returns</b>
                <p>Simple and hassle-free returns.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <div>
                <p class="small-title">OUR COLLECTION</p>
                <h2>Popular Products</h2>
            </div>

            <a href="index.php?page=products" class="view-all">View All Products →</a>
        </div>

        <div class="product-list">

            <?php foreach ($popularProducts as $product): ?>
                <div class="product">
                    <div class="product-image">
                        <?php $imgSrc = productImageSrc($product['image_path']); ?>
                        <?php if ($imgSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 loading="lazy"
                                 style="width:100%;height:100%;object-fit:contain;display:block;"
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                        <button class="heart">♡</button>
                    </div>
                    <div class="product-info">
                        <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                        <h3 title="<?php echo htmlspecialchars($product['name']); ?>"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="brand"><?php echo htmlspecialchars($product['brand'] ?? ''); ?></p>
                        <div class="stars">★★★★★</div>
                        <strong>₱<?php echo number_format($product['price'], 2); ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </section>

    <section class="orders" id="orders">
        <div class="orders-content">
            <p class="small-title">SPORTMART ACCOUNT</p>
            <h2>Track Your Orders</h2>
            <p>
                Check your previous orders and
                keep track of your purchases.
            </p>
            <a href="index.php?page=orders">View My Orders</a>
        </div>
    </section>

<?php endif; ?>

<div class="popup" id="cartPopup">
    <div class="popup-box">
        <button class="close" onclick="closePopup('cartPopup')">×</button>
        <h2>Shopping Cart</h2>
        <p id="cartMessage">Your cart is currently empty.</p>
        <button class="popup-button" onclick="closePopup('cartPopup')">Continue Shopping</button>
    </div>
</div>

<?php if (!$isLoggedIn): ?>

    <div class="popup" id="loginPopup">
        <div class="popup-box login-box">
            <button class="close" onclick="closePopup('loginPopup')">×</button>

            <h2>Login</h2>
            <p>Enter your SportMart account.</p>

            <form action="login.php" method="POST">
                <input type="text" name="username" placeholder="Username" required>

                <input type="password" name="password" placeholder="Password" required>

                <?php if (isset($_GET['login'])): ?>
                    <?php if ($_GET['login'] === 'error'): ?>
                        <p class="login-message">Incorrect username or password.</p>
                    <?php elseif ($_GET['login'] === 'expired'): ?>
                        <p class="login-message">Your password has expired.</p>
                    <?php elseif ($_GET['login'] === 'required'): ?>
                        <p class="login-message">Please login to continue.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <button type="submit" class="popup-button">Login</button>
            </form>

            <p class="popup-disclaimer">
                Disclaimer: An account is required to place and track orders.
                Don't have an account?
                <a href="#" onclick="closePopup('loginPopup'); showSignup(); return false;">Sign up here</a>.
            </p>
        </div>
    </div>

    <div class="popup" id="signupPopup">
        <div class="popup-box signup-box">
            <button class="close" onclick="closePopup('signupPopup')">×</button>

            <h2>Sign Up</h2>
            <p>Create your SportMart account.</p>

            <?php
            $signupStatus = $_GET['signup'] ?? '';
            $signupErrors = [
                'empty' => 'Please fill in all fields.',
                'name' => 'Full name cannot contain numbers.',
                'username' => 'Username can only contain letters and numbers, and cannot be numbers only.',
                'exists' => 'Username already exists.',
                'email' => 'Please use valid email',
                'mismatch' => 'Passwords do not match. Please re-enter your password.',
                'password' => 'Password does not meet the requirements.',
                'error' => 'Unable to create account.'
            ];
            ?>

            <form action="register.php" method="POST" id="signupForm" novalidate>
                <input type="text" name="fullname" id="signupFullname" placeholder="Full Name" required>
                <p class="signup-error" id="fullnameError"><?php echo $signupStatus === 'name' ? $signupErrors['name'] : ''; ?></p>

                <input type="text" name="username" id="signupUsername" placeholder="Username" required>
                <p class="signup-error" id="usernameError"><?php echo in_array($signupStatus, ['username', 'exists']) ? $signupErrors[$signupStatus] : ''; ?></p>

                <input type="text" name="email" id="signupEmail" placeholder="Email Address" required>
                <p class="signup-error" id="emailError"><?php echo $signupStatus === 'email' ? $signupErrors['email'] : ''; ?></p>

                <input type="password" name="password" id="signupPassword" placeholder="Password" required>
                <ul class="password-rules" id="passwordRules">
                    <li id="ruleLength">At least twelve (12) characters in length</li>
                    <li id="ruleUpper">At least one (1) CAPITAL letter</li>
                    <li id="ruleLower">At least one (1) small letter</li>
                    <li id="ruleNumber">At least one (1) number</li>
                    <li id="ruleSpecial">At least one (1) special character</li>
                </ul>

                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
                <p class="signup-error" id="confirmMessage"><?php echo $signupStatus === 'mismatch' ? $signupErrors['mismatch'] : ''; ?></p>

                <p class="login-message" id="signupMessage"><?php echo in_array($signupStatus, ['empty', 'password', 'error']) ? $signupErrors[$signupStatus] : ''; ?></p>

                <button type="submit" class="popup-button">Create Account</button>
            </form>

            <p class="popup-disclaimer">
                Already have an account?
                <a href="#" onclick="closePopup('signupPopup'); showLogin(); return false;">Login here</a>.
            </p>
        </div>
    </div>

<?php endif; ?>

<?php if ($isLoggedIn): ?>

    <div class="profile-sidebar" id="profileSidebar">
        <div class="profile-sidebar-header">
            <h2>Account</h2>
            <button class="profile-close" onclick="closeProfileMenu()">×</button>
        </div>

        <div class="profile-user">
            <div class="profile-avatar">
                <?php echo htmlspecialchars(strtoupper(substr($userName, 0, 1))); ?>
            </div>

            <div>
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p><?php echo htmlspecialchars($userRole); ?></p>
            </div>
        </div>

        <div class="profile-info">
            <div>
                <span>Username</span>
                <strong><?php echo htmlspecialchars($username); ?></strong>
            </div>

            <div>
                <span>User ID</span>
                <strong><?php echo htmlspecialchars($userId); ?></strong>
            </div>
        </div>

        <div class="profile-menu">
            <button onclick="showProfilePage()">Profile</button>
            <button onclick="showSettingsPage()">Settings</button>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="profile-overlay" id="profileOverlay" onclick="closeProfileMenu()"></div>

    <div class="account-page-popup" id="profilePage">
        <div class="account-page-box">
            <button class="close" onclick="closeAccountPage('profilePage')">×</button>
        </div>
    </div>

    <div class="account-page-popup" id="settingsPage">
        <div class="account-page-box">
            <button class="close" onclick="closeAccountPage('settingsPage')">×</button>
        </div>
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/main.js"></script>
</body>
</html>