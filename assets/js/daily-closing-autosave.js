document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('shopWalkInForm');
    const status = document.getElementById('shopDraftStatus');

    if (!form) return;

    let dailyId = '';
    let timer = null;
    let saving = false;
    let queued = false;
    let restoring = false;

    function setStatus(message, isError = false) {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        status.classList.toggle('is-success', !isError);
    }

    function collectDraft() {
        const draft = {
            walk_in_money: document.getElementById('walk_in_money')?.value || '',
            expenses: [],
            deliveries: []
        };

        document.querySelectorAll('#expenseRows .expense-row').forEach(row => {
            draft.expenses.push({
                category: row.querySelector('.expense-category')?.value || '',
                name: row.querySelector('.expense-name')?.value || '',
                amount: row.querySelector('.expense-amount')?.value || ''
            });
        });

        document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(row => {
            draft.deliveries.push({
                customer: row.querySelector('.delivery-customer')?.value || '',
                slim: row.querySelector('.delivery-slim')?.value || '',
                round: row.querySelector('.delivery-round')?.value || '',
                payment: row.querySelector('.delivery-payment')?.value || '',
                price: row.querySelector('.delivery-price-input')?.value || '',
                method: row.querySelector('.delivery-method')?.value || 'Cash'
            });
        });

        return draft;
    }

    function refreshPageCalculations() {
        document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(row => {
            const customer = row.querySelector('.delivery-customer');
            if (customer) customer.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const money = document.getElementById('walk_in_money');
        if (money) money.dispatchEvent(new Event('input', { bubbles: true }));

        const compute = document.getElementById('shopComputeButton');
        if (compute) compute.click();
    }

    function makeExpenseRow() {
        const row = document.createElement('div');
        row.className = 'expense-row';
        row.innerHTML = `
            <div class="form-group">
                <label class="form-label">Expense</label>
                <select name="expense_category[]" class="form-input expense-category">
                    <option value="">No Expense</option>
                    <option value="Food">Food</option>
                    <option value="Gas">Gas</option>
                    <option value="Cash Advance">Cash Advance</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" name="expense_amount[]" class="form-input expense-amount" min="0" step="0.01" placeholder="Enter amount">
            </div>
            <div class="form-group expense-name-group">
                <label class="form-label expense-name-label">Name / Description</label>
                <input type="text" name="expense_name[]" class="form-input expense-name" maxlength="255" disabled>
            </div>
            <button type="button" class="expense-remove" title="Remove expense" aria-label="Remove expense">×</button>
        `;
        return row;
    }

    function makeDeliveryRow() {
        const row = document.createElement('div');
        row.className = 'delivery-payment-row';
        row.innerHTML = `
            <div class="form-group delivery-customer-group">
                <label class="form-label">Customer</label>
                <input type="text" name="delivery_customer[]" class="form-input delivery-customer" list="shopDeliveryCustomerList" maxlength="100" placeholder="Select or enter customer" autocomplete="off">
            </div>
            <div class="form-group"><label class="form-label">Slim</label><input type="number" name="delivery_slim[]" class="form-input delivery-slim" min="0" step="1" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Round</label><input type="number" name="delivery_round[]" class="form-input delivery-round" min="0" step="1" placeholder="0"></div>
            <div class="form-group delivery-payment-group"><label class="form-label">Payment</label><input type="number" name="delivery_payment[]" class="form-input delivery-payment" min="0" step="0.01" placeholder="Optional"></div>
            <div class="form-group delivery-price-group"><label class="form-label">Price/Gal</label><input type="number" name="delivery_price_per_gallon[]" class="form-input delivery-price-input" min="0" step="0.01" placeholder="Required for new customer"></div>
            <div class="form-group"><label class="form-label">Method</label><select name="delivery_method[]" class="form-input delivery-method"><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div>
            <div class="form-group delivery-balance-group"><label class="form-label">Balance</label><div class="delivery-balance delivery-balance-neutral" aria-live="polite">—</div></div>
            <button type="button" class="delivery-payment-remove" title="Remove payment" aria-label="Remove payment">×</button>
        `;
        return row;
    }

    function applyDraft(draft) {
        if (!draft) return;
        restoring = true;

        const money = document.getElementById('walk_in_money');
        if (money) money.value = draft.walk_in_money || '';

        const expenseRows = document.getElementById('expenseRows');
        if (expenseRows && Array.isArray(draft.expenses)) {
            expenseRows.innerHTML = '';
            draft.expenses.forEach(expense => {
                const row = makeExpenseRow();
                row.querySelector('.expense-category').value = expense.category || '';
                row.querySelector('.expense-amount').value = expense.amount || '';
                row.querySelector('.expense-name').value = expense.name || '';
                expenseRows.appendChild(row);
            });
            if (!draft.expenses.length) expenseRows.appendChild(makeExpenseRow());
        }

        const deliveryRows = document.getElementById('deliveryPaymentRows');
        if (deliveryRows && Array.isArray(draft.deliveries)) {
            deliveryRows.innerHTML = '';
            draft.deliveries.forEach(delivery => {
                const row = makeDeliveryRow();
                row.querySelector('.delivery-customer').value = delivery.customer || '';
                row.querySelector('.delivery-slim').value = delivery.slim || '';
                row.querySelector('.delivery-round').value = delivery.round || '';
                row.querySelector('.delivery-payment').value = delivery.payment || '';
                row.querySelector('.delivery-price-input').value = delivery.price || '';
                row.querySelector('.delivery-method').value = delivery.method || 'Cash';
                deliveryRows.appendChild(row);
            });
            if (!draft.deliveries.length) deliveryRows.appendChild(makeDeliveryRow());
        }

        restoring = false;
        refreshPageCalculations();
    }

    async function loadDraft() {
        try {
            const response = await fetch('load-daily-draft.php', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load draft.');

            dailyId = String(result.daily_id || '');
            if (result.has_draft && result.draft) {
                applyDraft(result.draft);
                setStatus('✓ Draft restored from MySQL');
            } else {
                setStatus('MySQL draft autosave ready');
            }
        } catch (error) {
            console.error('Daily draft load failed:', error);
            setStatus('Draft load failed: ' + error.message, true);
        }
    }

    async function saveDraft() {
        if (!dailyId) return;
        if (saving) {
            queued = true;
            return;
        }

        saving = true;
        queued = false;
        setStatus('Saving draft…');

        try {
            const body = new URLSearchParams();
            body.set('daily_id', dailyId);
            body.set('draft', JSON.stringify(collectDraft()));

            const response = await fetch('save-daily-draft.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save draft.');
            setStatus('✓ Draft saved to MySQL');
        } catch (error) {
            console.error('Daily draft autosave failed:', error);
            setStatus('Draft autosave failed: ' + error.message, true);
        } finally {
            saving = false;
            if (queued) scheduleSave(250);
        }
    }

    function scheduleSave(delay = 700) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            saveDraft();
        }, delay);
    }

    form.addEventListener('input', () => {
        if (!restoring) scheduleSave();
    });

    form.addEventListener('change', () => {
        if (!restoring) scheduleSave();
    });

    loadDraft();
});
