<?php
require_once __DIR__ . '/includes/ssl.php';
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/recaptcha.php';
require_once __DIR__ . '/includes/auth_guard.php';
enforceIdleTimeout($pdo);

noCacheHeaders();
if (!empty($_SESSION['logged_in']) && in_array($_SESSION['user_role'] ?? '', ['Admin', 'Staff', 'Developer', 'Driver'], true)) {
    header('Location: ' . roleHome($_SESSION['user_role']));
    exit();
}

$defaultPolicy = [
    'max_failed_attempts' => '3',
    'min_password_length' => '12',
    'require_uppercase'   => '1',
    'require_lowercase'   => '1',
    'require_number'      => '1',
    'require_special'     => '1'
];
try {
    $rawSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $activePolicy = array_merge($defaultPolicy, $rawSettings ?: []);
} catch (Exception $e) {
    $activePolicy = $defaultPolicy;
}

$page = $_GET['page'] ?? 'home';
$isLoggedIn = isLoggedIn();
$userName = $_SESSION['user_name'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$username = $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$allowedPages = [ 'home', 'products','productDetails', 'orders', 'cart', 'profile', 'settings'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

if (in_array($page, ['profile', 'settings'], true) && !$isLoggedIn) {
    header('Location: index.php?login=required');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0" >
        <meta name="csrf-token" content="<?php echo e(getCsrfToken()); ?>">
        <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo filemtime(__DIR__ . '/assets/css/styles.css'); ?>" >
        <link rel="stylesheet" href="assets/css/account.css?v=<?php echo filemtime(__DIR__ . '/assets/css/account.css'); ?>" >
        <link rel="stylesheet" href="assets/css/activation.css?v=<?php echo filemtime(__DIR__ . '/assets/css/activation.css'); ?>" >
        <?php if ($page === 'products'): ?> <link rel="stylesheet" href="assets/css/products.css?v=<?php echo filemtime(__DIR__ . '/assets/css/products.css'); ?>"> <?php endif; ?>
        <?php if ($page === 'productDetails'): ?> <link rel="stylesheet" href="assets/css/productDetails.css?v=<?php echo filemtime(__DIR__ . '/assets/css/productDetails.css'); ?>"> <?php endif; ?>
    <title>SportMart</title>
    <script>window.addEventListener('pageshow',function(e){if(e.persisted){location.reload();}});</script>
</head>

<body>
<?php include 'includes/header.php'; ?>
<?php
    if ($page === 'home') {
        include 'pages/home.php';
    } elseif ($page === 'products') {
        include 'pages/products.php';
    } elseif ($page === 'productDetails') {
        include 'pages/productDetails.php';   
    } elseif ($page === 'orders') {
        include 'pages/orders.php';
    } elseif ($page === 'cart') {
        include 'pages/cart.php';
    } elseif ($page === 'profile') {
        include 'pages/profile.php';
    } elseif ($page === 'settings') {
        include 'pages/settings.php';
    } ?>
    <?php if (!$isLoggedIn): ?>
        <div class="popup" id="loginPopup">
            <div class ="popup-box login-box">
                <button class="close" onclick="closePopup('loginPopup')">x</button>
                <h2>Login</h2>
                <p>Enter your Sportmart Account.</p>
                <form action="pages/login.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="text" name="username" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <?php if(isset($_GET['login'])): ?>
                        <?php if($_GET['login'] === 'error'): ?>
                            <p class="login-message">Incorrect username or password.</p>
                    <?php elseif ($_GET['login'] === 'locked'): ?>
                            <p class="login-message">Account locked due to multiple failed login attempts. Please contact support.</p>
                    <?php elseif ($_GET['login'] === 'timeout'): ?>
                            <p class="login-message">You have been logged out due to inactivity.</p>
                    <?php elseif ($_GET['login'] === 'activated'): ?>
                            <p class="login-message login-success">Account activated. You can now log in.</p>
                    <?php elseif ($_GET['login'] === 'invalidlink'): ?>
                            <p class="login-message">This activation link is invalid or has expired.</p>
                    <?php elseif ($_GET['login'] === 'unverified'): ?>
                            <p class="login-message">Please activate your account first. Check your email for the activation link.</p>
                            <p class="login-message resend-note">No email? <a href="#" onclick="var e=prompt('Enter your email address'); if(e){var f=document.createElement('form');f.method='POST';f.action='pages/resend_activation.php';var i=document.createElement('input');i.type='hidden';i.name='email';i.value=e;f.appendChild(i);var t=document.createElement('input');t.type='hidden';t.name='csrf_token';t.value=document.querySelector('meta[name=csrf-token]').content;f.appendChild(t);document.body.appendChild(f);f.submit();} return false;">Resend activation link</a></p>
                    <?php elseif ($_GET['login'] === 'resent'): ?>
                            <p class="login-message login-success">If that account is waiting for activation, a new link has been sent.</p>
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
                $signupErrors = ['captcha' => 'Please complete the CAPTCHA to confirm you are not a robot.', 'empty' => 'Please fill in all fields.', 'name' => 'Full name cannot contain numbers.', 'username' => 'Username can only contain letters and numbers, and cannot be numbers only.', 'exists' => 'Username already exists.', 'email' => 'Please use valid email', 'email_taken' => 'Email is already registered.', 'mismatch' => 'Passwords do not match. Please re-enter your password.', 'password' => 'Password does not meet the requirments.', 'error' => 'Unable to create account.', 'captcha' => 'Please complete the CAPTCHA.'];?>
                <form action="pages/register.php" method="POST" id="signupForm" novalidate>
                    <?php echo csrfField(); ?>
                    <input type="text" name="fullname" id="SignupFullname" placeholder="Full Name" required >
                    <p class="signup-error" id="fullnameError">
                        <?php echo $signupStatus === 'name' ? e($signupErrors['name']) : ''; ?> </p>
                <input type="text" name="username" id="signupUsername" placeholder="Username" required >
                <p class="signup-error" id="usernameError">
                    <?php echo in_array( $signupStatus, ['username', 'exists'] ) ? e($signupErrors[$signupStatus]) : ''; ?>
                </p>
                <input type="text" name="email" id="signupEmail" placeholder="Email Address" required >
                <p class="signup-error" id="emailError"> <?php echo in_array($signupStatus, ['email', 'email_taken']) ? e($signupErrors[$signupStatus]) : ''; ?> </p>
                <input type="password" name="password" id="signupPassword" placeholder="Password" required >
                <ul class="password-rules" id="passwordRules">
                    <li id="ruleLength"> At least <?php echo (int)($activePolicy['min_password_length'] ?? 12); ?> characters in length </li>
                    <?php if ((int)($activePolicy['require_uppercase'] ?? 1) > 0): ?>
                        <li id="ruleUpper"> At least <?php echo (int)$activePolicy['require_uppercase']; ?> CAPITAL letter(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_lowercase'] ?? 1) > 0): ?>
                        <li id="ruleLower"> At least <?php echo (int)$activePolicy['require_lowercase']; ?> small letter(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_number'] ?? 1) > 0): ?>
                        <li id="ruleNumber"> At least <?php echo (int)$activePolicy['require_number']; ?> number(s) </li>
                    <?php endif; ?>
                    <?php if ((int)($activePolicy['require_special'] ?? 1) > 0): ?>
                        <li id="ruleSpecial"> At least <?php echo (int)$activePolicy['require_special']; ?> special character(s) </li>
                    <?php endif; ?>
                </ul>
                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required >
                <p class="signup-error" id="confirmMessage"> <?php echo $signupStatus === 'mismatch' ? e($signupErrors['mismatch']) : ''; ?> </p>
                <p class="login-message<?php echo in_array($signupStatus, ['success', 'verify'], true) ? ' login-success' : ''; ?>" id="signupMessage">
                    <?php echo $signupStatus === 'verify' ? 'Account created! We sent an activation link to your email. Click it to activate your account.' : ($signupStatus === 'success' ? 'Account created successfully! Please login.' : (in_array( $signupStatus, ['empty', 'password', 'error', 'captcha'] ) ? e($signupErrors[$signupStatus]) : '')); ?>
                </p>
                <?php if ($signupStatus === 'verify' && !empty($_SESSION['dev_activation_link'])): ?>
                    <p class="login-message dev-note">Dev mode (no mail server): <a href="<?php echo htmlspecialchars($_SESSION['dev_activation_link'], ENT_QUOTES, 'UTF-8'); ?>">open activation link</a></p>
                    <?php unset($_SESSION['dev_activation_link']); ?>
                <?php endif; ?>
                <?php if (RECAPTCHA_ENABLED): ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo e(RECAPTCHA_SITE_KEY); ?>"></div>
                    <p class="signup-error" id="captchaError"><?php echo $signupStatus === 'captcha' ? e($signupErrors['captcha']) : ''; ?></p>
                <?php endif; ?>
                <button type="submit" class="popup-button"> Create Account </button>
            </form>
            <p class="popup-disclaimer"> Already have an account? <a href="index.php?login=required"> Login here </a>. </p>
        </div>

    </div>


    <?php endif; ?>
    
    <?php include 'includes/footer.php'; ?>
<script>
window.passwordPolicy = {
    minLen: <?php echo (int)($activePolicy['min_password_length'] ?? 12); ?>,
    reqUpper: <?php echo (int)($activePolicy['require_uppercase'] ?? 1); ?>,
    reqLower: <?php echo (int)($activePolicy['require_lowercase'] ?? 1); ?>,
    reqNum: <?php echo (int)($activePolicy['require_number'] ?? 1); ?>,
    reqSpec: <?php echo (int)($activePolicy['require_special'] ?? 1); ?>
};
</script>
<script src="assets/js/main.js"></script>
<?php if (RECAPTCHA_ENABLED): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</body>

</html>