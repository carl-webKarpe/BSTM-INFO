/* ==================================================================
   Shared front-end helpers
   ================================================================== */
(function () {
  'use strict';

  // ---------------------------------------------------------------- toast
  var toastEl;
  window.toast = function (msg) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      toastEl.setAttribute('role', 'status');
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
  };

  // ---------------------------------------------------------------- clipboard
  window.copyText = function (text) {
    function fallback() {
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); toast('Link copied to clipboard'); } catch (e) { prompt('Copy this link:', text); }
      document.body.removeChild(ta);
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () { toast('Link copied to clipboard'); }, fallback);
    } else { fallback(); }
  };

  // ---------------------------------------------------------------- QR codes
  /**
   * Draws a QR code on a canvas.
   * opts: { size, margin, caption, subcaption }
   */
  window.drawQR = function (canvas, text, opts) {
    opts = opts || {};
    var size = opts.size || 240, margin = opts.margin == null ? 16 : opts.margin;
    var qr = qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    var n = qr.getModuleCount();
    var cell = Math.max(1, Math.floor((size - margin * 2) / n));
    var qrSize = cell * n;
    var captionH = opts.caption ? (opts.subcaption ? 64 : 40) : 0;
    var w = qrSize + margin * 2;
    canvas.width = w;
    canvas.height = w + captionH;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#0F172A';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) ctx.fillRect(margin + c * cell, margin + r * cell, cell, cell);
      }
    }
    if (opts.caption) {
      ctx.fillStyle = '#172554';
      ctx.textAlign = 'center';
      ctx.font = 'bold ' + Math.max(12, Math.round(w / 22)) + 'px Inter, Arial, sans-serif';
      ctx.fillText(fitText(ctx, opts.caption, w - 20), w / 2, w + 18);
      if (opts.subcaption) {
        ctx.fillStyle = '#64748B';
        ctx.font = Math.max(10, Math.round(w / 30)) + 'px Inter, Arial, sans-serif';
        ctx.fillText(fitText(ctx, opts.subcaption, w - 20), w / 2, w + 44);
      }
    }
    return canvas;
  };

  function fitText(ctx, text, max) {
    if (ctx.measureText(text).width <= max) return text;
    while (text.length > 3 && ctx.measureText(text + '…').width > max) text = text.slice(0, -1);
    return text + '…';
  }

  window.downloadCanvas = function (canvas, filename) {
    var a = document.createElement('a');
    a.download = filename;
    a.href = canvas.toDataURL('image/png');
    document.body.appendChild(a);
    a.click();
    a.remove();
  };

  // Auto-render <canvas data-qr="text" data-size="200">
  function renderQrCanvases() {
    if (typeof qrcode === 'undefined') return;
    document.querySelectorAll('canvas[data-qr]').forEach(function (cv) {
      drawQR(cv, cv.getAttribute('data-qr'), {
        size: parseInt(cv.getAttribute('data-size') || '220', 10),
        margin: cv.hasAttribute('data-margin') ? parseInt(cv.getAttribute('data-margin'), 10) : 16
      });
    });
  }

  // ---------------------------------------------------------------- export (Excel / PDF)
  function slug(s) { return (s || 'export').replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '').toLowerCase(); }
  function stamp() {
    var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '_' + p(d.getHours()) + p(d.getMinutes());
  }

  /** Clone a table without .no-export cells, and with plain text content. */
  function cleanTable(table) {
    var t = table.cloneNode(true);
    t.querySelectorAll('.no-export').forEach(function (el) { el.remove(); });
    t.querySelectorAll('[data-export]').forEach(function (el) { el.textContent = el.getAttribute('data-export'); });
    return t;
  }

  function tableToAoa(table) {
    var rows = [];
    cleanTable(table).querySelectorAll('tr').forEach(function (tr) {
      if (tr.querySelector('.table-empty')) return;
      var row = [];
      tr.querySelectorAll('th,td').forEach(function (cell) {
        row.push(cell.textContent.replace(/\s+/g, ' ').trim());
      });
      rows.push(row);
    });
    return rows;
  }

  /** sections: [{title, head:[...], rows:[[...]]}] */
  window.exportXLSX = function (sections, filename) {
    if (typeof XLSX === 'undefined') { alert('Excel library failed to load.'); return; }
    var wb = XLSX.utils.book_new(), used = {};
    sections.forEach(function (s, i) {
      var aoa = [s.head].concat(s.rows);
      var ws = XLSX.utils.aoa_to_sheet(aoa);
      ws['!cols'] = s.head.map(function (h, ci) {
        var max = String(h).length;
        s.rows.forEach(function (r) { max = Math.max(max, String(r[ci] == null ? '' : r[ci]).length); });
        return { wch: Math.min(50, max + 2) };
      });
      var name = (s.title || ('Sheet ' + (i + 1))).replace(/[\\\/\?\*\[\]:]/g, ' ').slice(0, 28);
      while (used[name]) name = name.slice(0, 25) + ' ' + i;
      used[name] = 1;
      XLSX.utils.book_append_sheet(wb, ws, name);
    });
    XLSX.writeFile(wb, slug(filename) + '_' + stamp() + '.xlsx');
  };

  window.exportPDF = function (sections, filename, title, subtitle, orientation) {
    if (!window.jspdf) { alert('PDF library failed to load.'); return; }
    var doc = new window.jspdf.jsPDF({ orientation: orientation || 'landscape', unit: 'pt', format: 'a4' });
    var pageW = doc.internal.pageSize.getWidth();
    var org = document.body.getAttribute('data-org') || '';
    doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(23, 37, 84);
    doc.text(title || 'Report', 40, 42);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(100, 116, 139);
    doc.text((org ? org + '  •  ' : '') + (subtitle ? subtitle + '  •  ' : '') + 'Generated ' + new Date().toLocaleString(), 40, 58);
    var y = 74;
    sections.forEach(function (s, i) {
      if (sections.length > 1 || s.title) {
        if (y > doc.internal.pageSize.getHeight() - 90) { doc.addPage(); y = 50; }
        doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(23, 37, 84);
        doc.text(s.title || '', 40, y + 6);
        y += 14;
      }
      doc.autoTable({
        head: [s.head], body: s.rows, startY: y,
        margin: { left: 40, right: 40 },
        styles: { fontSize: 8, cellPadding: 4, overflow: 'linebreak' },
        headStyles: { fillColor: [37, 99, 235], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [248, 250, 252] }
      });
      y = doc.lastAutoTable.finalY + 26;
    });
    var pages = doc.internal.getNumberOfPages();
    for (var p = 1; p <= pages; p++) {
      doc.setPage(p); doc.setFontSize(8); doc.setTextColor(148, 163, 184);
      doc.text('Page ' + p + ' of ' + pages, pageW - 40, doc.internal.pageSize.getHeight() - 20, { align: 'right' });
    }
    doc.save(slug(filename) + '_' + stamp() + '.pdf');
  };

  /** Build export sections from <table data-export-title="..."> elements. */
  window.sectionsFromTables = function (tables) {
    return Array.prototype.map.call(tables, function (t) {
      var aoa = tableToAoa(t);
      return { title: t.getAttribute('data-export-title') || '', head: aoa[0] || [], rows: aoa.slice(1) };
    });
  };

  /** Fetch server JSON {title, subtitle, columns, rows} and export it. */
  window.exportFromUrl = function (url, format, btn) {
    if (btn) { btn.disabled = true; }
    url += (url.indexOf('?') === -1 ? '?' : '&') + 'for=' + encodeURIComponent(format);
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) {
        var sec = [{ title: '', head: d.columns, rows: d.rows }];
        if (format === 'xlsx') exportXLSX([{ title: d.title, head: d.columns, rows: d.rows }], d.filename || d.title);
        else exportPDF(sec, d.filename || d.title, d.title, d.subtitle);
      })
      .catch(function (e) { alert('Export failed: ' + e.message); })
      .finally(function () { if (btn) btn.disabled = false; });
  };

  // ---------------------------------------------------------------- UI wiring
  document.addEventListener('click', function (e) {
    // dropdowns
    var toggle = e.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown.open').forEach(function (d) {
      if (!toggle || d !== toggle.parentElement) d.classList.remove('open');
    });
    if (toggle) {
      e.preventDefault();
      toggle.parentElement.classList.toggle('open');
      return;
    }
    // copy buttons
    var copy = e.target.closest('[data-copy]');
    if (copy) { e.preventDefault(); copyText(copy.getAttribute('data-copy')); return; }
    // sidebar
    if (e.target.closest('.menu-toggle')) { document.body.classList.toggle('nav-open'); return; }
    if (e.target.classList.contains('backdrop')) { document.body.classList.remove('nav-open'); return; }
    // print
    if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); return; }
    // modal close
    if (e.target.closest('[data-close-modal]') || e.target.classList.contains('modal-backdrop')) {
      var m = e.target.closest('.modal-backdrop'); if (m) m.classList.remove('open');
    }
    var mo = e.target.closest('[data-open-modal]');
    if (mo) { e.preventDefault(); var el = document.getElementById(mo.getAttribute('data-open-modal')); if (el) el.classList.add('open'); }
  });

  // confirmation prompts on forms / buttons
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var sub = e.submitter;
    var msg = (sub && sub.getAttribute('data-confirm')) || f.getAttribute('data-confirm');
    if (msg && !confirm(msg)) { e.preventDefault(); return; }
    if (f.hasAttribute('data-once')) {
      setTimeout(function () { f.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = true; }); }, 0);
    }
  });

  // auto-submit selects
  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.dropdown.open,.modal-backdrop.open').forEach(function (d) { d.classList.remove('open'); });
      document.body.classList.remove('nav-open');
    }
  });

  document.addEventListener('DOMContentLoaded', renderQrCanvases);
})();
