function showCart() {
    const popup = document.getElementById("cartPopup");

    if (popup) {
        popup.style.display = "flex";
    }
}

function showLogin() {
    const popup = document.getElementById("loginPopup");

    if (popup) {
        popup.style.display = "flex";
    } else {
        window.location.href = "index.php?login=required";
    }
}

function showSignup() {
    const popup = document.getElementById("signupPopup");

    if (popup) {
        popup.style.display = "flex";
    } else {
        window.location.href = "index.php?signup=open";
    }
}

function closePopup(id) {
    const popup = document.getElementById(id);

    if (popup) {
        popup.style.display = "none";
    }
}

function showProfileMenu() {
    const sidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("profileOverlay");

    if (!sidebar) {
        window.location.href = "index.php?profile=open";
        return;
    }

    sidebar.classList.add("show");

    if (overlay) {
        overlay.classList.add("show");
    }
}

function closeProfileMenu() {
    const sidebar = document.getElementById("profileSidebar");
    const overlay = document.getElementById("profileOverlay");

    if (sidebar) {
        sidebar.classList.remove("show");
    }

    if (overlay) {
        overlay.classList.remove("show");
    }
}

function showProfilePage() {
    closeProfileMenu();

    const page = document.getElementById("profilePage");

    if (page) {
        page.style.display = "flex";
    }
}

function toggleAccountMenu() {
    const accountMenu = document.querySelector('.account-menu');
    if (!accountMenu) { return; }
    accountMenu.classList.toggle('open');
    const button = accountMenu.querySelector('.account-trigger');
    if (button) { const isOpen = accountMenu.classList.contains('open');
        button.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
    }
}
document.addEventListener('click', function (event) {
    const accountMenu = document.querySelector('.account-menu');
    if (!accountMenu) { return; }
    if (!accountMenu.contains(event.target)) {
        accountMenu.classList.remove('open');
        const button = accountMenu.querySelector('.account-trigger');
        if (button) { button.setAttribute('aria-expanded', 'false'); }
    }
});

function showSettingsPage() {
    closeProfileMenu();

    const page = document.getElementById("settingsPage");

    if (page) {
        page.style.display = "flex";
    }
}

function closeAccountPage(id) {
    const page = document.getElementById(id);

    if (page) {
        page.style.display = "none";
    }
}

let toastTimer = null;

function showToast(message, showCartLink) {
    let toast = document.getElementById("cartToast");

    if (!toast) {
        toast = document.createElement("div");
        toast.id = "cartToast";
        toast.className = "cart-toast";
        document.body.appendChild(toast);
    }

    toast.innerHTML = "";

    const text = document.createElement("span");
    text.textContent = message;
    toast.appendChild(text);

    if (showCartLink) {
        const link = document.createElement("a");
        link.href = "index.php?page=cart";
        link.textContent = "View Cart";
        toast.appendChild(link);
    }

    toast.classList.add("show");

    clearTimeout(toastTimer);

    toastTimer = setTimeout(function () {
        toast.classList.remove("show");
    }, 3500);
}

document.addEventListener("DOMContentLoaded", function () {
    const orderFilter = document.getElementById("orderFilter");

    if (orderFilter) {
        orderFilter.addEventListener("change", function () {
            const orders = document.querySelectorAll(".order-card");

            orders.forEach(function (order) {
                if (orderFilter.value === "All" || order.dataset.status === orderFilter.value) {
                    order.style.display = "flex";
                } else {
                    order.style.display = "none";
                }
            });
        });
    }

    const addCartButtons = document.querySelectorAll(".product-buttons .add-cart-button, .product-details-actions .add-cart-button");

    addCartButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const product = button.closest(".product");
            const productId = button.dataset.id || (product ? product.dataset.id : "")

            if (!productId) {
                showToast("Product information is missing.", false);
                return;
            }

            const formData = new FormData();
            formData.append("add_to_cart", "1");
            formData.append("product_id", productId);
            formData.append("csrf_token", (document.querySelector('meta[name="csrf-token"]') || {}).content || "");

            button.disabled = true;

            fetch("pages/cart.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        showToast(data.name + " added to cart.", true);
                    } else if (data.login_required) {
                        showToast(data.message, false);
                        showLogin();
                    } else {
                        showToast("Unable to add product to cart.", false);
                    }
                })
                .catch(function () {
                    showToast("Something went wrong. Please try again.", false);
                })
                .finally(function () {
                    button.disabled = false;
                });
        });
    });

    const buyButtons = document.querySelectorAll(".product-buttons .buy-button, .product-details-actions .buy-button");

    buyButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const product = button.closest(".product");
            const productId = button.dataset.id || (product ? product.dataset.id : "");

            if (!productId) {
                showToast("Product information is missing.", false);
                return;
            }

            button.disabled = true;

            const form = document.createElement("form");
            form.method = "POST";
            form.action = "pages/cart.php";

            const fields = {
                add_to_cart: "1",
                buy_now: "1",
                product_id: productId,
                csrf_token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ""
            };

            Object.keys(fields).forEach(function (key) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    });

    const signupForm = document.getElementById("signupForm");

    if (signupForm) {
        const password = document.getElementById("signupPassword");
        const confirm = document.getElementById("confirmPassword");
        const confirmMessage = document.getElementById("confirmMessage");

        const policy = window.passwordPolicy || { minLen: 12, reqUpper: 1, reqLower: 1, reqNum: 1, reqSpec: 1 };

        const rules = [
            {
                element: document.getElementById("ruleLength"),
                test: function (value) {
                    return value.length >= policy.minLen;
                }
            },
            {
                element: document.getElementById("ruleUpper"),
                test: function (value) {
                    return (value.match(/[A-Z]/g) || []).length >= policy.reqUpper;
                }
            },
            {
                element: document.getElementById("ruleLower"),
                test: function (value) {
                    return (value.match(/[a-z]/g) || []).length >= policy.reqLower;
                }
            },
            {
                element: document.getElementById("ruleNumber"),
                test: function (value) {
                    return (value.match(/[0-9]/g) || []).length >= policy.reqNum;
                }
            },
            {
                element: document.getElementById("ruleSpecial"),
                test: function (value) {
                    return (value.match(/[^A-Za-z0-9]/g) || []).length >= policy.reqSpec;
                }
            }
        ];

        const updateRules = function () {
            rules.forEach(function (rule) {
                rule.element.classList.toggle("met", rule.test(password.value));
            });
        };

        const updateConfirm = function () {
            if (confirm.value === "") {
                confirmMessage.className = "signup-error";
                confirmMessage.textContent = "";
                return;
            }

            if (password.value === confirm.value) {
                confirmMessage.className = "signup-success";
                confirmMessage.textContent = "Passwords match.";
            } else {
                confirmMessage.className = "signup-error";
                confirmMessage.textContent = "Passwords do not match. Please re-enter your password.";
            }
        };

        password.addEventListener("input", function () {
            updateRules();

            if (confirm.value !== "") {
                updateConfirm();
            }
        });

        confirm.addEventListener("input", updateConfirm);
    }

    const loginPopup = document.getElementById("loginPopup");

    if (loginPopup && window.location.search.includes("login=")) {
        loginPopup.style.display = "flex";
    }

    const signupPopup = document.getElementById("signupPopup");

    if (signupPopup && window.location.search.includes("signup=")) {
        signupPopup.style.display = "flex";
    }

    if (window.location.search.includes("profile=open") && document.getElementById("profileSidebar")) {
        showProfileMenu();
    }

    const mobile = document.getElementById("checkoutMobile");
    const address = document.getElementById("checkoutAddress");
    const request = document.getElementById("checkoutRequest");

    if (mobile && address && request) {
        try {
            const saved = JSON.parse(sessionStorage.getItem("checkoutFields") || "null");

                if (saved) {
                if (typeof saved.mobile === "string") mobile.value = saved.mobile;
                if (typeof saved.address === "string") address.value = saved.address;
                if (typeof saved.request === "string") request.value = saved.request;

                sessionStorage.removeItem("checkoutFields");
            }
        } catch (error) {}
    }

    const placeOrderForm = document.getElementById("placeOrderForm");
    const placeOrderButton = document.getElementById("placeOrderButton");

    if (placeOrderForm && placeOrderButton) {
        placeOrderForm.addEventListener("submit", function () {
            placeOrderButton.disabled = true;
            placeOrderButton.textContent = "Placing order...";
        });
    }

    if (document.querySelector(".order-notice")) {
        try {
            sessionStorage.removeItem("checkoutFields");
        } catch (error) {}
    }

    const passwordInputs = document.querySelectorAll('input[type="password"]');

    passwordInputs.forEach(function (input) {
        const wrapper = document.createElement("div");
        wrapper.className = "password-field";

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "password-toggle";
        toggle.textContent = "Show";
        toggle.setAttribute("aria-label", "Show password");

        toggle.addEventListener("click", function () {
            const hidden = input.type === "password";

            input.type = hidden ? "text" : "password";
            toggle.textContent = hidden ? "Hide" : "Show";
            toggle.setAttribute("aria-label", hidden ? "Hide password" : "Show password");
        });

        wrapper.appendChild(toggle);
    });
});

let quantityTimer = null;

function saveCheckoutFields() {
    const mobile = document.getElementById("checkoutMobile");
    const address = document.getElementById("checkoutAddress");
    const request = document.getElementById("checkoutRequest");

    if (!mobile || !address || !request) {
        return;
    }

    try {
        sessionStorage.setItem("checkoutFields", JSON.stringify({
            mobile: mobile.value,
            address: address.value,
            request: request.value
        }));
    } catch (error) {}
}

function changeQuantity(id, change) {
    const input = document.getElementById("quantity-" + id);

    if (!input) {
        return;
    }

    const current = parseInt(input.value) || 0;
    input.value = Math.max(0, Math.min(99, current + change));

    clearTimeout(quantityTimer);

    quantityTimer = setTimeout(function () {
        const form = input.closest("form");

        if (form) {
            saveCheckoutFields();
            form.submit();
        }
    }, 400);
}

function openPopup(id) {
    const popup = document.getElementById(id);

    if (popup) {
        popup.style.display = "flex";
    }
}

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeProfileMenu();
    }
});


document.addEventListener("DOMContentLoaded", function () {
    const newPassword = document.getElementById("newPassword");
    const confirmPassword = document.getElementById("confirmNewPassword");
    const rulesList = document.getElementById("newPasswordRules");
    const confirmMessage = document.getElementById("confirmNewMessage");

    if (!newPassword || !confirmPassword || !rulesList) {
        return;
    }

    const readInt = function (key, fallback) {
        const n = parseInt(rulesList.dataset[key], 10);
        return isNaN(n) ? fallback : n;
    };
    const countMatches = function (value, regex) {
        const m = value.match(regex);
        return m ? m.length : 0;
    };

    const tests = {
        length: function (value) { return value.length >= readInt("minLength", 12) && value.length <= 64; },
        upper: function (value) { return countMatches(value, /[A-Z]/g) >= readInt("minUpper", 1); },
        lower: function (value) { return countMatches(value, /[a-z]/g) >= readInt("minLower", 1); },
        number: function (value) { return countMatches(value, /[0-9]/g) >= readInt("minNumber", 1); },
        special: function (value) { return countMatches(value, /[^A-Za-z0-9]/g) >= readInt("minSpecial", 1); }
    };

    const updateRules = function () {
        rulesList.querySelectorAll("li").forEach(function (item) {
            const test = tests[item.dataset.rule];
            item.classList.toggle("met", !!test && test(newPassword.value));
        });
    };

    const updateConfirm = function () {
        if (confirmPassword.value === "") {
            confirmMessage.textContent = "";
            confirmMessage.className = "match-message";
            return;
        }

        if (newPassword.value === confirmPassword.value) {
            confirmMessage.textContent = "Passwords match.";
            confirmMessage.className = "match-message ok";
        } else {
            confirmMessage.textContent = "Passwords do not match.";
            confirmMessage.className = "match-message bad";
        }
    };

    newPassword.addEventListener("input", function () {
        updateRules();
        updateConfirm();
    });

    confirmPassword.addEventListener("input", updateConfirm);
});

document.addEventListener("DOMContentLoaded", function () {
    if (!localStorage.getItem("cookieConsent")) {
        const banner = document.getElementById("cookieConsentBanner");
        if (banner) {
            banner.style.display = "block";
        }
    }
});

function acceptCookieConsent() {
    localStorage.setItem("cookieConsent", "accepted");
    const banner = document.getElementById("cookieConsentBanner");
    if (banner) {
        banner.style.display = "none";
    }
}