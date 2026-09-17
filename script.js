// القيم الافتراضية
const defaults = {
  itemsCount: 0,
  totalEarnings: 0,
  withdrawn: 0,
  available: 0,
  expected: 0
};

// عرض القيم
document.getElementById('items-count').textContent = defaults.itemsCount;
document.getElementById('total-earn').textContent = defaults.totalEarnings;
document.getElementById('withdrawn').textContent = defaults.withdrawn;
document.getElementById('available').textContent = defaults.available;
document.getElementById('expected').textContent = defaults.expected;

// حساب نسبة التسليم
(function () {
  const startDate = new Date("2025-01-01T00:00:00Z");
  const now = new Date();
  const days = Math.floor((now - startDate) / (1000 * 60 * 60 * 24));
  const increments = Math.floor(days / 3);
  const percent = 20 + (increments * 0.001);
  const percentStr = percent.toFixed(3) + "%";

  document.getElementById("delivery-percent").textContent = percentStr;
  document.getElementById("progress-bar").style.width = Math.min(100, percent) + "%";
})();

// حركة بسيطة عند الضغط على البوكسات
document.querySelectorAll(".order-box").forEach(box => {
  box.addEventListener("click", () => {
    box.style.transform = "scale(1.07)";
    setTimeout(() => { box.style.transform = ""; }, 200);
  });
});
