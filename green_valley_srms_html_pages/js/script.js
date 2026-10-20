/* ==========================================================================
   Green Valley SRMS - script.js
   Unobtrusive client-side validation and small interactions.
   Server-side validation in PHP is the source of truth; this only improves UX.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* --- Confirm before any delete link/button --- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var msg = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    /* --- Generic required-field validation on submit --- */
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var firstInvalid = null;

            form.querySelectorAll('[required]').forEach(function (field) {
                var value = (field.value || '').trim();
                field.classList.remove('field-error');
                if (value === '') {
                    field.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = field; }
                }
            });

            // Marks must be 0..100 when present
            var marks = form.querySelector('input[name="marks"]');
            if (marks && marks.value !== '') {
                var m = parseFloat(marks.value);
                if (isNaN(m) || m < 0 || m > 100) {
                    marks.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = marks; }
                }
            }

            // Basic email check when present and filled
            var email = form.querySelector('input[type="email"]');
            if (email && email.value.trim() !== '') {
                var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
                if (!ok) {
                    email.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = email; }
                }
            }

            // Phone fields: digits only (mirrors normalize_phone() in PHP).
            form.querySelectorAll('[data-phone]').forEach(function (field) {
                field.classList.remove('field-error');
                if (field.value.trim() === '') { return; }   // optional field
                if (normalizePhone(field.value) === null) {
                    field.classList.add('field-error');
                    if (!firstInvalid) { firstInvalid = field; }
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.focus();
                showFormMessage(form, 'Please correct the highlighted fields.');
            }
        });
    });

    /* --- Phone fields: block stray characters and tidy up on blur --- */
    document.querySelectorAll('[data-phone]').forEach(function (field) {
        // Strip anything that is not a digit or an allowed separator as it is
        // typed, so a letter simply never appears in the box.
        field.addEventListener('input', function () {
            var cleaned = field.value.replace(/[^0-9+\s().-]/g, '');
            if (cleaned !== field.value) {
                var pos = field.selectionStart - 1;
                field.value = cleaned;
                try { field.setSelectionRange(pos, pos); } catch (err) { /* ignore */ }
            }
            field.classList.remove('field-error');
        });

        // On blur, rewrite a valid number into the canonical 07.../01... form
        // so what the user sees matches exactly what gets stored.
        field.addEventListener('blur', function () {
            if (field.value.trim() === '') { return; }
            var normalized = normalizePhone(field.value);
            if (normalized) {
                field.value = normalized;
            } else {
                field.classList.add('field-error');
            }
        });
    });

    /* --- Live grade preview on the results form --- */
    var marksInput = document.querySelector('input[name="marks"]');
    var gradePreview = document.getElementById('grade-preview');
    if (marksInput && gradePreview) {
        var update = function () {
            var m = parseFloat(marksInput.value);
            gradePreview.textContent = isNaN(m) ? '—' : gradeFor(m);
        };
        marksInput.addEventListener('input', update);
        update();
    }
});

/* Kenyan phone number -> canonical 0712345678, or null if invalid.
   Mirrors normalize_phone() in includes/functions.php; PHP remains the
   source of truth, this only gives instant feedback. */
function normalizePhone(raw) {
    raw = (raw || '').trim();
    if (raw === '') { return null; }

    // Reject letters or unexpected symbols outright.
    if (/[^0-9+\s().-]/.test(raw)) { return null; }
    // A "+" is only meaningful as the first character.
    if (raw.indexOf('+') > 0) { return null; }

    var digits = raw.replace(/\D+/g, '');
    var subscriber;
    if (digits.indexOf('00254') === 0)    { subscriber = digits.slice(5); }
    else if (digits.indexOf('254') === 0) { subscriber = digits.slice(3); }
    else if (digits.indexOf('0') === 0)   { subscriber = digits.slice(1); }
    else                                  { subscriber = digits; }

    // 9 subscriber digits beginning 7 (Safaricom/Airtel) or 1 (011x ranges).
    return /^[71][0-9]{8}$/.test(subscriber) ? '0' + subscriber : null;
}

/* KCSE 12-point grade - mirrors calculate_grade() in PHP (preview only). */
function gradeFor(m) {
    if (m < 0) m = 0; if (m > 100) m = 100;
    if (m >= 80) return 'A';
    if (m >= 75) return 'A-';
    if (m >= 70) return 'B+';
    if (m >= 65) return 'B';
    if (m >= 60) return 'B-';
    if (m >= 55) return 'C+';
    if (m >= 50) return 'C';
    if (m >= 45) return 'C-';
    if (m >= 40) return 'D+';
    if (m >= 35) return 'D';
    if (m >= 30) return 'D-';
    return 'E';
}

/* Show a lightweight inline message above a form. */
function showFormMessage(form, text) {
    var box = form.querySelector('.js-msg');
    if (!box) {
        box = document.createElement('div');
        box.className = 'alert alert-error js-msg';
        form.parentNode.insertBefore(box, form);
    }
    box.textContent = text;
}
