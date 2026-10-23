<footer class="footer">
    <div class="footer-content">
        <div class="footer-column footer-brand">
            <h2><span>S</span> SportMart</h2>
            <p>Your trusted online store for sports equipment and gear.</p>
        </div>

        <div class="footer-column">
            <h3>Shop</h3>
            <a href="<?php echo $basePath ?? ''; ?>index.php?page=products">Products</a>
            <a href="<?php echo $basePath ?? ''; ?>index.php?page=products">Categories</a>
            <a href="<?php echo $basePath ?? ''; ?>index.php?page=products">Best Sellers</a>
        </div>

        <div class="footer-column">
            <h3>Support</h3>
            <a href="#">About Us</a>
            <a href="#">Contact Us</a>
            <a href="#">FAQs</a>
            <a href="<?php echo $basePath ?? ''; ?>pages/privacy.php">Privacy Policy</a>
        </div>

        <div class="footer-column">
            <h3>Account</h3>
            <a href="<?php echo $basePath ?? ''; ?>index.php?page=orders">My Orders</a>
            <a href="<?php echo $basePath ?? ''; ?>index.php?page=cart">Shopping Cart</a>
        </div>
    </div>

    <div class="copyright">
        © 2026 SportMart. All rights reserved.
    </div>
</footer>

<div id="cookieConsentBanner" class="cookie-banner" style="display: none;">
    <div class="cookie-content">
        <p>We use cookies to ensure you get the best experience on SportMart. By continuing, you agree to our <a href="<?php echo $basePath ?? ''; ?>pages/privacy.php">Privacy Policy</a>.</p>
        <button type="button" class="cookie-btn" onclick="acceptCookieConsent()">Accept</button>
    </div>
</div>