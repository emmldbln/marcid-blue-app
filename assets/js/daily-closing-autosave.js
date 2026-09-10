/*
=========================================================
MARCID BLUE
Daily Closing MySQL Draft Autosave
=========================================================

This stores the current Daily Closing form as a server-side draft.
It never creates deliveries, payments, expenses, or daily_sales rows.
Permanent accounting records remain handled by the existing Save action.
=========================================================
*/

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('shopWalkInForm');
    const status = document.getElementById('shopDraftStatus');

    if (!form) {
        return;
    }

    const dailyId = form.dataset.dailyId || '';

    if (!dailyId) {
        return;
    }

    let timer = null;
    let saving = false;
    let queued = false;

    function setStatus(message, isError = false) {
        if (!status) {
            return;
        }

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

    async function saveDraft() {
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to save draft.');
            }

            setStatus('✓ Draft saved to MySQL');
        } catch (error) {
            console.error('Daily draft autosave failed:', error);
            setStatus('Draft autosave failed: ' + error.message, true);
        } finally {
            saving = false;

            if (queued) {
                scheduleSave(250);
            }
        }
    }

    function scheduleSave(delay = 700) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            timer = null;
            saveDraft();
        }, delay);
    }

    form.addEventListener('input', () => scheduleSave());
    form.addEventListener('change', () => scheduleSave());

    window.addEventListener('beforeunload', () => {
        if (timer) {
            clearTimeout(timer);
        }
    });

    setStatus('MySQL draft autosave ready');
});
