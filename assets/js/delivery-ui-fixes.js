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

        document.querySelectorAll('.delivery-price-input, .driver-delivery-price').forEach(function (input) {
            input.setAttribute('placeholder', '0');
            input.setAttribute('step', '5');
        });
    }

    function copyShopLegendsToDriver() {
        const driverRows = document.getElementById('driverDeliveryPaymentRows');
        const shopRows = document.getElementById('deliveryPaymentRows');
        if (!driverRows || !shopRows || driverRows.dataset.legendsCopied === '1') return;

        const shopSection = shopRows.closest('.card, .card-body, .panel, section') || shopRows.parentElement;
        const driverSection = driverRows.closest('.driver-panel-section');
        if (!shopSection || !driverSection) return;

        const shopLegends = shopSection.querySelectorAll('legend, [class*="legend"], [class*="Legend"]');
        if (!shopLegends.length) return;

        const driverHeader = driverSection.querySelector('.driver-panel-header');
        if (!driverHeader) return;

        shopLegends.forEach(function (legend) {
            const clone = legend.cloneNode(true);
            clone.classList.add('driver-delivery-legend-copy');
            driverHeader.appendChild(clone);
        });

        driverRows.dataset.legendsCopied = '1';
    }

    function injectStyles() {
        if (document.getElementById('marcid-blue-delivery-ui-fixes-style')) return;

        const style = document.createElement('style');
        style.id = 'marcid-blue-delivery-ui-fixes-style';
        style.textContent = `
            /* Use the same hover interaction as the existing action buttons. */
            #shopWalkInForm .btn-primary:hover,
            #driverDeliveriesPanel .btn-primary:hover {
                background: var(--primary-dark) !important;
                transform: translateY(-1px);
                box-shadow: var(--shadow-sm);
            }

            #shopWalkInForm .btn-secondary:hover,
            #driverDeliveriesPanel .btn-secondary:hover,
            #shopWalkInForm .btn-success:hover,
            #driverDeliveriesPanel .btn-success:hover {
                background: var(--secondary-dark, var(--success-dark, #247d49)) !important;
                transform: translateY(-1px);
                box-shadow: var(--shadow-sm);
            }

            #shopWalkInForm .btn-primary,
            #driverDeliveriesPanel .btn-primary,
            #shopWalkInForm .btn-secondary,
            #driverDeliveriesPanel .btn-secondary,
            #shopWalkInForm .btn-success,
            #driverDeliveriesPanel .btn-success {
                transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            }

            #shopWalkInForm .btn-primary:active,
            #driverDeliveriesPanel .btn-primary:active,
            #shopWalkInForm .btn-secondary:active,
            #driverDeliveriesPanel .btn-secondary:active,
            #shopWalkInForm .btn-success:active,
            #driverDeliveriesPanel .btn-success:active {
                transform: translateY(0);
                box-shadow: none;
            }

            #shopWalkInForm .btn:disabled,
            #driverDeliveriesPanel .btn:disabled {
                transform: none;
                box-shadow: none;
            }

            .driver-delivery-legend-copy {
                margin-left: 12px;
            }
        `;
        document.head.appendChild(style);
    }

    function init() {
        injectStyles();
        cleanDeliveryUI();
        copyShopLegendsToDriver();

        const observer = new MutationObserver(function () {
            cleanDeliveryUI();
            copyShopLegendsToDriver();
        });

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
