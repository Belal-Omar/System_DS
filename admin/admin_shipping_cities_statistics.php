<?php 
// ملف: admin_shipping_cities_statistics.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/core/config.php");

// التحقق من أن المستخدم أدمن
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.html");
    exit;
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إحصائيات مدن الشحن</h1>
    <div class="flex gap-3">
        <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors" onclick="exportToExcel()">
            <i class="bx bx-download ml-2"></i>
            تصدير Excel
        </button>
        <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors" onclick="syncCitiesStats()" id="refreshBtn">
            <i class="fa fa-refresh"></i> تحديث الإحصائيات
        </button>
    </div>
</div>

<!-- رسائل التحديث -->
<div id="messageContainer"></div>

<div class="page-wrapper">

<!-- إحصائيات عامة -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg ml-4">
                <i class='bx bx-map text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">عدد المدن</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalCities">0</h3>
                <p class="text-sm text-gray-500 mt-1">مدينة نشطة</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg ml-4">
                <i class='bx bx-shopping-bag text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الطلبات</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalOrders">0</h3>
                <p class="text-sm text-gray-500 mt-1">من جميع المدن</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-yellow-500">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg ml-4">
                <i class='bx bx-money text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الإيرادات المكتملة</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalRevenue">0 د.ل</h3>
                <p class="text-sm text-gray-500 mt-1">المجموع فقط (مثل orders.html)</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-purple-500">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg ml-4">
                <i class='bx bx-trending-up text-purple-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">متوسط قيمة الطلب المكتمل</p>
                <h3 class="text-2xl font-bold text-gray-800" id="avgOrderValue">0 د.ل</h3>
                <p class="text-sm text-gray-500 mt-1">المجموع فقط لكل طلب مكتمل</p>
            </div>
        </div>
    </div>
</div>

<!-- رسوم بيانية -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="chart-container">
        <h3 class="text-xl font-bold text-gray-800 mb-4">توزيع الطلبات حسب المدينة</h3>
        <canvas id="citiesChart" height="300"></canvas>
    </div>
    
    <div class="chart-container">
        <h3 class="text-xl font-bold text-gray-800 mb-4">الإيرادات المكتملة حسب المدينة (المجموع فقط)</h3>
        <canvas id="revenueChart" height="300"></canvas>
    </div>
</div>

<!-- أفضل المدن -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="stat-card">
        <h3 class="text-xl font-bold text-gray-800 mb-4">أفضل المدن (الأكثر إيرادات مكتملة)</h3>
        <div class="space-y-3" id="topCities">
            <!-- سيتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
    
    <div class="stat-card">
        <h3 class="text-xl font-bold text-gray-800 mb-4">المدن الأقل نشاطاً (أقل إيرادات مكتملة)</h3>
        <div class="space-y-3" id="bottomCities">
            <!-- سيتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
</div>

<!-- إحصائيات الحالات -->
<div class="stat-card p-6 mb-8">
    <h3 class="text-xl font-bold text-gray-800 mb-4">توزيع الطلبات حسب الحالة</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4" id="statusStats">
        <!-- سيتم ملؤها بالجافاسكريبت -->
    </div>
</div>

<!-- تفاصيل جميع المدن -->
<div class="stat-card">
    <h3 class="text-xl font-bold text-gray-800 mb-6">تفاصيل جميع المدن</h3>
    <div class="overflow-x-auto">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>المدينة</th>
                    <th>مصاريف الشحن</th>
                    <th>عدد الطلبات</th>
                    <th>الإيرادات المكتملة (المجموع فقط)</th>
                    <th>مكتمل</th>
                    <th>ملغي</th>
                    <th>مرتجع</th>
                    <th>متوسط الطلب</th>
                    <th>نسبة النجاح</th>
                    <th>المسوقون الفريدون</th>
                </tr>
            </thead>
            <tbody id="citiesTableBody">
                <!-- سيتم ملؤها بالجافاسكريبت -->
            </tbody>
        </table>
    </div>
</div>

</div>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .stat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .orders-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .orders-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: right;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
    }
    
    .orders-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .orders-table tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-completed {
        background: #d4edda;
        color: #155724;
    }
    
    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-returned {
        background: #fff3cd;
        color: #856404;
    }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: #4b6b2f;
        transition: width 0.3s ease;
    }
    
    .refresh-btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 500;
    }
    
    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<script>
// بيانات المدن
let citiesData = [];
let apiData = null; // تخزين بيانات الـ API الكاملة
let citiesChart = null;
let revenueChart = null;

// جلب إحصائيات المدن
async function syncCitiesStats() {
  const refreshBtn = document.getElementById('refreshBtn');
  const messageContainer = document.getElementById('messageContainer');
  
  try {
    refreshBtn.classList.add('loading');
    refreshBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري التحديث...';
    
    const response = await fetch('admin_get_shipping_cities_stats.php');
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    console.log('Admin Response data:', data);
    
    if (data.success) {
      citiesData = data.cities || [];
      apiData = data; // تخزين بيانات الـ API الكاملة
      updateUI();
      showMessage('تم تحديث الإحصائيات بنجاح', 'success');
    } else {
      showMessage(data.message || 'فشل تحميل الإحصائيات', 'error');
    }
  } catch (error) {
    console.error('Error fetching admin cities stats:', error);
    showMessage('حدث خطأ في الاتصال بالخادم: ' + error.message, 'error');
  } finally {
    refreshBtn.classList.remove('loading');
    refreshBtn.innerHTML = '<i class="fa fa-refresh"></i> تحديث الإحصائيات';
  }
}

// تحديث الواجهة
function updateUI() {
  updateStatistics();
  updateCharts();
  updateTopCities();
  updateBottomCities();
  updateStatusStats();
  updateCitiesTable();
}

// تحديث الإحصائيات العامة
function updateStatistics() {
  const totalCities = citiesData.length;
  // استخدام البيانات المباشرة من الـ API بدلاً من تجميع البيانات
  
  console.log('Admin API Data:', apiData); // للتصحيح
  
  const totalOrders = apiData?.admin_stats?.direct_total_orders || 0;
  const completedOrders = apiData?.admin_stats?.direct_completed_orders || 0;
  const totalRevenue = parseFloat(apiData?.admin_stats?.direct_completed_revenue || 0);
  const avgOrderValue = completedOrders > 0 ? totalRevenue / completedOrders : 0;
  
  console.log('Admin Values:', { totalOrders, completedOrders, totalRevenue, avgOrderValue }); // للتصحيح
  
  document.getElementById('totalCities').textContent = totalCities;
  document.getElementById('totalOrders').textContent = totalOrders.toLocaleString();
  document.getElementById('totalRevenue').textContent = totalRevenue.toFixed(2) + ' د.ل';
  document.getElementById('avgOrderValue').textContent = avgOrderValue.toFixed(2) + ' د.ل';
}

// تحديث الرسوم البيانية
function updateCharts() {
  // تدمير الرسوم البيانية الموجودة
  if (citiesChart) {
    citiesChart.destroy();
  }
  if (revenueChart) {
    revenueChart.destroy();
  }
  
  // رسم بياني لتوزيع الطلبات
  const citiesCtx = document.getElementById('citiesChart').getContext('2d');
  const maxOrders = Math.max(...citiesData.slice(0, 10).map(city => city.orders || 0));
  
  citiesChart = new Chart(citiesCtx, {
    type: 'bar',
    data: {
      labels: citiesData.slice(0, 10).map(city => city.city_name || 'Unknown'),
      datasets: [{
        label: 'عدد الطلبات',
        data: citiesData.slice(0, 10).map(city => parseInt(city.orders_count || 0)),
        backgroundColor: 'rgba(75, 107, 47, 0.8)',
        borderColor: 'rgba(75, 107, 47, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: maxOrders > 0 ? maxOrders * 1.2 : 10
        },
        x: {
          ticks: {
            maxRotation: 45,
            minRotation: 0,
            font: {
              size: 11
            }
          }
        }
      }
    }
  });
  
  // رسم بياني للإيرادات
  const revenueCtx = document.getElementById('revenueChart').getContext('2d');
  revenueChart = new Chart(revenueCtx, {
    type: 'doughnut',
    data: {
      labels: citiesData.slice(0, 8).map(city => city.city_name || 'Unknown'),
      datasets: [{
        data: citiesData.slice(0, 8).map(city => parseFloat(city.completed_revenue || 0)),
        backgroundColor: [
          'rgba(75, 107, 47, 0.8)',
          'rgba(59, 130, 246, 0.8)',
          'rgba(245, 158, 11, 0.8)',
          'rgba(239, 68, 68, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(236, 72, 153, 0.8)',
          'rgba(34, 197, 94, 0.8)',
          'rgba(251, 146, 60, 0.8)'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 12,
            padding: 8,
            font: {
              size: 10
            }
          }
        }
      },
      cutout: '50%'
    }
  });
}

// تحديث أفضل المدن
function updateTopCities() {
  const topCities = citiesData.slice(0, 5);
  const container = document.getElementById('topCities');
  
  container.innerHTML = topCities.map((city, index) => `
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div class="flex items-center">
        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center ml-3">
          <span class="text-green-600 font-bold">${index + 1}</span>
        </div>
        <div>
          <p class="font-semibold text-gray-800">${city.city_name || 'Unknown'}</p>
          <p class="text-sm text-gray-500">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</p>
        </div>
      </div>
      <div class="text-left">
        <p class="font-bold text-gray-800">${city.completed_orders || 0}</p>
        <p class="text-sm text-gray-500">طلب مكتمل</p>
      </div>
    </div>
  `).join('');
}

// تحديث المدن الأقل نشاطاً
function updateBottomCities() {
  const bottomCities = citiesData.slice(-5).reverse();
  const container = document.getElementById('bottomCities');
  
  container.innerHTML = bottomCities.map((city, index) => `
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div class="flex items-center">
        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center ml-3">
          <span class="text-red-600 font-bold">${index + 1}</span>
        </div>
        <div>
          <p class="font-semibold text-gray-800">${city.city_name || 'Unknown'}</p>
          <p class="text-sm text-gray-500">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</p>
        </div>
      </div>
      <div class="text-left">
        <p class="font-bold text-gray-800">${city.completed_orders || 0}</p>
        <p class="text-sm text-gray-500">طلب مكتمل</p>
      </div>
    </div>
  `).join('');
}

// تحديث إحصائيات الحالات
function updateStatusStats() {
  const statusStats = apiData?.status_stats || {};
  const container = document.getElementById('statusStats');
  
  const statusConfig = {
    'pending': { label: 'قيد الانتظار', color: 'yellow' },
    'confirmed': { label: 'تم التأكيد', color: 'blue' },
    'processing': { label: 'قيد التنفيذ', color: 'purple' },
    'shipping': { label: 'في الشحن', color: 'indigo' },
    'completed': { label: 'مكتمل', color: 'green' },
    'cancelled': { label: 'ملغي', color: 'red' },
    'returned': { label: 'مرتجع', color: 'orange' }
  };
  
  container.innerHTML = Object.entries(statusConfig).map(([key, config]) => `
    <div class="text-center p-4 bg-${key === 'completed' ? 'green' : 'gray'}-50 rounded-lg">
      <div class="text-2xl font-bold text-${config.color}-600">${statusStats[key] || 0}</div>
      <div class="text-sm text-gray-600">${config.label}</div>
    </div>
  `).join('');
}

// تحديث جدول المدن
function updateCitiesTable() {
  const tbody = document.getElementById('citiesTableBody');
  
  tbody.innerHTML = citiesData.map(city => {
    const successRate = (city.orders_count || 0) > 0 ? ((city.completed_orders || 0) / (city.orders_count || 0)) * 100 : 0;
    
    return `
      <tr class="hover:bg-gray-50">
        <td>
          <div class="flex items-center">
            <i class='bx bx-map-pin text-blue-500 ml-2'></i>
            <span class="font-semibold">${city.city_name || 'Unknown'}</span>
          </div>
        </td>
        <td>${parseFloat(city.shipping_cost || 0).toFixed(2)} د.ل</td>
        <td>
          <span class="font-bold text-blue-600">${city.orders_count || 0}</span>
        </td>
        <td>
          <span class="font-bold text-green-600">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</span>
        </td>
        <td>
          <span class="status-badge status-completed">${city.completed_orders || 0}</span>
        </td>
        <td>
          <span class="status-badge status-cancelled">${city.cancelled_orders || 0}</span>
        </td>
        <td>
          <span class="status-badge status-returned">${city.returned_orders || 0}</span>
        </td>
        <td>${parseFloat(city.avg_order_value || 0).toFixed(2)} د.ل</td>
        <td>
          <div class="flex items-center">
            <div class="progress-bar w-16 ml-2">
              <div class="progress-fill" style="width: ${successRate}%"></div>
            </div>
            <span class="text-sm font-semibold">${successRate.toFixed(1)}%</span>
          </div>
        </td>
        <td>${city.unique_marketers || 0}</td>
      </tr>
    `;
  }).join('');
}

// عرض الرسائل
function showMessage(message, type) {
  const messageContainer = document.getElementById('messageContainer');
  messageContainer.innerHTML = `<div class="message ${type}">${message}</div>`;
  
  setTimeout(() => {
    messageContainer.innerHTML = '';
  }, 5000);
}

// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
  syncCitiesStats();
});

// تحديث تلقائي كل 5 دقائق
setInterval(syncCitiesStats, 5 * 60 * 1000);

function exportToExcel() {
  if (!citiesData || !citiesData.length) {
    alert('لا توجد بيانات للتصدير حالياً. انتظر تحميل الإحصائيات ثم أعد المحاولة.');
    return;
  }
  let csv = '\ufeff';
  csv += 'المدينة,مصاريف الشحن,عدد الطلبات,إيرادات مكتملة,مكتمل,ملغي,مرتجع,متوسط الطلب,نسبة النجاح,المستخدمون الفريدون\n';
  citiesData.forEach(city => {
    const orders = parseInt(city.orders_count || city.total_orders || 0, 10);
    const completed = parseInt(city.completed_orders || 0, 10);
    const revenue = parseFloat(city.completed_revenue || city.total_revenue || 0);
    const successRate = orders > 0 ? (completed / orders) * 100 : 0;
    const name = String(city.city_name || 'غير معروف').replace(/"/g, '""');
    csv += `"${name}",`;
    csv += `${parseFloat(city.shipping_cost || 0).toFixed(2)},`;
    csv += `${orders},`;
    csv += `${revenue.toFixed(2)},`;
    csv += `${completed},`;
    csv += `${parseInt(city.cancelled_orders || 0, 10)},`;
    csv += `${parseInt(city.returned_orders || 0, 10)},`;
    csv += `${orders > 0 ? (revenue / orders).toFixed(2) : '0.00'},`;
    csv += `${successRate.toFixed(1)}%,`;
    csv += `${city.unique_users || city.unique_marketers || 1}\n`;
  });
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `إحصائيات_المدن_${new Date().toISOString().split('T')[0]}.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
}
</script>

<style>
    .stat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 100%;
        overflow: hidden;
    }
    
    .chart-container canvas {
        max-width: 100% !important;
        height: 300px !important;
        max-height: 300px !important;
    }
    
    .orders-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .orders-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: right;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
    }
    
    .orders-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .orders-table tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-completed {
        background: #d4edda;
        color: #155724;
    }
    
    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-returned {
        background: #fff3cd;
        color: #856404;
    }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: #4b6b2f;
        transition: width 0.3s ease;
    }
    
    .refresh-btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 500;
    }
    
    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>
