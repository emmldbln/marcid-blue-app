(function () {
    function cleanDeliveryUI() {
        document.querySelectorAll('.driver-delivery-note, .delivery-price-note, .delivery-price-group span').forEach(function (note) {
            if (note.textContent.trim() === 'Customer price will be used when available.') {
                note.remove();
            }
        });

        document.querySelectorAll('.delivery-payment, .driver-delivery-payment').forEach(function (input) {
            input.setAttribute('placeholder', '0');
        });
    }

    function init() {
        cleanDeliveryUI();

        const observer = new MutationObserver(cleanDeliveryUI);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
