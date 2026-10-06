// Small progressive enhancements. Every page works without this file;
// the server re-validates everything.
document.addEventListener('DOMContentLoaded', () => {
    // Confirm before destructive form submits: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
        });
    });

    // Checkout: show card fields only for card payment, address only for delivery.
    const toggle = (name, value, targetId, requiredSelector) => {
        const target = document.getElementById(targetId);
        if (!target) return;
        const update = () => {
            const checked = document.querySelector(`input[name="${name}"]:checked`);
            const show = checked && checked.value === value;
            target.hidden = !show;
            target.querySelectorAll(requiredSelector).forEach((el) => { el.required = show; });
            document.dispatchEvent(new CustomEvent('checkout:change'));
        };
        document.querySelectorAll(`input[name="${name}"]`).forEach((r) => r.addEventListener('change', update));
        update();
    };
    toggle('payment_method', 'Card', 'card-form', 'input');
    toggle('delivery_type', 'Delivery', 'address-group', 'textarea');

    // Checkout: live total including delivery fee.
    const totalEl = document.getElementById('grand-total');
    if (totalEl) {
        const subtotal = parseFloat(totalEl.dataset.subtotal);
        const fee = parseFloat(totalEl.dataset.fee);
        const feeEl = document.getElementById('fee-line');
        const render = () => {
            const delivery = document.querySelector('input[name="delivery_type"]:checked')?.value === 'Delivery';
            feeEl.textContent = 'RM ' + (delivery ? fee : 0).toFixed(2);
            totalEl.textContent = 'RM ' + (subtotal + (delivery ? fee : 0)).toFixed(2);
        };
        document.addEventListener('checkout:change', render);
        render();
    }

    // Admin orders: fill the status modal from the clicked button.
    const modal = document.getElementById('updateStatusModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            modal.querySelector('#modalOrderId').value = btn.dataset.orderId;
            modal.querySelector('#modalOrderLabel').textContent = '#' + btn.dataset.orderId;
            modal.querySelector('#newStatus').value = btn.dataset.currentStatus;
            modal.querySelector('#paymentStatus').value = btn.dataset.paymentStatus;
        });
    }
});
