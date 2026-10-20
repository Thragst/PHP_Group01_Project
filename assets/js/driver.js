window.openConfirmModal = function(orderId) {
    var input = document.getElementById('confirmModalOrderId');
    var text = document.getElementById('confirmModalText');
    var modal = document.getElementById('confirmDeliveryModal');

    if (input) input.value = orderId;
    if (text) text.innerText = 'Confirm order #ORD-' + orderId + ' has been successfully delivered?';
    if (modal) modal.style.display = 'flex';
};

window.closeConfirmModal = function() {
    var modal = document.getElementById('confirmDeliveryModal');
    if (modal) modal.style.display = 'none';
};

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('custom-modal-overlay')) {
        e.target.style.display = 'none';
    }
});