// دالة إضافة العمولة للمسوق
function addCommissionToMarketer(orderId, commissionAmount) {
  fetch('add_commission_from_cart.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `order_id=${orderId}&commission_amount=${commissionAmount}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('تمت إضافة العمولة بنجاح:', data.commission_amount);
    } else {
      console.log('فشل إضافة العمولة:', data.message);
    }
  })
  .catch(error => {
    console.error('خطأ في إضافة العمولة:', error);
  });
}
