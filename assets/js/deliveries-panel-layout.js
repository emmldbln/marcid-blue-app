(function () {
    function init() {
        const card = document.getElementById('driverDeliveriesPanel');
        if (!card || card.dataset.layoutReady === '1') return;
        card.dataset.layoutReady = '1';

        const summary = card.querySelector('.driver-deliveries-layout');
        const quantity = document.getElementById('driver_total_delivery_quantity');
        if (!summary || !quantity) return;

        const quantityGroup = quantity.closest('.driver-total-quantity');
        if (quantityGroup) {
            const quantityNote = quantityGroup.querySelector('.driver-delivery-quantity-note');
            if (quantityNote) quantityNote.remove();
        }

        card.querySelectorAll('.driver-delivery-note').forEach(function (note) {
            note.remove();
        });

        const salesGroup = document.createElement('div');
        salesGroup.className = 'driver-summary-field';
        salesGroup.innerHTML = `
            <div class="summary-label">Total Sales for Deliver</div>
            <div class="summary-value" id="driverTotalDeliverySales">₱0.00</div>
        `;

        const expectedGroup = document.createElement('div');
        expectedGroup.className = 'driver-summary-field';
        expectedGroup.innerHTML = `
            <div class="summary-label">Total Expected Money for Delivery</div>
            <div class="summary-value" id="driverTotalExpectedMoney">₱0.00</div>
        `;

        const statusGroup = document.createElement('div');
        statusGroup.className = 'driver-summary-field driver-remittance-status-field';
        statusGroup.innerHTML = `
            <div class="summary-label">Driver Remittance Status</div>
            <div class="summary-value driver-remittance-status" id="driverRemittanceStatus">—</div>
        `;

        summary.appendChild(salesGroup);
        summary.appendChild(expectedGroup);
        summary.appendChild(statusGroup);

        const actions = document.createElement('div');
        actions.className = 'driver-actions';
        actions.innerHTML = `
            <button type="button" class="btn btn-secondary" id="driverBalanceButton">Balance</button>
        `;
        card.querySelector('.card-body').appendChild(actions);

        const style = document.createElement('style');
        style.id = 'marcid-blue-driver-deliveries-layout-style';
        style.textContent = `
            .driver-deliveries-layout {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                align-items: end;
            }

            .driver-money-field,
            .driver-total-quantity,
            .driver-summary-field {
                min-width: 0;
            }

            .driver-total-quantity,
            .driver-summary-field {
                padding: 8px 0 4px 8px;
            }

            .driver-summary-field .summary-value,
            .driver-remittance-status {
                font-size: 18px;
                font-weight: 700;
                line-height: 1.4;
            }

            .driver-remittance-status {
                white-space: nowrap;
            }

            .driver-remittance-balanced { color: var(--success); }
            .driver-remittance-short { color: var(--danger); }
            .driver-remittance-over { color: var(--primary-dark); }

            .driver-actions {
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                margin-top: 28px;
                padding-top: 20px;
                border-top: 1px solid var(--border);
            }

            .driver-actions .btn {
                min-width: 150px;
            }

            /* Dashboard summary updates */
            .dashboard-net-profit-value {
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }

            .dashboard-net-profit-eye {
                width: 32px;
                height: 32px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 0;
                border-radius: 50%;
                background: transparent;
                color: var(--text-muted);
                font-size: 17px;
                line-height: 1;
                cursor: pointer;
            }

            .dashboard-net-profit-eye:hover {
                background: var(--background);
                color: var(--text);
            }

            .dashboard-net-profit-hidden {
                letter-spacing: 2px;
            }

            .dashboard-total-delivery-quantity {
                display: flex;
                align-items: baseline;
                gap: 8px;
            }

            .dashboard-total-delivery-quantity .summary-value {
                font-size: 24px;
                font-weight: 700;
                line-height: 1.4;
            }

            /* Put finalization at the top of the Daily Status card. */
            .daily-status-summary-card {
                position: relative;
            }

            .daily-status-finalize {
                width: 100%;
                margin-bottom: 14px;
            }

            .daily-status-finalize .btn {
                width: 100%;
            }

            @media (max-width: 1250px) {
                .driver-deliveries-layout { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            }

            @media (max-width: 900px) {
                .driver-deliveries-layout { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 650px) {
                .driver-deliveries-layout { grid-template-columns: 1fr; }
                .driver-actions { flex-direction: column; }
                .driver-actions .btn { width: 100%; }
            }
        `;
        document.head.appendChild(style);

        const moneyInput = document.getElementById('driver_money_received');
        const salesOutput = document.getElementById('driverTotalDeliverySales');
        const expectedOutput = document.getElementById('driverTotalExpectedMoney');
        const statusOutput = document.getElementById('driverRemittanceStatus');
        const balanceButton = document.getElementById('driverBalanceButton');
        const dailyId = document.querySelector('#shopWalkInForm')?.dataset.dailyId || '';

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

        function getDriverExpenses() {
            let total = 0;
            card.querySelectorAll('.driver-expense-row').forEach(function (row) {
                total += number(row.querySelector('.driver-expense-amount')?.value);
            });
            return total;
        }

        function getShopPayments() {
            let total = 0;
            document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(function (row) {
                total += number(row.querySelector('.delivery-payment')?.value);
            });
            return total;
        }

        function getDriverPayments() {
            let total = 0;
            card.querySelectorAll('.driver-delivery-row').forEach(function (row) {
                total += number(row.querySelector('.driver-delivery-payment')?.value);
            });
            return total;
        }

        function getShopMoneyReceived() {
            return number(document.getElementById('walk_in_money')?.value);
        }

        function getTotalDeliveryQuantity() {
            let total = 0;

            document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(function (row) {
                total += number(row.querySelector('.delivery-slim')?.value);
                total += number(row.querySelector('.delivery-round')?.value);
            });

            card.querySelectorAll('.driver-delivery-row').forEach(function (row) {
                total += number(row.querySelector('.driver-delivery-slim')?.value);
                total += number(row.querySelector('.driver-delivery-round')?.value);
            });

            return Math.max(0, Math.trunc(total));
        }

        function calculate() {
            const moneyReceived = number(moneyInput?.value);
            const driverExpenses = getDriverExpenses();
            const totalSales = moneyReceived + driverExpenses;
            const expected = getShopPayments() + getDriverPayments();

            salesOutput.textContent = currency(totalSales);
            expectedOutput.textContent = currency(expected);
            updateDashboardSummary();

            return { moneyReceived, driverExpenses, totalSales, expected };
        }

        function resetStatus() {
            if (!statusOutput) return;
            statusOutput.className = 'summary-value driver-remittance-status';
            statusOutput.textContent = '—';
        }

        function balance() {
            const values = calculate();
            const difference = values.totalSales - values.expected;
            statusOutput.className = 'summary-value driver-remittance-status';

            if (Math.abs(difference) <= 0.005) {
                statusOutput.classList.add('driver-remittance-balanced');
                statusOutput.textContent = 'Balanced';
            } else if (difference < 0) {
                statusOutput.classList.add('driver-remittance-short');
                statusOutput.textContent = 'Short of ' + currency(Math.abs(difference));
            } else {
                statusOutput.classList.add('driver-remittance-over');
                statusOutput.textContent = 'Over of ' + currency(difference);
            }

            return values;
        }

        function findSummaryCard(labelText) {
            const cards = document.querySelectorAll('.summary-card');
            for (const summaryCard of cards) {
                const label = summaryCard.querySelector('.summary-label');
                if (label && label.textContent.trim() === labelText) {
                    return summaryCard;
                }
            }
            return null;
        }

        function updateDashboardSummary() {
            const totalQuantity = getTotalDeliveryQuantity();
            const quantityOutput = document.getElementById('dashboardTotalDeliveryQuantity');
            if (quantityOutput) {
                quantityOutput.textContent = totalQuantity.toLocaleString('en-PH');
            }

            const shopMoneyReceived = getShopMoneyReceived();
            const driverMoneyReceived = number(moneyInput?.value);
            const netProfit = shopMoneyReceived + driverMoneyReceived;
            const netProfitValue = document.getElementById('dashboardNetProfit');

            if (netProfitValue) {
                netProfitValue.dataset.amount = String(netProfit);
                if (netProfitValue.dataset.revealed === '1') {
                    netProfitValue.textContent = currency(netProfit);
                }
            }
        }

        function setupDashboardSummary() {
            const oldShopSalesCard = findSummaryCard('Shop Sales');
            if (oldShopSalesCard) {
                oldShopSalesCard.querySelector('.summary-label').textContent = 'Net Profit for Today';
                const value = oldShopSalesCard.querySelector('.summary-value');
                const description = oldShopSalesCard.querySelector('.summary-description');

                if (value) {
                    value.id = 'dashboardNetProfit';
                    value.className = 'summary-value dashboard-net-profit-hidden';
                    value.dataset.amount = '0';
                    value.dataset.revealed = '0';
                    value.textContent = '••••••';

                    const eye = document.createElement('button');
                    eye.type = 'button';
                    eye.className = 'dashboard-net-profit-eye';
                    eye.id = 'dashboardNetProfitEye';
                    eye.setAttribute('aria-label', 'Show Net Profit for Today');
                    eye.setAttribute('title', 'Show Net Profit for Today');
                    eye.textContent = '◉';

                    const wrapper = document.createElement('span');
                    wrapper.className = 'dashboard-net-profit-value';
                    value.parentNode.insertBefore(wrapper, value);
                    wrapper.appendChild(value);
                    wrapper.appendChild(eye);

                    eye.addEventListener('click', function () {
                        const revealed = value.dataset.revealed === '1';
                        if (revealed) {
                            value.dataset.revealed = '0';
                            value.classList.add('dashboard-net-profit-hidden');
                            value.textContent = '••••••';
                            eye.textContent = '◉';
                            eye.setAttribute('aria-label', 'Show Net Profit for Today');
                            eye.setAttribute('title', 'Show Net Profit for Today');
                        } else {
                            value.dataset.revealed = '1';
                            value.classList.remove('dashboard-net-profit-hidden');
                            value.textContent = currency(number(value.dataset.amount));
                            eye.textContent = '◎';
                            eye.setAttribute('aria-label', 'Hide Net Profit for Today');
                            eye.setAttribute('title', 'Hide Net Profit for Today');
                        }
                    });
                }

                if (description) {
                    description.textContent = 'Total money received from Shop and Driver';
                }
            }

            const oldPriceCard = findSummaryCard('Price per Customer');
            if (oldPriceCard) {
                const label = oldPriceCard.querySelector('.summary-label');
                const value = oldPriceCard.querySelector('.summary-value');
                const description = oldPriceCard.querySelector('.summary-description');

                if (label) label.textContent = 'Total Quantity of Deliveries';
                if (value) {
                    value.id = 'dashboardTotalDeliveryQuantity';
                    value.textContent = '0';
                }
                if (description) {
                    description.textContent = 'Total Slim + Round deliveries for today';
                }
                oldPriceCard.classList.add('dashboard-total-delivery-quantity');
            }

            const dailyStatusCard = findSummaryCard('Daily Status');
            if (dailyStatusCard) {
                dailyStatusCard.classList.add('daily-status-summary-card');

                const existingFinalize = document.getElementById('driverCloseDayButton');
                if (existingFinalize) {
                    const finalizeWrap = document.createElement('div');
                    finalizeWrap.className = 'daily-status-finalize';
                    finalizeWrap.appendChild(existingFinalize);
                    dailyStatusCard.insertBefore(finalizeWrap, dailyStatusCard.firstChild);
                }
            }

            updateDashboardSummary();
        }

        card.addEventListener('input', function () {
            resetStatus();
            calculate();
        });
        card.addEventListener('change', function () {
            resetStatus();
            calculate();
        });
        document.getElementById('deliveryPaymentRows')?.addEventListener('input', function () {
            resetStatus();
            calculate();
        });
        document.getElementById('deliveryPaymentRows')?.addEventListener('change', function () {
            resetStatus();
            calculate();
        });
        document.getElementById('walk_in_money')?.addEventListener('input', updateDashboardSummary);
        document.getElementById('walk_in_money')?.addEventListener('change', updateDashboardSummary);

        balanceButton.addEventListener('click', balance);

        setupDashboardSummary();
        calculate();
    }

    if (document.getElementById('driverDeliveriesPanel')) {
        init();
    } else {
        const observer = new MutationObserver(function () {
            if (document.getElementById('driverDeliveriesPanel')) {
                observer.disconnect();
                init();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
