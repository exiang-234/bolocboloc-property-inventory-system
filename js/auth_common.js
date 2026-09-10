(function () {
    document.querySelectorAll('.borrow-role-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var roleInput = document.getElementById('role');
            if (!roleInput) return;
            roleInput.value = this.getAttribute('data-role') || '';
            document.querySelectorAll('.borrow-role-btn').forEach(function (btn) {
                btn.classList.remove('is-active');
            });
            this.classList.add('is-active');
        });
    });

    window.bpisTogglePassword = function (inputId, btn) {
        var input = document.getElementById(inputId);
        if (!input || !btn) return;
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
    };
})();
