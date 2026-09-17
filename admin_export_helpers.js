/**
 * تصدير Excel/PDF مع دعم العربية — نسخة مستقرة
 */
(function (global) {
  'use strict';

  function safeFilename(name) {
    return String(name || 'export')
      .replace(/[\\/:*?"<>|]+/g, '_')
      .replace(/\s+/g, '_')
      .slice(0, 80);
  }

  function downloadTextFile(content, filename, mime) {
    filename = safeFilename(filename);
    mime = mime || 'text/csv;charset=utf-8;';

    // data: URI أأمن مع CSP من blob:
    try {
      var dataUrl = 'data:' + mime + ',' + encodeURIComponent(content);
      var a = document.createElement('a');
      a.href = dataUrl;
      a.setAttribute('download', filename);
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      a.remove();
      return;
    } catch (e1) {}

    try {
      var blob = new Blob([content], { type: mime });
      var url = URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      setTimeout(function () {
        URL.revokeObjectURL(url);
        link.remove();
      }, 1500);
    } catch (e2) {
      alert('تعذر تنزيل الملف. جرّب متصفح آخر.');
    }
  }

  function cellText(td) {
    return (td.innerText || td.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function exportTableToExcel(tableOrId, filename, opts) {
    opts = opts || {};
    var skip = opts.skipColumns || [];
    var table = typeof tableOrId === 'string' ? document.getElementById(tableOrId) : tableOrId;
    if (!table) {
      alert('تعذر العثور على الجدول للتصدير.');
      return;
    }

    var csv = '\ufeff';
    var rows = table.querySelectorAll('tr');
    var rowCount = 0;
    for (var r = 0; r < rows.length; r++) {
      var cells = rows[r].querySelectorAll('th,td');
      if (!cells.length) continue;
      var line = [];
      for (var c = 0; c < cells.length; c++) {
        if (skip.indexOf(c) !== -1) continue;
        var val = cellText(cells[c]).replace(/"/g, '""');
        line.push('"' + val + '"');
      }
      if (line.length) {
        csv += line.join(',') + '\n';
        rowCount++;
      }
    }

    if (rowCount <= 1) {
      alert('لا توجد بيانات كافية في الجدول للتصدير.');
      return;
    }

    downloadTextFile(csv, (filename || 'export') + '.csv', 'text/csv;charset=utf-8;');
  }

  function exportElementToPdf(elementOrId, filename, opts) {
    opts = opts || {};
    if (typeof html2pdf === 'undefined') {
      alert('مكتبة PDF غير محمّلة. حدّث الصفحة (Ctrl+F5) ثم أعد المحاولة.');
      return;
    }

    var el = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
    if (!el) {
      alert('تعذر العثور على المحتوى للتصدير.');
      return;
    }

    var clone = el.cloneNode(true);
    clone.style.maxHeight = 'none';
    clone.style.overflow = 'visible';
    clone.style.height = 'auto';
    clone.style.width = '100%';
    clone.style.background = '#ffffff';
    clone.querySelectorAll('*').forEach(function (n) {
      if (n.style) {
        n.style.maxHeight = 'none';
        n.style.setProperty('max-width', 'none', 'important');
      }
      if (n.classList && (n.classList.contains('overflow-y-auto') || n.classList.contains('overflow-x-auto'))) {
        n.style.overflow = 'visible';
      }
    });

    // مهم: العنصر لازم يبقى على الشاشة (مش left:-9999) وإلا html2canvas يطلع أبيض
    var wrap = document.createElement('div');
    wrap.setAttribute('dir', 'rtl');
    wrap.style.cssText = [
      'position:fixed',
      'left:0',
      'top:0',
      'width:1100px',
      'max-width:none',
      'background:#ffffff',
      'padding:16px',
      'font-family:Tahoma,Arial,sans-serif',
      'opacity:0.01',
      'pointer-events:none',
      'z-index:2147483646',
      'overflow:visible'
    ].join(';');
    wrap.style.setProperty('max-width', 'none', 'important');
    wrap.appendChild(clone);
    document.body.appendChild(wrap);

    var opt = {
      margin: opts.margin != null ? opts.margin : 8,
      filename: safeFilename(filename || 'export') + '.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: opts.scale || 1.25,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: Math.max(1100, clone.scrollWidth || 1100),
        windowHeight: Math.max(800, clone.scrollHeight || 800)
      },
      jsPDF: {
        unit: 'mm',
        format: 'a4',
        orientation: opts.orientation || 'landscape'
      },
      pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    // انتظار رسم العنصر قبل الالتقاط
    requestAnimationFrame(function () {
      setTimeout(function () {
        html2pdf()
          .set(opt)
          .from(wrap)
          .save()
          .then(function () {
            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
          })
          .catch(function (err) {
            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
            console.error(err);
            alert('فشل تصدير PDF. جرّب Excel.');
          });
      }, 150);
    });
  }

  global.AdminExport = {
    exportTableToExcel: exportTableToExcel,
    exportElementToPdf: exportElementToPdf,
    downloadTextFile: downloadTextFile
  };
})(window);
