(function () {
    function init() {
        const card = document.getElementById('driverDeliveriesPanel');
        if (!card || card.dataset.layoutReady === '1') return;
        card.dataset.layoutReady = '1';

        const summary = card.querySelector('.driver-deliveries-layout');
        const quantity = document.getElementById('driver_total_delivery_quantity');
        if (!summary || !quantity) return;

        card.querySelectorAll('.driver-delivery-note, .driver-delivery-quantity-note').forEach(function (note) {
            note.remove();
        });

        const salesGroup = document.createElement('div');
        salesGroup.className = 'driver-summary-field';
        salesGroup.innerHTML = '<div class="summary-label">Total Sales for Deliver</div><div class="summary-value" id="driverTotalDeliverySales">₱0.00</div>';

        const expectedGroup = document.createElement('div');
        expectedGroup.className = 'driver-summary-field';
        expectedGroup.innerHTML = '<div class="summary-label">Total Expected Money for Delivery</div><div class="summary-value" id="driverTotalExpectedMoney">₱0.00</div>';

        const statusGroup = document.createElement('div');
        statusGroup.className = 'driver-summary-field driver-remittance-status-field';
        statusGroup.innerHTML = '<div class="summary-label">Driver Remittance Status</div><div class="summary-value driver-remittance-status" id="driverRemittanceStatus">—</div>';

        summary.appendChild(salesGroup);
        summary.appendChild(expectedGroup);
        summary.appendChild(statusGroup);

        const actions = document.createElement('div');
        actions.className = 'driver-actions';
        actions.innerHTML = '<button type="button" class="btn btn-secondary" id="driverBalanceButton">Balance</button>';
        card.querySelector('.card-body').appendChild(actions);

        const style = document.createElement('style');
        style.id = 'marcid-blue-driver-deliveries-layout-style';
        style.textContent = `
            .driver-deliveries-layout { grid-template-columns: repeat(5, minmax(0, 1fr)); align-items: end; }
            .driver-money-field, .driver-total-quantity, .driver-summary-field { min-width: 0; }
            .driver-total-quantity, .driver-summary-field { padding: 8px 0 4px 8px; }
            .driver-summary-field .summary-value, .driver-remittance-status { font-size: 18px; font-weight: 700; line-height: 1.4; }
            .driver-remittance-status { white-space: nowrap; }
            .driver-remittance-balanced { color: var(--success); }
            .driver-remittance-short { color: var(--danger); }
            .driver-remittance-over { color: var(--primary-dark); }
            .driver-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border); }
            .driver-actions .btn { min-width: 150px; }
            .dashboard-net-profit-value { display: inline-flex; align-items: center; gap: 10px; }
            .dashboard-net-profit-eye { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: transparent; color: var(--text-muted); font-size: 17px; line-height: 1; cursor: pointer; }
            .dashboard-net-profit-eye:hover { background: var(--background); color: var(--text); }
            .dashboard-net-profit-hidden { letter-spacing: 2px; }
            .dashboard-total-delivery-quantity-value { font-size: 18px; font-weight: 700; line-height: 1.4; }
            .daily-status-finalize-outside { width: 100%; margin: 0 0 10px 0; }
            .daily-status-finalize-outside .btn { width: 100%; }
            @media (max-width: 1250px) { .driver-deliveries-layout { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (max-width: 900px) { .driver-deliveries-layout { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 650px) { .driver-deliveries-layout { grid-template-columns: 1fr; } .driver-actions { flex-direction: column; } .driver-actions .btn { width: 100%; } }
        `;
        document.head.appendChild(style);

        const moneyInput = document.getElementById('driver_money_received');
        const salesOutput = document.getElementById('driverTotalDeliverySales');
        const expectedOutput = document.getElementById('driverTotalExpectedMoney');
        const statusOutput = document.getElementById('driverRemittanceStatus');
        const balanceButton = document.getElementById('driverBalanceButton');

        function number(value) {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function currency(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

        function updateDashboardSummary() {
            const totalQuantity = getTotalDeliveryQuantity();
            const quantityOutput = document.getElementById('dashboardTotalDeliveryQuantity');
            if (quantityOutput) quantityOutput.textContent = String(totalQuantity);

            const shopMoney = number(document.getElementById('walk_in_money')?.value);
            const driverMoney = number(moneyInput?.value);
            const netProfit = shopMoney + driverMoney;
            const netProfitValue = document.getElementById('dashboardNetProfit');
            if (netProfitValue) {
                netProfitValue.dataset.amount = String(netProfit);
                if (netProfitValue.dataset.revealed === '1') netProfitValue.textContent = currency(netProfit);
            }
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

        function setupDashboard() {
            const shopSales = document.querySelectorAll('.summary-card');
            shopSales.forEach(function (summaryCard) {
                const label = summaryCard.querySelector('.summary-label');
                if (!label) return;

                if (label.textContent.trim() === 'Shop Sales') {
                    label.textContent = 'Net Profit for Today';
                    const value = summaryCard.querySelector('.summary-value');
                    const description = summaryCard.querySelector('.summary-description');
                    if (description) description.textContent = 'Total money received from Shop and Driver';

                    if (value && !document.getElementById('dashboardNetProfitEye')) {
                        value.id = 'dashboardNetProfit';
                        value.className = 'summary-value dashboard-net-profit-hidden';
                        value.dataset.revealed = '0';
                        value.dataset.amount = '0';
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
                            value.dataset.revealed = revealed ? '0' : '1';
                            value.classList.toggle('dashboard-net-profit-hidden', revealed);
                            value.textContent = revealed ? '••••••' : currency(number(value.dataset.amount));
                            eye.textContent = revealed ? '◉' : '◎';
                        });
                    }
                }

                if (label.textContent.trim() === 'Shop Customers') {
                    label.textContent = 'Total Walk-in for Today';
                    const description = summaryCard.querySelector('.summary-description');
                    if (description) description.textContent = '';
                }

                if (label.textContent.trim() === 'Price per Customer') {
                    label.textContent = 'Total Deliveries for Today';
                    const value = summaryCard.querySelector('.summary-value');
                    const description = summaryCard.querySelector('.summary-description');
                    if (value) {
                        value.id = 'dashboardTotalDeliveryQuantity';
                        value.className = 'summary-value dashboard-total-delivery-quantity-value';
                    }
                    if (description) description.textContent = '';
                }
            });

            const dailyStatusCard = Array.from(document.querySelectorAll('.summary-card')).find(function (summaryCard) {
                return summaryCard.querySelector('.summary-label')?.textContent.trim() === 'Daily Status';
            });
            if (dailyStatusCard) {
                let button = document.getElementById('driverCloseDayButton');
                if (button) {
                    const oldWrap = button.closest('.daily-status-finalize, .daily-status-finalize-top, .daily-status-finalize-outside');
                    if (oldWrap) oldWrap.remove();
                }
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-primary';
                button.id = 'driverCloseDayButton';
                button.textContent = 'Finalize & Close Day';

                const wrapper = document.createElement('div');
                wrapper.className = 'daily-status-finalize-outside';
                wrapper.appendChild(button);

                dailyStatusCard.parentNode.insertBefore(wrapper, dailyStatusCard);

                button.addEventListener('click', function () {
                    const values = balance();
                    const dailyId = document.querySelector('#shopWalkInForm')?.dataset.dailyId || '';
                    if (!dailyId) {
                        alert('No open daily record was found.');
                        return;
                    }
                    if (!window.confirm('Finalize and close this day? This will lock the daily record until it is reopened from Daily Records.')) return;

                    button.disabled = true;
                    balanceButton.disabled = true;
                    const body = new URLSearchParams();
                    body.set('daily_id', dailyId);
                    body.set('driver_money_received', String(values.moneyReceived));
                    body.set('driver_expenses', String(values.driverExpenses));
                    body.set('driver_sales', String(values.totalSales));
                    body.set('expected_sales', String(values.expected));
                    body.set('remittance_difference', String(values.totalSales - values.expected));

                    fetch('close-daily-record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
                        body: body.toString()
                    }).then(function (response) {
                        return response.json().then(function (result) {
                            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to close the day.');
                            return result;
                        });
                    }).then(function () {
                        alert('Day finalized and closed successfully.');
                        window.location.reload();
                    }).catch(function (error) {
                        console.error(error);
                        alert(error.message || 'Unable to close the day.');
                        button.disabled = false;
                        balanceButton.disabled = false;
                    });
                });
            }

            updateDashboardSummary();
        }

        card.addEventListener('input', function () { resetStatus(); calculate(); });
        card.addEventListener('change', function () { resetStatus(); calculate(); });
        document.getElementById('deliveryPaymentRows')?.addEventListener('input', function () { resetStatus(); calculate(); });
        document.getElementById('deliveryPaymentRows')?.addEventListener('change', function () { resetStatus(); calculate(); });
        document.getElementById('walk_in_money')?.addEventListener('input', updateDashboardSummary);
        document.getElementById('walk_in_money')?.addEventListener('change', updateDashboardSummary);
        balanceButton.addEventListener('click', balance);

        setupDashboard();
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
