<footer class="footer" id="siteFooter">
    <div class="footer-content">
        <div class="footer-column footer-brand">
            <h2><span>S</span> SportMart</h2>
            <p>Your trusted online store for sports equipment and gear.</p>

            <div class="footer-social" aria-label="SportMart social media">
                <a href="https://facebook.com/sportmartph" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21h3.1z"/></svg>
                </a>
                <a href="https://instagram.com/sportmartph" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
                </a>
                <a href="https://tiktok.com/@sportmartph" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16.6 3c.3 2.3 1.6 3.7 3.9 3.9v2.9c-1.4.1-2.6-.3-3.8-1.1v5.4c0 6.9-7.5 9-10.5 4.1-1.9-3.1-.7-8.6 5.3-8.8v3.1c-.5.1-.9.2-1.4.4-1.3.4-2 1.2-1.8 2.6.4 2.7 5.3 3.5 4.9-1.8V3h3.4z"/></svg>
                </a>
            </div>
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
            <a href="#" id="contactToggle" aria-haspopup="dialog" aria-controls="contactModal">Contact Us</a>
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
        © <?php echo date('Y'); ?> SportMart. All rights reserved.
    </div>
</footer>

<div id="cookieConsentBanner" class="cookie-banner" style="display: none;">
    <div class="cookie-content">
        <p>We use cookies to ensure you get the best experience on SportMart. By continuing, you agree to our <a href="<?php echo $basePath ?? ''; ?>pages/privacy.php">Privacy Policy</a>.</p>
        <button type="button" class="cookie-btn" onclick="acceptCookieConsent()">Accept</button>
    </div>
</div>

<div class="popup" id="contactModal" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">
    <div class="popup-box contact-modal-box">
        <button type="button" class="close" id="contactModalClose" aria-label="Close">&times;</button>

        <h2 id="contactModalTitle">Contact Us</h2>
        <p class="contact-modal-sub">We're happy to help. Reach out to us anytime.</p>

        <div class="contact-modal-list">
            <div class="contact-modal-item">
                <span class="contact-modal-icon" aria-hidden="true">📍</span>
                <div>
                    <strong>Address</strong>
                    <address>
                        SportMart Store, 123 Sports Avenue,<br>
                        Brgy. San Antonio, Pasig City,<br>
                        Metro Manila 1605, Philippines
                    </address>
                </div>
            </div>

            <div class="contact-modal-item">
                <span class="contact-modal-icon" aria-hidden="true">📞</span>
                <div>
                    <strong>Phone</strong>
                    <a href="tel:+639171234567">+63 917 123 4567</a>
                    <a href="tel:+63285550142">(02) 8555 0142</a>
                </div>
            </div>

            <div class="contact-modal-item">
                <span class="contact-modal-icon" aria-hidden="true">✉</span>
                <div>
                    <strong>Email</strong>
                    <a href="mailto:support@sportmart.ph">support@sportmart.ph</a>
                </div>
            </div>

            <div class="contact-modal-item">
                <span class="contact-modal-icon" aria-hidden="true">🕘</span>
                <div>
                    <strong>Store Hours</strong>
                    <span>Mon – Sat: 9:00 AM – 6:00 PM</span>
                    <span>Sunday: Closed</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var trigger = document.getElementById("contactToggle");
    var modal = document.getElementById("contactModal");
    var closeBtn = document.getElementById("contactModalClose");
    if (!trigger || !modal) return;

    function openModal(e) {
        if (e) e.preventDefault();
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
        closeBtn.focus();
    }

    function closeModal() {
        modal.classList.remove("show");
        document.body.style.overflow = "";
        trigger.focus();
    }

    trigger.addEventListener("click", openModal);
    closeBtn.addEventListener("click", closeModal);

    modal.addEventListener("click", function (e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.classList.contains("show")) closeModal();
    });
})();
</script>