<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
$page = $_GET['page'] ?? 'home';
$isLoggedIn = isLoggedIn();
$userName = $_SESSION['user_name'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$username = $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$allowedPages = [ 'home', 'products', 'orders', 'cart' ];
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0" >
        <link rel="stylesheet" href="assets/css/styles.css" >
    <title>SportMart</title>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php
    if ($page === 'home') {
        include 'pages/home.php';
    } elseif ($page === 'products') {
        include 'pages/products.php';
    } elseif ($page === 'orders') {
        include 'pages/orders.php';
    } elseif ($page === 'cart') {
        include 'pages/cart.php';
    } ?>
    <?php if (!$isLoggedIn): ?>
        <div class="popup" id="loginPopup">
            <div class ="popup-box login-box">
                <button class="close" onclick="closePopup('loginPopup')">x</button>
                <h2>Login</h2>
                <p>Enter your Sportmart Account.</p>
                <form action="pages/login.php" method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <?php if(isset($_GET['login'])): ?>
                        <?php if($_GET['login'] === 'error'): ?>
                            <p class="login-message">Incorrect username or password.</p>
                    <?php elseif ($_GET['login'] === 'locked'): ?>
                            <p class="login-message">Account locked after 3 failed login attempts.</p>
                    <?php elseif ($_GET['login'] === 'required'): ?>
                            <p class="login-message">Please login to continue.</p>
                    <?php endif; ?>
                    <?php endif; ?>
                    <button type="submit" class="popup-button">Login</button>
                </form>
                <p class="popup-disclaimer"> Don't have an account? <a href="index.php?signup=open"> Sign up here</a></p>
            </div>
        </div>

        <div class="popup" id="signupPopup"> 
            <div class="popup-box signup-box">
                <button class="close" onclick="closePopup('signupPopup')">x</button>
                <h2>Sign Up</h2>
                <p>Create your SportMart Account.</p>
                <?php $signupStatus = $_GET['signup'] ?? '';
                $signupErrors = ['empty' => 'Please fill in all fields.', 'name' => 'Full name cannot contain numbers.', 'username' => 'Username can only contain letters and numbers, and cannot be numbers only.', 'exists' => 'Username already exists.', 'email' => 'Please use valid email', 'mismatch' => 'Passwords do not match. Please re-enter your password.', 'password' => 'Password does not meet the requirments.', 'error' => 'Unable to create account.', 'captcha' => 'Please complete the CAPTCHA.'];?>
                <form action="pages/register.php" method="POST" id="signupForm" novalidate>
                    <input type="text" name="fullname" id="SignupFullname" placeholder="Full Name" required >
                    <p class="signup-error" id="fullnameError">
                        <?php echo $signupStatus === 'name' ? $signupErrors['name'] : ''; ?> </p>
                <input type="text" name="username" id="signupUsername" placeholder="Username" required >
                <p class="signup-error" id="usernameError">
                    <?php echo in_array( $signupStatus, ['username', 'exists'] ) ? $signupErrors[$signupStatus] : ''; ?>
                </p>
                <input type="text" name="email" id="signupEmail" placeholder="Email Address" required >
                <p class="signup-error" id="emailError"> <?php echo $signupStatus === 'email' ? $signupErrors['email'] : ''; ?> </p>
                <input type="password" name="password" id="signupPassword" placeholder="Password" required >
                <ul class="password-rules" id="passwordRules">
                    <li id="ruleLength"> At least twelve (12) characters in length </li>
                    <li id="ruleUpper"> At least one (1) CAPITAL letter </li>
                    <li id="ruleLower"> At least one (1) small letter </li>
                    <li id="ruleNumber"> At least one (1) number </li>
                    <li id="ruleSpecial"> At least one (1) special character </li>
                </ul>
                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required >
                <p class="signup-error" id="confirmMessage"> <?php echo $signupStatus === 'mismatch' ? $signupErrors['mismatch'] : ''; ?> </p>
                <p class="login-message" id="signupMessage"> <?php echo in_array( $signupStatus, ['empty', 'password', 'error', 'captcha'] ) ? $signupErrors[$signupStatus] : ''; ?> </p>
                <div class="g-recaptcha" data-sitekey="6LfM9fItAAAAANmJl31ryWpfPiOzSXCW4wj8Aa0j"></div>
                <button type="submit" class="popup-button"> Create Account </button>
            </form>
            <p class="popup-disclaimer"> Already have an account? <a href="index.php?login=required"> Login here </a>. </p>
        </div>
    </div>

    <?php endif; ?>
    
    <?php include 'includes/footer.php'; ?>
<script src="assets/js/main.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</body>
</html>