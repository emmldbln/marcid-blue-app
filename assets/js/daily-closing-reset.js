(function () {
    function init() {
        const form = document.getElementById('shopWalkInForm');
        const pageHeader = document.querySelector('.page-header');
        if (!form || !pageHeader) return;

        const dailyId = form.dataset.dailyId || '';
        if (!dailyId || pageHeader.querySelector('.daily-closing-reset-button')) return;

        const headerRow = pageHeader.querySelector('.daily-closing-header-row');
        if (!headerRow) return;

        const finalizeButton = headerRow.querySelector('.daily-closing-finalize-button');
        if (!finalizeButton) return;

        const resetButton = document.createElement('button');
        resetButton.type = 'button';
        resetButton.className = 'btn btn-secondary daily-closing-reset-button';
        resetButton.textContent = 'Reset';
        resetButton.title = 'Clear all Daily Closing data for this open day';

        const actions = document.createElement('div');
        actions.className = 'daily-closing-header-actions';
        actions.style.display = 'flex';
        actions.style.alignItems = 'center';
        actions.style.gap = '10px';

        finalizeButton.parentNode.insertBefore(actions, finalizeButton);
        actions.appendChild(resetButton);
        actions.appendChild(finalizeButton);

        resetButton.addEventListener('click', async function () {
            const confirmed = window.confirm(
                'Reset this day completely?\n\nAll fields will be cleared and any autosaved or already-saved data for this open day will be permanently deleted. This cannot be undone.'
            );
            if (!confirmed) return;

            resetButton.disabled = true;
            finalizeButton.disabled = true;

            try {
                const body = new URLSearchParams();
                body.set('daily_id', dailyId);

                const response = await fetch('reset-daily-closing.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        Accept: 'application/json'
                    },
                    body: body.toString()
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to reset the day.');
                }

                // Remove every legacy browser draft so it cannot restore old values.
                Object.keys(localStorage).forEach(function (key) {
                    if (key.indexOf('marcidBlueDailyClosingDraft_') === 0) {
                        localStorage.removeItem(key);
                    }
                });

                // Reload from the clean MySQL state. The autosave module will find
                // no draft and leave every field empty/zero.
                window.location.reload();
            } catch (error) {
                console.error(error);
                alert(error.message || 'Unable to reset the day.');
                resetButton.disabled = false;
                finalizeButton.disabled = false;
            }
        });

        const style = document.createElement('style');
        style.textContent = `
            .daily-closing-header-actions .daily-closing-finalize-button {
                margin: 0;
            }
            .daily-closing-reset-button {
                min-width: 90px;
            }
            @media (max-width: 650px) {
                .daily-closing-header-actions {
                    flex-direction: column;
                    align-items: stretch !important;
                }
                .daily-closing-header-actions .btn {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
