(function () {
    function init() {
        const card = document.getElementById('driverDeliveriesPanel');
        const statusOutput = document.getElementById('driverRemittanceStatus');
        if (!card || !statusOutput) return;

        function number(value) {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function currency(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getDriverMoney() {
            return number(document.getElementById('driver_money_received')?.value);
        }

        function getDriverExpenses() {
            let total = 0;
            card.querySelectorAll('.driver-expense-row').forEach(function (row) {
                total += number(row.querySelector('.driver-expense-amount')?.value);
            });
            return total;
        }

        function getShopDeliveryPayments() {
            let total = 0;
            document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(function (row) {
                total += number(row.querySelector('.delivery-payment')?.value);
            });
            return total;
        }

        function getDriverDeliveryPayments() {
            let total = 0;
            card.querySelectorAll('.driver-delivery-row').forEach(function (row) {
                total += number(row.querySelector('.driver-delivery-payment')?.value);
            });
            return total;
        }

        function updateRemittanceStatus() {
            const driverMoney = getDriverMoney();
            const driverExpenses = getDriverExpenses();
            const shopDeliveryPayments = getShopDeliveryPayments();
            const driverDeliveryPayments = getDriverDeliveryPayments();

            // Shop delivery payments were already collected by the Station.
            // They count toward the driver's effective received amount only
            // for remittance balancing because they are also expected money.
            const effectiveReceived = driverMoney + driverExpenses + shopDeliveryPayments;
            const expected = shopDeliveryPayments + driverDeliveryPayments;
            const difference = effectiveReceived - expected;

            statusOutput.className = 'summary-value driver-remittance-status';

            // After Reset / on a completely empty day, there is no remittance
            // to balance. Do not display "Balanced" for 0 vs 0.
            if (Math.abs(effectiveReceived) <= 0.005 && Math.abs(expected) <= 0.005) {
                statusOutput.classList.add('driver-remittance-neutral');
                statusOutput.textContent = '—';
            } else if (Math.abs(difference) <= 0.005) {
                statusOutput.classList.add('driver-remittance-balanced');
                statusOutput.textContent = 'Balanced';
            } else if (difference < 0) {
                statusOutput.classList.add('driver-remittance-short');
                statusOutput.textContent = 'Short of ' + currency(Math.abs(difference));
            } else {
                statusOutput.classList.add('driver-remittance-over');
                statusOutput.textContent = 'Over of ' + currency(difference);
            }
        }

        function scheduleUpdate() {
            window.requestAnimationFrame(updateRemittanceStatus);
        }

        document.getElementById('driver_money_received')?.addEventListener('input', scheduleUpdate);
        document.getElementById('driver_money_received')?.addEventListener('change', scheduleUpdate);
        card.addEventListener('input', scheduleUpdate);
        card.addEventListener('change', scheduleUpdate);
        document.getElementById('deliveryPaymentRows')?.addEventListener('input', scheduleUpdate);
        document.getElementById('deliveryPaymentRows')?.addEventListener('change', scheduleUpdate);

        const driverRows = document.getElementById('driverDeliveryPaymentRows');
        if (driverRows) {
            new MutationObserver(scheduleUpdate).observe(driverRows, {
                childList: true,
                subtree: true
            });
        }

        const shopRows = document.getElementById('deliveryPaymentRows');
        if (shopRows) {
            new MutationObserver(scheduleUpdate).observe(shopRows, {
                childList: true,
                subtree: true
            });
        }

        document.getElementById('driverBalanceButton')?.addEventListener('click', scheduleUpdate);

        scheduleUpdate();
        window.setTimeout(scheduleUpdate, 100);
        window.setTimeout(scheduleUpdate, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
