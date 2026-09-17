// عناصر الصفحة
const colorBtns = document.querySelectorAll('.color-btn');
const sizeBtns = document.querySelectorAll('.size-btn');
const addCart = document.querySelector('.add-cart');
const buyNow = document.querySelector('.buy-now');
const stockBtn = document.querySelector('.stock-btn');
const stockModal = document.getElementById('stockModal');
const closeStock = document.getElementById('closeStock');

let selectedColor = null;
let selectedSize = null;

// اختيار اللون
colorBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    colorBtns.forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedColor = btn.textContent.trim();
    activateButtons();
  });
});

// اختيار المقاس
sizeBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    sizeBtns.forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedSize = btn.textContent.trim();
    activateButtons();
  });
});

// تفعيل الأزرار بعد الاختيار
function activateButtons() {
  if (selectedColor && selectedSize) {
    addCart.disabled = false;
    buyNow.disabled = false;
    stockBtn.disabled = false;
  }
}

// زر "إضافة إلى السلة"
addCart.addEventListener('click', () => {
  if (!selectedColor || !selectedSize) {
    showWarning('يرجى اختيار اللون والمقاس أولاً');
    return;
  }

  const cartItems = JSON.parse(localStorage.getItem("cart")) || [];
  const newItem = {
    name: "جاكت Adidas",
    color: selectedColor,
    size: selectedSize,
    price: 90,
    commission: 10,
    img: "imgs/68e0357a3c92b.webp",
    qty: 1
  };
  cartItems.push(newItem);
  localStorage.setItem("cart", JSON.stringify(cartItems));
  updateCartCount();
  showSuccess(`تمت إضافة (${selectedColor} - ${selectedSize}) إلى السلة`);
});

// زر "اطلب الآن"
buyNow.addEventListener('click', () => {
  if (!selectedColor || !selectedSize) {
    showWarning('يرجى اختيار اللون والمقاس أولاً');
    return;
  }

  const cartItems = JSON.parse(localStorage.getItem("cart")) || [];
  const newItem = {
    name: "جاكت Adidas",
    color: selectedColor,
    size: selectedSize,
    price: 90,
    commission: 10,
    img: "imgs/68e0357a3c92b.webp",
    qty: 1
  };
  cartItems.push(newItem);
  localStorage.setItem("cart", JSON.stringify(cartItems));
  updateCartCount();
  window.location.href = "cart.html";
});

// عرض نافذة المخزون
stockBtn.addEventListener('click', () => {
  stockModal.style.display = 'flex';
});

// غلق النافذة
closeStock.addEventListener('click', () => {
  stockModal.style.display = 'none';
});

// تحديث العداد
function updateCartCount() {
  const cartItems = JSON.parse(localStorage.getItem("cart")) || [];
  const count = cartItems.length;
  const cartCount = document.querySelector('.cart-count');
  if (cartCount) {
    cartCount.textContent = count;
  }
}

document.addEventListener('DOMContentLoaded', updateCartCount);
