(function () {
    function money(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function findCardByLabel(text) {
        const labels = document.querySelectorAll('.summary-label');
        for (const label of labels) {
            if (label.textContent.trim() === text) {
                return label.closest('.summary-card') || label.parentElement;
            }
        }
        return null;
    }

    function findCardContainingLabel(text) {
        const labels = document.querySelectorAll('.summary-label');
        for (const label of labels) {
            if (label.textContent.trim().includes(text)) {
                return label.closest('.summary-card') || label.parentElement;
            }
        }
        return null;
    }

    function getTotalDeliveries() {
        let total = 0;
        document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(function (row) {
            total += Number.parseInt(row.querySelector('.delivery-slim')?.value || 0, 10) || 0;
            total += Number.parseInt(row.querySelector('.delivery-round')?.value || 0, 10) || 0;
        });

        const driverPanel = document.getElementById('driverDeliveriesPanel');
        if (driverPanel) {
            driverPanel.querySelectorAll('.driver-delivery-row').forEach(function (row) {
                total += Number.parseInt(row.querySelector('.driver-delivery-slim')?.value || 0, 10) || 0;
                total += Number.parseInt(row.querySelector('.driver-delivery-round')?.value || 0, 10) || 0;
            });
        }
        return Math.max(0, total);
    }

    function getDailyStatusCard() {
        return findCardByLabel('Daily Status') || findCardContainingLabel('Daily Status');
    }

    function ensureFinalizeButton() {
        const card = getDailyStatusCard();
        if (!card) return;

        let button = document.getElementById('driverCloseDayButton');
        if (!button) {
            const wrapper = document.createElement('div');
            wrapper.className = 'daily-status-finalize-top';
            wrapper.innerHTML = '<button type="button" class="btn btn-primary" id="driverCloseDayButton">Finalize &amp; Close Day</button>';
            card.insertBefore(wrapper, card.firstChild);
            button = wrapper.querySelector('#driverCloseDayButton');
        } else {
            const wrapper = button.closest('.daily-status-finalize, .daily-status-finalize-top');
            if (wrapper) card.insertBefore(wrapper, card.firstChild);
        }

        if (button && button.dataset.uiFixBound !== '1') {
            button.dataset.uiFixBound = '1';
            button.addEventListener('click', function () {
                const balanceButton = document.getElementById('driverBalanceButton');
                const status = document.getElementById('driverRemittanceStatus');
                if (balanceButton) balanceButton.click();

                const form = document.getElementById('shopWalkInForm');
                const dailyId = form?.dataset.dailyId || '';
                if (!dailyId) {
                    alert('No open daily record was found.');
                    return;
                }

                const confirmed = window.confirm(
                    'Finalize and close this day? This will lock the daily record until it is reopened from Daily Records.'
                );
                if (!confirmed) return;

                const driverMoney = Number.parseFloat(document.getElementById('driver_money_received')?.value || 0) || 0;
                let driverExpenses = 0;
                document.querySelectorAll('#driverDeliveriesPanel .driver-expense-amount').forEach(function (input) {
                    driverExpenses += Number.parseFloat(input.value || 0) || 0;
                });

                let shopPayments = 0;
                document.querySelectorAll('#deliveryPaymentRows .delivery-payment').forEach(function (input) {
                    shopPayments += Number.parseFloat(input.value || 0) || 0;
                });

                let driverPayments = 0;
                document.querySelectorAll('#driverDeliveriesPanel .driver-delivery-payment').forEach(function (input) {
                    driverPayments += Number.parseFloat(input.value || 0) || 0;
                });

                const driverSales = driverMoney + driverExpenses;
                const expectedSales = shopPayments + driverPayments;
                const difference = driverSales - expectedSales;

                button.disabled = true;
                if (balanceButton) balanceButton.disabled = true;

                const body = new URLSearchParams();
                body.set('daily_id', dailyId);
                body.set('driver_money_received', String(driverMoney));
                body.set('driver_expenses', String(driverExpenses));
                body.set('driver_sales', String(driverSales));
                body.set('expected_sales', String(expectedSales));
                body.set('remittance_difference', String(difference));

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
                        button.disabled = false;
                        if (balanceButton) balanceButton.disabled = false;
                    });
            });
        }
    }

    function fixDashboardCards() {
        const netProfitCard = findCardByLabel('Net Profit for Today') || findCardByLabel('Shop Sales');
        if (netProfitCard) {
            const description = netProfitCard.querySelector('.summary-description');
            if (description) description.textContent = 'Total money received from Shop and Driver';
        }

        const customersCard = findCardByLabel('Shop Customers');
        if (customersCard) {
            const label = customersCard.querySelector('.summary-label');
            const description = customersCard.querySelector('.summary-description');
            if (label) label.textContent = 'Total Walk-in for Today';
            if (description) description.textContent = '';
        }

        const deliveriesCard = findCardByLabel('Total Quantity of Deliveries') || findCardByLabel('Price per Customer');
        if (deliveriesCard) {
            const label = deliveriesCard.querySelector('.summary-label');
            const value = deliveriesCard.querySelector('.summary-value');
            const description = deliveriesCard.querySelector('.summary-description');
            if (label) label.textContent = 'Total Deliveries for Today';
            if (value) {
                value.id = 'dashboardTotalDeliveryQuantity';
                value.className = 'summary-value dashboard-total-delivery-quantity-value';
                value.textContent = String(getTotalDeliveries());
            }
            if (description) description.textContent = '';
        }

        const netProfitValue = document.getElementById('dashboardNetProfit');
        if (netProfitValue) {
            const eye = document.getElementById('dashboardNetProfitEye');
            if (eye) {
                eye.innerHTML = '<span class="dashboard-eye-icon" aria-hidden="true"></span>';
                eye.setAttribute('aria-label', 'Show Net Profit for Today');
                eye.setAttribute('title', 'Show Net Profit for Today');
            }
        }
    }

    function apply() {
        fixDashboardCards();
        ensureFinalizeButton();

        const quantity = document.getElementById('dashboardTotalDeliveryQuantity');
        if (quantity) quantity.textContent = String(getTotalDeliveries());
    }

    const style = document.createElement('style');
    style.textContent = `
        .dashboard-eye-icon {
            position: relative;
            display: block;
            width: 17px;
            height: 11px;
            border: 2px solid currentColor;
            border-radius: 80% 20% / 70% 30%;
            transform: rotate(-45deg);
        }

        .dashboard-eye-icon::after {
            content: '';
            position: absolute;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            top: 1px;
            left: 4px;
        }

        .dashboard-net-profit-eye {
            flex: 0 0 32px;
        }

        .dashboard-total-delivery-quantity-value,
        #dashboardShopCustomers {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }

        .daily-status-finalize-top {
            width: 100%;
            margin: 0 0 16px 0;
        }

        .daily-status-finalize-top .btn {
            width: 100%;
        }
    `;
    document.head.appendChild(style);

    function start() {
        apply();
        const observer = new MutationObserver(function () {
            apply();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('input', function () {
            const quantity = document.getElementById('dashboardTotalDeliveryQuantity');
            if (quantity) quantity.textContent = String(getTotalDeliveries());
        });
        document.addEventListener('change', function () {
            const quantity = document.getElementById('dashboardTotalDeliveryQuantity');
            if (quantity) quantity.textContent = String(getTotalDeliveries());
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
