<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - SportMart</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="logo"><span>S</span> SportMart</a>
        <div class="nav-links">
            <a href="../index.php">Home</a>
            <a href="../index.php?page=products">Products</a>
        </div>
        <div class="nav-right">
            <a href="../index.php" class="signup-link">Back to Store</a>
        </div>
    </nav>

    <main class="privacy-container">
        <a href="../index.php" class="back-link">&larr; Back to Home</a>
        
        <h1>Privacy Policy & Cookie Notice</h1>
        <p class="effective-date">Last Updated: October 2026</p>

        <div class="privacy-section">
            <h2>1. Information We Collect</h2>
            <p>When you register, place an order, or browse SportMart, we collect personal details necessary to provide our services, including:</p>
            <ul>
                <li>Account registration data (Full Name, Username, Email address).</li>
                <li>Delivery & Contact details (Shipping Address, Phone Number).</li>
                <li>Authentication and security logs (Hashed Passwords, IP addresses, Login history).</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>2. How We Use Your Information</h2>
            <p>Your information is strictly used for order fulfillment, account security, and improving system operations:</p>
            <ul>
                <li>Processing and delivering your sport equipment purchases.</li>
                <li>Enforcing security measures such as account lockout policies after repeated failed logins.</li>
                <li>Maintaining centralized audit logs for system transparency and fraud prevention.</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>3. Cookie Consent</h2>
            <p>SportMart uses essential local storage cookies to manage user login sessions and retain site navigation preferences. By accepting our cookie banner, you consent to maintaining your local session state.</p>
        </div>

        <div class="privacy-section">
            <h2>4. Data Protection & Security</h2>
            <p>We enforce strict access controls (Role-Based Access Control) and security safeguards. Passwords are salted and hashed using standard bcrypt hashing algorithms, and sensitive data is stored securely in encrypted databases.</p>
        </div>

        <div class="privacy-section">
            <h2>5. Your Rights</h2>
            <p>You have the right to access, update, or request the deletion of your personal profile data at any time through your account settings panel.</p>
        </div>
    </main>

    <?php include_once '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
</body>
</html>