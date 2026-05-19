// Site-wide JS

(function () {
    'use strict';

    function pwdStrength(pwd) {
        // Должно совпадать с password_strength() в lib/password.php
        if (!pwd || pwd.length < 6) return 0;
        var s = 0;
        if (/[a-z]/.test(pwd)) s++;
        if (/[A-Z]/.test(pwd)) s++;
        if (/\d/.test(pwd))    s++;
        if (/[^A-Za-z0-9]/.test(pwd)) s++;
        return Math.min(s, 4);
    }

    var labels = ['Очень слабый', 'Слабый', 'Средний', 'Хороший', 'Отличный'];
    var colors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];

    function initPasswordStrength() {
        var input  = document.getElementById('password');
        var bar    = document.getElementById('pwd-strength');
        var label  = document.getElementById('pwd-strength-label');
        var submit = document.getElementById('register-submit');
        if (!input || !bar || !label) return;

        function update() {
            var score = pwdStrength(input.value);
            bar.style.width = (25 * score) + '%';
            bar.className = 'progress-bar ' + colors[score];
            label.textContent = input.value === '' ? '' : labels[score];
            if (submit) submit.disabled = score < 2;
        }

        input.addEventListener('input', update);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordStrength);
    } else {
        initPasswordStrength();
    }
})();
