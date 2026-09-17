<?php
// admin_shipping_storage.php
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'shipping_company') {
    return;
}

$sc_id = (int)($_SESSION['shipping_company_id'] ?? 0);

if ($sc_id <= 0) {
    echo "<div class='p-8 text-center text-red-500 font-bold'>عذراً، لم يتم العثور على بيانات شركتك.</div>";
    return;
}

// جلب الأرصدة الحالية للشركة
$inventory = [];
$inv_query = "
    SELECT i.quantity, i.updated_at, p.product_name, i.product_code
    FROM shipping_inventory i
    LEFT JOIN shipping_inventory_products p ON i.product_code = p.product_code
    WHERE i.shipping_company_id = $sc_id AND IFNULL(i.status, 'active') = 'active'
    ORDER BY p.product_name ASC
";
$res_inv = $conn->query($inv_query);
if ($res_inv) {
    while ($r = $res_inv->fetch_assoc()) {
        $inventory[] = $r;
    }
}

$low_stock_msg = [];
foreach ($inventory as $inv) {
    if ($inv['quantity'] <= 10) {
        $low_stock_msg[] = "- المنتج ({$inv['product_name']}) رصيده ({$inv['quantity']})";
    }
}
?>

<?php if (!empty($low_stock_msg)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire('تنبيه المخزون!', <?= json_encode("بعض المنتجات في عهدتكم قارب رصيدها على النفاذ:\n" . implode("\n", $low_stock_msg)) ?>, 'warning');
});
</script>
<?php endif; ?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center mb-8">
        <i class='bx bx-store-alt text-emerald-600 mr-3 text-4xl'></i>
        <h1 class="text-3xl font-extrabold text-slate-800">مخزن الشركة (الأرصدة المتاحة)</h1>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mt-8">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="text-lg font-bold text-slate-700">رصيد المنتجات المسلمة لك من الإدارة</h3>
            <div class="w-full md:w-1/3 flex">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class='bx bx-search text-gray-400 text-xl'></i>
                    </div>
                    <input type="text" id="storageSearchInput" placeholder="بحث باسم أو كود المنتج..." class="w-full p-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors">
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-slate-500 uppercase bg-white">
                    <tr>
                        <th class="px-6 py-4 border-b">المنتج</th>
                        <th class="px-6 py-4 border-b">كود المنتج</th>
                        <th class="px-6 py-4 border-b text-center">الكمية المتوفرة في عهدتكم</th>
                        <th class="px-6 py-4 border-b">آخر تحديث للرصيد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500 font-bold">لا يوجد أي أرصدة مسجلة في مخزنكم حالياً.</td></tr>
                    <?php else: ?>
                        <?php foreach($inventory as $inv): ?>
                        <tr class="hover:bg-slate-50 transition-colors storage-row">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800 storage-name"><?= htmlspecialchars($inv['product_name'] ?? 'غير معروف') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 storage-code" dir="ltr"><?= htmlspecialchars($inv['product_code']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-3 py-1 rounded-full text-sm font-bold <?= $inv['quantity'] <= 10 ? 'bg-red-100 text-red-700 animate-pulse' : 'bg-green-100 text-green-700' ?>">
                                    <?= (int)$inv['quantity'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-xs"><?= htmlspecialchars($inv['updated_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noStorageResultsRow" style="display: none;">
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 font-bold">لم يتم العثور على نتائدلطابقة للبحث</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('storageSearchInput');
    const rows = document.querySelectorAll('.storage-row');
    const noResultsRow = document.getElementById('noStorageResultsRow');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.storage-name').textContent.toLowerCase();
                const code = row.querySelector('.storage-code').textContent.toLowerCase();

                if (name.includes(query) || code.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount === 0 && rows.length > 0) {
                noResultsRow.style.display = '';
            } else {
                noResultsRow.style.display = 'none';
            }
        });
    }
});
</script>
