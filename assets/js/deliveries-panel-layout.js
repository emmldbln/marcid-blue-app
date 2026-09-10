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
            <button type="button" class="btn btn-primary" id="driverCloseDayButton">Finalize &amp; Close Day</button>
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
        const closeButton = document.getElementById('driverCloseDayButton');
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

        function calculate() {
            const moneyReceived = number(moneyInput?.value);
            const driverExpenses = getDriverExpenses();
            const totalSales = moneyReceived + driverExpenses;
            const expected = getShopPayments() + getDriverPayments();

            salesOutput.textContent = currency(totalSales);
            expectedOutput.textContent = currency(expected);

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

        balanceButton.addEventListener('click', balance);

        closeButton.addEventListener('click', function () {
            const values = balance();
            if (!dailyId) {
                alert('No open daily record was found.');
                return;
            }

            const confirmed = window.confirm(
                'Finalize and close this day? This will lock the daily record until it is reopened from Daily Records.'
            );
            if (!confirmed) return;

            closeButton.disabled = true;
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    Accept: 'application/json'
                },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().then(function (result) {
                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Unable to close the day.');
                        }
                        return result;
                    });
                })
                .then(function () {
                    alert('Day finalized and closed successfully.');
                    window.location.reload();
                })
                .catch(function (error) {
                    console.error(error);
                    alert(error.message || 'Unable to close the day.');
                    closeButton.disabled = false;
                    balanceButton.disabled = false;
                });
        });

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
