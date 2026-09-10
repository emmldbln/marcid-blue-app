(function () {
    function init() {
        const form = document.getElementById('shopWalkInForm');
        if (!form || form.dataset.mysqlAutosaveReady === '1') return;
        form.dataset.mysqlAutosaveReady = '1';

        const driverPanel = document.getElementById('driverDeliveriesPanel');
        const actions = form.querySelector('.shop-actions');
        let status = document.getElementById('shopDraftStatus');
        if (!status && actions) {
            status = document.createElement('div');
            status.id = 'shopDraftStatus';
            status.className = 'shop-draft-status';
            actions.parentNode.insertBefore(status, actions.nextSibling);
        }

        let dailyId = '';
        let timer = null;
        let saving = false;
        let queued = false;
        let restoring = false;
        let initialized = false;

        function setStatus(text, error = false) {
            if (!status) return;
            status.textContent = text;
            status.style.color = error ? 'var(--danger)' : 'var(--text-muted)';
        }

        function rows(selector, root = document) {
            return Array.from(root.querySelectorAll(selector));
        }

        function collect() {
            return {
                walk_in_money: document.getElementById('walk_in_money')?.value || '',
                expenses: rows('#expenseRows .expense-row').map(row => ({
                    category: row.querySelector('.expense-category')?.value || '',
                    name: row.querySelector('.expense-name')?.value || '',
                    amount: row.querySelector('.expense-amount')?.value || ''
                })),
                deliveries: rows('#deliveryPaymentRows .delivery-payment-row').map(row => ({
                    customer: row.querySelector('.delivery-customer')?.value || '',
                    slim: row.querySelector('.delivery-slim')?.value || '',
                    round: row.querySelector('.delivery-round')?.value || '',
                    payment: row.querySelector('.delivery-payment')?.value || '',
                    price: row.querySelector('.delivery-price-input')?.value || '',
                    method: row.querySelector('.delivery-method')?.value || 'Cash'
                })),
                driver: {
                    money_received: document.getElementById('driver_money_received')?.value || '',
                    expenses: rows('.driver-expense-row', driverPanel || document).map(row => ({
                        category: row.querySelector('.driver-expense-category')?.value || '',
                        name: row.querySelector('.driver-expense-name')?.value || '',
                        amount: row.querySelector('.driver-expense-amount')?.value || ''
                    })),
                    deliveries: rows('.driver-delivery-row', driverPanel || document).map(row => ({
                        customer: row.querySelector('.driver-delivery-customer')?.value || '',
                        slim: row.querySelector('.driver-delivery-slim')?.value || '',
                        round: row.querySelector('.driver-delivery-round')?.value || '',
                        payment: row.querySelector('.driver-delivery-payment')?.value || '',
                        price: row.querySelector('.driver-delivery-price')?.value || '',
                        method: row.querySelector('.driver-delivery-method')?.value || 'Cash'
                    }))
                }
            };
        }

        function buildShopExpense() {
            const row = document.createElement('div');
            row.className = 'expense-row';
            row.innerHTML = '<div class="form-group"><label class="form-label">Expense</label><select name="expense_category[]" class="form-input expense-category"><option value="">No Expense</option><option value="Food">Food</option><option value="Gas">Gas</option><option value="Cash Advance">Cash Advance</option><option value="Others">Others</option></select></div><div class="form-group"><label class="form-label">Amount</label><input type="number" name="expense_amount[]" class="form-input expense-amount" min="0" step="0.01"></div><div class="form-group expense-name-group"><label class="form-label expense-name-label">Name / Description</label><input type="text" name="expense_name[]" class="form-input expense-name" maxlength="255" disabled></div><button type="button" class="expense-remove">×</button>';
            return row;
        }

        function buildShopDelivery() {
            const row = document.createElement('div');
            row.className = 'delivery-payment-row';
            row.innerHTML = '<div class="form-group delivery-customer-group"><label class="form-label">Customer</label><input type="text" name="delivery_customer[]" class="form-input delivery-customer" list="shopDeliveryCustomerList" maxlength="100" autocomplete="off"></div><div class="form-group"><label class="form-label">Slim</label><input type="number" name="delivery_slim[]" class="form-input delivery-slim" min="0" step="1"></div><div class="form-group"><label class="form-label">Round</label><input type="number" name="delivery_round[]" class="form-input delivery-round" min="0" step="1"></div><div class="form-group delivery-payment-group"><label class="form-label">Payment</label><input type="number" name="delivery_payment[]" class="form-input delivery-payment" min="0" step="0.01"></div><div class="form-group delivery-price-group"><label class="form-label">Price/Gal</label><input type="number" name="delivery_price_per_gallon[]" class="form-input delivery-price-input" min="0" step="0.01"></div><div class="form-group"><label class="form-label">Method</label><select name="delivery_method[]" class="form-input delivery-method"><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div><div class="form-group delivery-balance-group"><label class="form-label">Balance</label><div class="delivery-balance delivery-balance-neutral">—</div></div><button type="button" class="delivery-payment-remove">×</button>';
            return row;
        }

        function buildDriverExpense() {
            const row = document.createElement('div');
            row.className = 'driver-expense-row';
            row.innerHTML = '<div class="form-group"><select class="form-input driver-expense-category"><option value="">No Expense</option><option value="Food">Food</option><option value="Gas">Gas</option><option value="Cash Advance">Cash Advance</option><option value="Others">Others</option></select></div><div class="form-group"><input type="number" class="form-input driver-expense-amount" min="0" step="0.01"></div><div class="form-group"><input type="text" class="form-input driver-expense-name" maxlength="255" disabled></div><button type="button" class="driver-expense-remove">×</button>';
            return row;
        }

        function buildDriverDelivery() {
            const row = document.createElement('div');
            row.className = 'driver-delivery-row';
            row.innerHTML = '<div class="form-group"><input type="text" class="form-input driver-delivery-customer" maxlength="100" autocomplete="off"></div><div class="form-group"><input type="number" class="form-input driver-delivery-slim" min="0" step="1"></div><div class="form-group"><input type="number" class="form-input driver-delivery-round" min="0" step="1"></div><div class="form-group"><input type="number" class="form-input driver-delivery-payment" min="0" step="0.01"></div><div class="form-group"><input type="number" class="form-input driver-delivery-price" min="0" step="0.01"></div><div class="form-group"><select class="form-input driver-delivery-method"><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div><div class="form-group"><div class="driver-delivery-balance">—</div></div><button type="button" class="driver-delivery-remove">×</button>';
            return row;
        }

        function restoreShop(draft) {
            const money = document.getElementById('walk_in_money');
            if (money) money.value = draft?.walk_in_money || '';

            const expenses = document.getElementById('expenseRows');
            if (expenses && Array.isArray(draft?.expenses)) {
                expenses.innerHTML = '';
                draft.expenses.forEach(item => {
                    const row = buildShopExpense();
                    row.querySelector('.expense-category').value = item.category || '';
                    row.querySelector('.expense-amount').value = item.amount || '';
                    row.querySelector('.expense-name').value = item.name || '';
                    expenses.appendChild(row);
                    row.querySelector('.expense-category').dispatchEvent(new Event('change', { bubbles: true }));
                });
                if (!draft.expenses.length) expenses.appendChild(buildShopExpense());
            }

            const deliveries = document.getElementById('deliveryPaymentRows');
            if (deliveries && Array.isArray(draft?.deliveries)) {
                deliveries.innerHTML = '';
                draft.deliveries.forEach(item => {
                    const row = buildShopDelivery();
                    row.querySelector('.delivery-customer').value = item.customer || '';
                    row.querySelector('.delivery-slim').value = item.slim || '';
                    row.querySelector('.delivery-round').value = item.round || '';
                    row.querySelector('.delivery-payment').value = item.payment || '';
                    row.querySelector('.delivery-price-input').value = item.price || '';
                    row.querySelector('.delivery-method').value = item.method || 'Cash';
                    deliveries.appendChild(row);
                });
                if (!draft.deliveries.length) deliveries.appendChild(buildShopDelivery());
            }
        }

        function restoreDriver(draft) {
            const data = draft?.driver || {};
            const panel = document.getElementById('driverDeliveriesPanel');
            if (!panel) return;

            const money = document.getElementById('driver_money_received');
            if (money) money.value = data.money_received || '';

            const expenses = panel.querySelector('#driverExpenseRows');
            if (expenses && Array.isArray(data.expenses)) {
                expenses.innerHTML = '';
                data.expenses.forEach(item => {
                    const row = buildDriverExpense();
                    row.querySelector('.driver-expense-category').value = item.category || '';
                    row.querySelector('.driver-expense-amount').value = item.amount || '';
                    row.querySelector('.driver-expense-name').value = item.name || '';
                    expenses.appendChild(row);
                    row.querySelector('.driver-expense-category').dispatchEvent(new Event('change', { bubbles: true }));
                });
                if (!data.expenses.length) expenses.appendChild(buildDriverExpense());
            }

            const deliveries = panel.querySelector('#driverDeliveryPaymentRows');
            if (deliveries && Array.isArray(data.deliveries)) {
                deliveries.innerHTML = '';
                data.deliveries.forEach(item => {
                    const row = buildDriverDelivery();
                    row.querySelector('.driver-delivery-customer').value = item.customer || '';
                    row.querySelector('.driver-delivery-slim').value = item.slim || '';
                    row.querySelector('.driver-delivery-round').value = item.round || '';
                    row.querySelector('.driver-delivery-payment').value = item.payment || '';
                    row.querySelector('.driver-delivery-price').value = item.price || '';
                    row.querySelector('.driver-delivery-method').value = item.method || 'Cash';
                    deliveries.appendChild(row);
                });
                if (!data.deliveries.length) deliveries.appendChild(buildDriverDelivery());
            }
        }

        function recalculateAll() {
            document.getElementById('shopComputeButton')?.click();

            document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(row => {
                row.querySelector('.delivery-payment')?.dispatchEvent(new Event('input', { bubbles: true }));
            });

            const driver = document.getElementById('driverDeliveriesPanel');
            if (driver) {
                driver.querySelectorAll('.driver-delivery-row').forEach(row => {
                    row.querySelector('.driver-delivery-payment')?.dispatchEvent(new Event('input', { bubbles: true }));
                });
                document.getElementById('driverBalanceButton')?.click();
            }

            document.getElementById('driverDeliveriesPanel')?.dispatchEvent(new Event('input', { bubbles: true }));
            window.dispatchEvent(new CustomEvent('marcidBlueDailyDraftRestored'));
        }

        async function loadDraft() {
            try {
                const response = await fetch('load-daily-draft.php', { cache: 'no-store', headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load draft.');
                dailyId = String(result.daily_id || '');

                if (result.has_draft && result.draft) {
                    restoring = true;
                    restoreShop(result.draft);
                    restoreDriver(result.draft);
                    restoring = false;
                    initialized = true;
                    recalculateAll();
                    setStatus('✓ Draft restored from MySQL');
                } else {
                    initialized = true;
                    recalculateAll();
                    setStatus('MySQL draft autosave ready');
                }
            } catch (error) {
                console.error(error);
                initialized = true;
                setStatus('Draft load failed: ' + error.message, true);
            }
        }

        async function saveDraft() {
            if (!dailyId || restoring || !initialized) return;
            if (saving) {
                queued = true;
                return;
            }
            saving = true;
            queued = false;
            try {
                const body = new URLSearchParams();
                body.set('daily_id', dailyId);
                body.set('draft', JSON.stringify(collect()));
                const response = await fetch('save-daily-draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        Accept: 'application/json'
                    },
                    body: body.toString()
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save draft.');
                setStatus('✓ Draft saved to MySQL');
            } catch (error) {
                console.error(error);
                setStatus('Draft autosave failed: ' + error.message, true);
            } finally {
                saving = false;
                if (queued) schedule(250);
            }
        }

        function schedule(delay = 700) {
            clearTimeout(timer);
            timer = setTimeout(() => {
                timer = null;
                saveDraft();
            }, delay);
        }

        function onFormChange() {
            if (restoring) return;
            document.getElementById('shopComputeButton')?.click();
            schedule();
        }

        form.addEventListener('input', onFormChange);
        form.addEventListener('change', onFormChange);

        if (driverPanel) {
            driverPanel.addEventListener('input', function () {
                if (restoring) return;
                document.getElementById('driverBalanceButton')?.click();
                schedule();
            });
            driverPanel.addEventListener('change', function () {
                if (restoring) return;
                document.getElementById('driverBalanceButton')?.click();
                schedule();
            });
        }

        window.addEventListener('marcidBlueDailyDraftRestored', function () {
            document.getElementById('shopComputeButton')?.click();
            document.getElementById('driverBalanceButton')?.click();
            document.getElementById('driverDeliveriesPanel')?.dispatchEvent(new Event('input', { bubbles: true }));
        });

        loadDraft();
    }

    function waitForPage() {
        const form = document.getElementById('shopWalkInForm');
        const driver = document.getElementById('driverDeliveriesPanel');
        if (form && driver) {
            init();
            return;
        }
        const observer = new MutationObserver(function () {
            const currentForm = document.getElementById('shopWalkInForm');
            const currentDriver = document.getElementById('driverDeliveriesPanel');
            if (currentForm && currentDriver) {
                observer.disconnect();
                init();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForPage, { once: true });
    } else {
        waitForPage();
    }
})();
