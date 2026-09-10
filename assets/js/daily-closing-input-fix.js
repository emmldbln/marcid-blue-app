(function () {
    function init() {
        const form = document.getElementById('shopWalkInForm');
        if (!form || form.dataset.inputFixReady === '1') return;
        form.dataset.inputFixReady = '1';

        const moneyInput = document.getElementById('walk_in_money');

        // An empty Shop Total Money Received means zero. This is important
        // for a blank/reset draft and prevents the old server-side validation
        // from treating an empty field as an invalid amount.
        form.addEventListener('submit', function () {
            if (moneyInput && moneyInput.value.trim() === '') {
                moneyInput.value = '0';
            }
        });

        // Keep the page quiet while the field is being edited.
        form.addEventListener('input', function () {
            if (moneyInput && moneyInput.value.trim() === '') {
                moneyInput.setCustomValidity('');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
