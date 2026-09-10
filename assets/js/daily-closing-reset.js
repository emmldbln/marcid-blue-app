(function () {
    function clearLegacyDrafts() {
        try {
            Object.keys(localStorage).forEach(function (key) {
                if (key.indexOf('marcidBlueDailyClosingDraft_') === 0) {
                    localStorage.removeItem(key);
                }
            });
        } catch (error) {
            console.warn('Unable to clear legacy browser draft:', error);
        }
    }

    function addResetButton() {
        if (document.getElementById('dailyClosingResetButton')) return true;

        const pageHeader = document.querySelector('.page-header');
        if (!pageHeader) return false;

        let headerRow = pageHeader.querySelector('.daily-closing-header-row');
        if (!headerRow) return false;

        const finalizeButton = headerRow.querySelector('.daily-closing-finalize-button');
        if (!finalizeButton) return false;

        const resetButton = document.createElement('button');
        resetButton.type = 'button';
        resetButton.id = 'dailyClosingResetButton';
        resetButton.className = 'btn btn-secondary daily-closing-reset-button';
        resetButton.textContent = 'Reset';
        resetButton.title = 'Clear all data for the current open day';
        resetButton.setAttribute('aria-label', 'Reset Daily Closing');

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
                'Reset this day completely?\n\n' +
                'All fields will be cleared and any autosaved or already-saved data for the current open day will be permanently deleted. This cannot be undone.'
            );

            if (!confirmed) return;

            resetButton.disabled = true;
            finalizeButton.disabled = true;

            try {
                const response = await fetch('reset-daily-closing.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        Accept: 'application/json'
                    },
                    body: ''
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to reset the day.');
                }

                clearLegacyDrafts();

                try {
                    window.sessionStorage.setItem('marcidBlueDailyClosingJustReset', '1');
                } catch (error) {
                    console.warn('Unable to set reset session flag:', error);
                }

                window.location.reload();
            } catch (error) {
                console.error(error);
                alert(error.message || 'Unable to reset the day.');
                resetButton.disabled = false;
                finalizeButton.disabled = false;
            }
        });

        const style = document.createElement('style');
        style.id = 'daily-closing-reset-style';
        style.textContent = `
            .daily-closing-header-actions {
                flex: 0 0 auto;
            }

            .daily-closing-header-actions .btn {
                margin: 0;
                transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            }

            /* Match the same hover interaction used by the other action buttons. */
            .daily-closing-header-actions .daily-closing-reset-button:hover:not(:disabled) {
                background: var(--secondary-dark, #14837e) !important;
                transform: translateY(-1px);
                box-shadow: var(--shadow-sm);
            }

            .daily-closing-header-actions .daily-closing-finalize-button:hover:not(:disabled) {
                background: var(--primary-dark) !important;
                transform: translateY(-1px);
                box-shadow: var(--shadow-sm);
            }

            .daily-closing-header-actions .btn:active:not(:disabled) {
                transform: translateY(0);
                box-shadow: none;
            }

            .daily-closing-header-actions .btn:disabled {
                transform: none;
                box-shadow: none;
            }

            .daily-closing-reset-button {
                min-width: 90px;
            }

            @media (max-width: 650px) {
                .daily-closing-header-actions {
                    flex-direction: column;
                    align-items: stretch !important;
                    width: 100%;
                }
                .daily-closing-header-actions .btn {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);

        return true;
    }

    function init() {
        if (addResetButton()) return;

        const observer = new MutationObserver(function () {
            if (addResetButton()) {
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        window.setTimeout(function () {
            if (addResetButton()) observer.disconnect();
        }, 3000);
    }

    clearLegacyDrafts();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
