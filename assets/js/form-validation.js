/*
 * Inline form validation
 * Replaces the browser's default "Please fill out this field." popup with a
 * red error message shown directly below the input.
 *
 * - Works on every form that uses HTML validation attributes (required,
 *   pattern, min, max, minlength, type="email", ...).
 * - Forms with the `novalidate` attribute keep using their own validation.
 * - Add data-native-validation to a form to keep the browser popup.
 */
(function () {
    "use strict";

    var style = document.createElement("style");
    style.textContent =
        ".js-field-error{display:block;margin:6px 0 0;color:#c62828;font-size:12px;" +
        "line-height:1.4;font-weight:500;text-align:left;letter-spacing:normal;text-transform:none;}" +
        ".js-invalid{border-color:#c62828 !important;}";
    document.head.appendChild(style);

    var counter = 0;

    function isHandled(field) {
        var form = field.form;
        return !!form && !form.noValidate && !form.hasAttribute("data-native-validation");
    }

    function getMessage(field) {
        var v = field.validity;
        var type = (field.type || "").toLowerCase();

        if (v.customError && field.validationMessage) {
            return field.validationMessage;
        }
        if (v.valueMissing) {
            if (field.tagName === "SELECT" || type === "radio") return "Please select an option.";
            if (type === "checkbox") return "Please check this box to continue.";
            if (type === "file") return "Please choose a file.";
            return "This field is required.";
        }
        if (v.typeMismatch) {
            if (type === "email") return "Please enter a valid email address.";
            if (type === "url") return "Please enter a valid URL.";
            return "Please enter a valid value.";
        }
        if (v.patternMismatch) {
            return field.title || "Please match the requested format.";
        }
        if (v.tooShort) {
            return "Please use at least " + field.minLength + " characters.";
        }
        if (v.tooLong) {
            return "Please use no more than " + field.maxLength + " characters.";
        }
        if (v.badInput) {
            return "Please enter a valid value.";
        }
        if (v.rangeUnderflow) {
            return "Value must be " + field.min + " or more.";
        }
        if (v.rangeOverflow) {
            return "Value must be " + field.max + " or less.";
        }
        if (v.stepMismatch) {
            return "Please enter a valid value.";
        }
        return field.validationMessage || "Please check this field.";
    }

    function anchorFor(field) {
        var type = (field.type || "").toLowerCase();

        if (type === "checkbox" || type === "radio") {
            var label = field.closest("label");
            if (label) return label;
        }

        var parent = field.parentElement;
        if (parent && parent !== field.form &&
            /password|input-wrap|input-group|field-wrap|toggle/i.test(parent.className || "")) {
            return parent;
        }

        return field;
    }

    function showError(field) {
        var message = getMessage(field);
        var el = field._jsErrorEl;

        if (!el || !el.parentNode) {
            el = document.createElement("div");
            el.className = "js-field-error";
            el.id = "js-field-error-" + (++counter);
            el.setAttribute("role", "alert");

            var anchor = anchorFor(field);
            anchor.parentNode.insertBefore(el, anchor.nextSibling);

            field._jsErrorEl = el;
            field.setAttribute("aria-describedby", el.id);
        }

        el.textContent = message;
        field.classList.add("js-invalid");
        field.setAttribute("aria-invalid", "true");
    }

    function clearError(field) {
        if (field._jsErrorEl) {
            if (field._jsErrorEl.parentNode) {
                field._jsErrorEl.parentNode.removeChild(field._jsErrorEl);
            }
            field._jsErrorEl = null;
        }
        field.classList.remove("js-invalid");
        field.removeAttribute("aria-invalid");
        field.removeAttribute("aria-describedby");
    }

    function clearFormErrors(root) {
        var fields = root.querySelectorAll(".js-invalid");
        for (var i = 0; i < fields.length; i++) {
            clearError(fields[i]);
        }
        var stray = root.querySelectorAll(".js-field-error");
        for (var j = 0; j < stray.length; j++) {
            if (stray[j].parentNode) stray[j].parentNode.removeChild(stray[j]);
        }
    }

    // "invalid" does not bubble, so listen during the capture phase.
    document.addEventListener("invalid", function (event) {
        var field = event.target;
        if (!field || !isHandled(field)) return;

        // Stop the browser from showing its own popup.
        event.preventDefault();
        showError(field);

        // Focus the first invalid field of this submit attempt.
        var form = field.form;
        if (!form._jsFocused) {
            form._jsFocused = true;
            field.focus();
            setTimeout(function () { form._jsFocused = false; }, 0);
        }
    }, true);

    function onEdit(event) {
        var field = event.target;
        if (!field || !field._jsErrorEl) return;

        if (field.validity && field.validity.valid) {
            clearError(field);
        } else {
            showError(field);
        }
    }

    document.addEventListener("input", onEdit, true);
    document.addEventListener("change", onEdit, true);

    document.addEventListener("reset", function (event) {
        if (event.target && event.target.querySelectorAll) {
            clearFormErrors(event.target);
        }
    }, true);

    // Clear old messages when a modal/popup is opened or closed.
    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var target = mutations[i].target;
                if (target.nodeType === 1 && target.querySelector(".js-field-error")) {
                    clearFormErrors(target);
                }
            }
        }).observe(document.documentElement, {
            attributes: true,
            subtree: true,
            attributeFilter: ["style", "class"]
        });
    }

    window.clearFormErrors = clearFormErrors;
})();