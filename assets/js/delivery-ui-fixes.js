(function () {
    function isGreenButton(button) {
        const style = window.getComputedStyle(button);
        const background = style.backgroundColor || '';
        const match = background.match(/rgba?\(([^)]+)\)/i);
        if (!match) return false;

        const values = match[1].split(',').map(function (value) {
            return parseFloat(value.trim());
        });

        if (values.length < 3 || values.some(function (value) { return !Number.isFinite(value); })) {
            return false;
        }

        const red = values[0];
        const green = values[1];
        const blue = values[2];
        return green > red + 10 && green > blue + 10;
    }

    function applyButtonHoverEffects() {
        const scope = document.querySelectorAll('#shopWalkInForm button, #driverDeliveriesPanel button');
        scope.forEach(function (button) {
            if (!isGreenButton(button)) return;
            button.classList.add('marcid-blue-green-action');
        });
    }

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

        applyButtonHoverEffects();
    }

    function copyShopLegendsToDriver() {
        const driverRows = document.getElementById('driverDeliveryPaymentRows');
        if (!driverRows || driverRows.dataset.legendsCopied === '1') return;

        const shopRows = document.getElementById('deliveryPaymentRows');
        if (!shopRows) return;

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
            /* Match the existing blue action-button interaction on every green action button. */
            #shopWalkInForm .marcid-blue-green-action,
            #driverDeliveriesPanel .marcid-blue-green-action {
                transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            }

            #shopWalkInForm .marcid-blue-green-action:hover,
            #driverDeliveriesPanel .marcid-blue-green-action:hover {
                background: var(--success-dark, #247d49) !important;
                transform: translateY(-1px);
                box-shadow: var(--shadow-sm);
            }

            #shopWalkInForm .marcid-blue-green-action:active,
            #driverDeliveriesPanel .marcid-blue-green-action:active {
                transform: translateY(0);
                box-shadow: none;
            }

            #shopWalkInForm .marcid-blue-green-action:disabled,
            #driverDeliveriesPanel .marcid-blue-green-action:disabled {
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
