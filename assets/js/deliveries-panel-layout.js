(function () {
    function applyLayout() {
        const card = document.getElementById('driverDeliveriesPanel');
        if (!card) return;

        const summary = card.querySelector('.driver-deliveries-layout');
        if (!summary || document.getElementById('driverTotalDeliverySales')) return;

        const quantity = document.getElementById('driver_total_delivery_quantity');
        if (!quantity) return;

        const quantityGroup = quantity.closest('.driver-total-quantity');
        if (!quantityGroup) return;

        const quantityNote = quantityGroup.querySelector('.driver-delivery-quantity-note');
        if (quantityNote) quantityNote.remove();

        card.querySelectorAll('.driver-delivery-note').forEach(function (note) {
            note.remove();
        });

        const salesGroup = document.createElement('div');
        salesGroup.className = 'driver-summary-field';
        salesGroup.innerHTML = `
            <div class="summary-label">Total Sales for Deliver</div>
            <div class="summary-value" id="driverTotalDeliverySales">₱0.00</div>
        `;

        const statusGroup = document.createElement('div');
        statusGroup.className = 'driver-summary-field driver-remittance-status-field';
        statusGroup.innerHTML = `
            <div class="summary-label">Driver Remittance Status</div>
            <div class="summary-value driver-remittance-status" id="driverRemittanceStatus">—</div>
        `;

        summary.appendChild(salesGroup);
        summary.appendChild(statusGroup);

        const style = document.createElement('style');
        style.id = 'marcid-blue-driver-deliveries-layout-style';
        style.textContent = `
            .driver-deliveries-layout {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                align-items: end;
            }

            .driver-money-field,
            .driver-total-quantity,
            .driver-summary-field {
                min-width: 0;
            }

            .driver-total-quantity {
                padding: 8px 0 4px 8px;
            }

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

            @media (max-width: 1100px) {
                .driver-deliveries-layout {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 650px) {
                .driver-deliveries-layout {
                    grid-template-columns: 1fr;
                }
            }
        `;
        document.head.appendChild(style);
    }

    if (document.getElementById('driverDeliveriesPanel')) {
        applyLayout();
    } else {
        const observer = new MutationObserver(function () {
            if (document.getElementById('driverDeliveriesPanel')) {
                observer.disconnect();
                applyLayout();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
