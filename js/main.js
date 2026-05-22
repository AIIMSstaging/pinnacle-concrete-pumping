// ============================================
// Pinnacle Concrete Pumping Group - Landing page scripts
// ============================================

(function () {
    'use strict';

    // ----- Configuration -----
    var API_BASE = 'https://api.pinnacleconcretepumping.com.au';
    var RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_SITE_KEY';
    var THANK_YOU_URL = 'thank-you.html';

    // Footer year
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // Sticky header shadow on scroll
    var header = document.getElementById('siteHeader');
    var onScroll = function () {
        if (!header) return;
        if (window.scrollY > 12) {
            header.classList.add('scrolled');
            document.body.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
            document.body.classList.remove('scrolled');
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile nav toggle
    var navToggle = document.getElementById('navToggle');
    var primaryNav = document.getElementById('primaryNav');
    var primaryNavClose = document.getElementById('primaryNavClose');
    var openNav = function () {
        primaryNav.classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    var closeNav = function () {
        primaryNav.classList.remove('open');
        document.body.style.overflow = '';
    };
    if (navToggle && primaryNav) {
        navToggle.addEventListener('click', function () {
            if (primaryNav.classList.contains('open')) closeNav(); else openNav();
        });
        if (primaryNavClose) primaryNavClose.addEventListener('click', closeNav);
        primaryNav.addEventListener('click', function (e) {
            if (e.target === primaryNav) closeNav();
        });
        primaryNav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeNav);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && primaryNav.classList.contains('open')) closeNav();
        });
    }

    // ----- Form validation helpers -----
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var phoneRegex = /^[\d\s\+\-\(\)]{6,}$/;

    function setFieldError(field, hasError) {
        field.style.borderColor = hasError ? '#E91E8C' : '';
    }

    function validateForm(form) {
        var valid = true;
        var fields = form.querySelectorAll('[required]');
        fields.forEach(function (field) {
            var value = (field.value || '').trim();
            var fieldValid = value.length > 0;

            if (fieldValid && field.type === 'email') {
                fieldValid = emailRegex.test(value);
            }
            if (fieldValid && field.type === 'tel') {
                fieldValid = phoneRegex.test(value);
            }

            setFieldError(field, !fieldValid);
            if (!fieldValid) valid = false;
        });
        return valid;
    }

    // ----- reCAPTCHA token -----
    function getRecaptchaToken(action) {
        return new Promise(function (resolve, reject) {
            if (typeof grecaptcha === 'undefined') {
                reject(new Error('reCAPTCHA not loaded'));
                return;
            }
            grecaptcha.ready(function () {
                grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: action })
                    .then(resolve)
                    .catch(reject);
            });
        });
    }

    // ----- Generic submitter -----
    function submitForm(form, endpoint, action, btnLoadingText) {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        var originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = btnLoadingText || 'Sending…';

        getRecaptchaToken(action)
            .then(function (token) {
                var formData = new FormData(form);
                formData.append('recaptcha_token', token);

                return fetch(API_BASE + endpoint, {
                    method: 'POST',
                    body: formData
                });
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data && result.data.success) {
                    window.location.href = THANK_YOU_URL;
                } else {
                    var msg = (result.data && result.data.message) ? result.data.message : 'Something went wrong. Please try again or call us.';
                    alert(msg);
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            })
            .catch(function () {
                alert('Network error. Please try again or call 1300 688 390.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    }

    // ----- Quote form (large form) -> form-quote.php -----
    var quoteForm = document.getElementById('quoteForm');
    if (quoteForm) {
        quoteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateForm(quoteForm)) return;
            submitForm(
                quoteForm,
                '/form-quote.php',
                'quote_form',
                '<span class="material-icons">hourglass_top</span>Sending…'
            );
        });
    }

    // ----- Mini form (simple) -> form.php -----
    var miniForm = document.getElementById('miniForm');
    if (miniForm) {
        miniForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validateForm(miniForm)) return;
            submitForm(
                miniForm,
                '/form.php',
                'mini_form',
                'Sending…'
            );
        });
    }

    // Smooth scroll fix for sticky header offset
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var hash = link.getAttribute('href');
            if (hash.length < 2) return;
            var target = document.querySelector(hash);
            if (!target) return;
            e.preventDefault();
            var headerOffset = header ? header.offsetHeight : 0;
            var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset - 8;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });
})();
