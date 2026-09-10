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

    function findShopLegend() {
        const shopRows = document.getElementById('deliveryPaymentRows');
        if (!shopRows) return null;

        const shopSection = shopRows.closest('.card, .card-body, .panel, section') || shopRows.parentElement;
        if (!shopSection) return null;

        const selectors = [
            '.delivery-status-legend',
            '.delivery-legend',
            '.status-legend',
            '[class*="delivery-legend"]',
            '[class*="status-legend"]'
        ];

        for (const selector of selectors) {
            const legend = shopSection.querySelector(selector);
            if (legend) return legend;
        }

        return null;
    }

    function addDriverLegend() {
        const driverRows = document.getElementById('driverDeliveryPaymentRows');
        const driverSection = driverRows?.closest('.driver-panel-section');
        if (!driverRows || !driverSection) return;

        const header = driverSection.querySelector('.driver-panel-header');
        const left = header?.firstElementChild;
        const subtitle = left?.querySelector('.driver-panel-subtitle');
        if (!header || !left || !subtitle) return;

        if (left.querySelector('.driver-delivery-legend-copy')) return;

        const shopLegend = findShopLegend();
        let legend;

        if (shopLegend) {
            legend = shopLegend.cloneNode(true);
            legend.classList.add('driver-delivery-legend-copy');
        } else {
            legend = document.createElement('div');
            legend.className = 'driver-delivery-legend-copy';
            legend.innerHTML = '<span>Unpaid</span><span>Paid</span><span>Due</span><span>Overpaid</span>';
        }

        /* Keep the legend in the left/header text column, immediately
           below the Driver subtitle and above the increment controls. */
        subtitle.insertAdjacentElement('afterend', legend);
    }

    function injectStyles() {
        if (document.getElementById('marcid-blue-delivery-ui-fixes-style')) return;

        const style = document.createElement('style');
        style.id = 'marcid-blue-delivery-ui-fixes-style';
        style.textContent = `
            /* Match the existing action-button hover behavior. */
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
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 12px;
                margin-top: 8px;
                margin-left: 0;
                color: var(--text-muted);
                font-size: 12px;
                line-height: 1.4;
            }

            .driver-delivery-legend-copy span {
                display: inline-flex;
                align-items: center;
                white-space: nowrap;
            }
        `;
        document.head.appendChild(style);
    }

    function init() {
        injectStyles();
        cleanDeliveryUI();
        addDriverLegend();

        const observer = new MutationObserver(function () {
            cleanDeliveryUI();
            addDriverLegend();
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
