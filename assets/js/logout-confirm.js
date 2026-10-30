/*
 * Logout confirmation popup.
 * Any link pointing to logout.php opens a popup:
 * "Are you sure you want to log out?" with OK / Cancel.
 */
(function () {
    'use strict';

    if (window.__logoutConfirmLoaded) return;
    window.__logoutConfirmLoaded = true;

    var LOGOUT_RE = /(^|\/)logout\.php(\?|#|$)/;

    var css = '' +
        '.lc-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:100000;padding:16px;font-family:Arial,Helvetica,sans-serif}' +
        '.lc-overlay.lc-show{display:flex}' +
        '.lc-box{background:#fff;color:#222;width:100%;max-width:360px;border-radius:12px;padding:26px 24px;box-shadow:0 12px 40px rgba(0,0,0,.3);text-align:center;box-sizing:border-box}' +
        '.lc-box h3{margin:0 0 8px;font-size:20px}' +
        '.lc-box p{margin:0 0 20px;font-size:15px;color:#555}' +
        '.lc-actions{display:flex;gap:10px}' +
        '.lc-actions button{flex:1;padding:10px 12px;font-size:15px;border:0;border-radius:8px;cursor:pointer}' +
        '.lc-cancel{background:#e9ecef;color:#333}' +
        '.lc-cancel:hover{background:#dee2e6}' +
        '.lc-ok{background:#8b2e2e;color:#fff}' +
        '.lc-ok:hover{background:#701631}';

    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    var overlay, okBtn, targetHref = null, lastFocus = null;

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'lc-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'lcTitle');
        overlay.innerHTML =
            '<div class="lc-box">' +
                '<h3 id="lcTitle">Log out</h3>' +
                '<p>Are you sure you want to log out?</p>' +
                '<div class="lc-actions">' +
                    '<button type="button" class="lc-cancel">Cancel</button>' +
                    '<button type="button" class="lc-ok">OK</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        okBtn = overlay.querySelector('.lc-ok');
        overlay.querySelector('.lc-cancel').addEventListener('click', close);
        okBtn.addEventListener('click', function () {
            var href = targetHref;
            overlay.classList.remove('lc-show');
            window.location.href = href;
        });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) {
            if (overlay.classList.contains('lc-show') && e.key === 'Escape') close();
        });
    }

    function open(href) {
        if (!overlay) build();
        targetHref = href;
        lastFocus = document.activeElement;
        overlay.classList.add('lc-show');
        okBtn.focus();
    }

    function close() {
        overlay.classList.remove('lc-show');
        targetHref = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || !LOGOUT_RE.test(href)) return;
        e.preventDefault();
        open(a.href);
    }, true);
})();