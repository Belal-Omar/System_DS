<?php
// Script to add transfer method functionality to admin withdrawals page
// This script should be run to add the JavaScript functionality

echo "<script>
function handleStatusChange(select) {
    const form = select.closest('form');
    const withdrawalId = form.querySelector('input[name=\"withdrawal_id\"]').value;
    const transferMethodContainer = document.getElementById('transfer-method-' + withdrawalId);
    
    if (select.value === 'مكتمل') {
        transferMethodContainer.style.display = 'block';
    } else {
        transferMethodContainer.style.display = 'none';
        form.querySelector('select[name=\"transfer_method\"]').value = '';
    }
}
</script>";
?>
