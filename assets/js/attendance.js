/* Attendance Monitoring: QR scanning + manual check-in. */
function initAttendance(cfg) {
  'use strict';
  var resultEl = document.getElementById('result');
  var listEl = document.getElementById('search-results');
  var scanner = null, scanning = false, busy = false, lastCode = '', lastTime = 0;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function api(action, data) {
    data = data || {};
    data.action = action;
    data.conference_id = cfg.conference;
    return fetch(cfg.api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': cfg.csrf },
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, message: 'Server error (' + r.status + ')' }; });
    }).catch(function () { return { ok: false, message: 'Network error. Check your connection.' }; });
  }

  function beep(ok) {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var o = ctx.createOscillator(), g = ctx.createGain();
      o.frequency.value = ok ? 880 : 220; o.type = 'sine';
      g.gain.value = 0.15; o.connect(g); g.connect(ctx.destination);
      o.start(); o.stop(ctx.currentTime + (ok ? 0.15 : 0.35));
    } catch (e) { /* ignore */ }
  }

  function updateStats(s) {
    if (!s) return;
    document.getElementById('st-registered').textContent = s.registered;
    document.getElementById('st-in').textContent = s.checked_in;
    document.getElementById('st-remaining').textContent = s.remaining;
    document.getElementById('st-rate').textContent = s.rate;
  }

  function personHtml(p) {
    if (!p) return '';
    return '<div class="cell-title" style="font-size:1.05rem">' + esc(p.name) + '</div>' +
      '<div class="small muted">' + esc(p.id_number) + ' · ' + esc(p.reference_no) + ' · ' + esc(p.type) + '</div>' +
      '<div class="small muted">' + (p.course ? esc(p.course) : '—') + (p.year ? ' · ' + esc(p.year) : '') + '</div>';
  }

  function showResult(res, method, code) {
    var cls = res.ok ? 'ok' : (res.result === 'pending' || res.result === 'duplicate' ? 'warn' : 'bad');
    var icon = res.ok ? 'check-circle-fill' : (cls === 'warn' ? 'exclamation-triangle-fill' : 'x-octagon-fill');
    var html = '<div class="checkin-result ' + cls + ' mb-2"><div class="flex" style="align-items:flex-start;flex-wrap:nowrap">' +
      '<i class="bi bi-' + icon + '" style="font-size:1.6rem;color:' + (res.ok ? 'var(--success)' : cls === 'warn' ? 'var(--warning)' : 'var(--danger)') + '"></i>' +
      '<div class="grow"><strong>' + esc(res.message) + '</strong>' + (res.person ? '<div class="mt-1">' + personHtml(res.person) + '</div>' : '') + '</div></div>';
    if (res.result === 'pending' && res.person) {
      html += '<div class="mt-2"><button class="btn btn-success btn-sm" data-confirm-checkin="' + res.person.id + '" data-method="' + method + '" data-code="' + esc(code || '') + '"><i class="bi bi-check2-circle"></i> Confirm &amp; Check In</button></div>';
    }
    html += '</div>';
    resultEl.innerHTML = html;
    beep(res.ok);
  }

  function afterCheckin(res) {
    updateStats(res.stats);
    if (!res.ok || !res.person) return;
    var row = document.querySelector('#att-table tr[data-id="' + res.person.id + '"]');
    if (cfg.view === 'out' && row) row.remove();
    if (cfg.view === 'in' && !row) {
      var tb = document.querySelector('#att-table tbody');
      var empty = tb.querySelector('.table-empty');
      if (empty) empty.parentElement.remove();
      var tr = document.createElement('tr');
      tr.setAttribute('data-id', res.person.id);
      tr.innerHTML = '<td><a class="cell-title" href="' + esc(res.person.profile_url) + '">' + esc(res.person.name) + '</a><div class="cell-sub">' + esc(res.person.reference_no) + '</div></td>' +
        '<td>' + esc(res.person.id_number) + '</td><td class="hide-sm"><span class="badge badge-muted">' + esc(res.person.type) + '</span></td>' +
        '<td class="hide-sm small">' + esc(res.person.course || '—') + '<div class="cell-sub">' + esc(res.person.year) + '</div></td>' +
        '<td class="nowrap small">' + esc(res.person.checked_in_at) + '<div class="cell-sub">Just now</div></td>' +
        '<td class="text-right"><button class="btn btn-ghost btn-sm" data-undo="' + res.person.id + '"><i class="bi bi-arrow-counterclockwise"></i> Undo</button></td>';
      tr.style.background = '#F0FDF4';
      tb.insertBefore(tr, tb.firstChild);
    }
  }

  // ------------------------------------------------------------ QR scanning
  function onScan(text) {
    var now = Date.now();
    if (busy || (text === lastCode && now - lastTime < 4000)) return;
    lastCode = text; lastTime = now; busy = true;
    api('scan', { code: text }).then(function (res) {
      showResult(res, 'qr', text);
      afterCheckin(res);
      setTimeout(function () { busy = false; }, 1200);
    });
  }

  var toggleBtn = document.getElementById('scan-toggle');
  toggleBtn.addEventListener('click', function () {
    if (typeof Html5Qrcode === 'undefined') { alert('QR scanner library failed to load.'); return; }
    if (scanning) {
      scanner.stop().then(function () {
        scanning = false;
        toggleBtn.innerHTML = '<i class="bi bi-camera-video"></i> Start Camera';
        document.getElementById('reader-placeholder').classList.remove('hidden');
      });
      return;
    }
    if (!window.isSecureContext) {
      alert('The camera can only be used on localhost or an HTTPS website. Use manual check-in, or open this page over HTTPS.');
      return;
    }
    scanner = scanner || new Html5Qrcode('reader');
    document.getElementById('reader-placeholder').classList.add('hidden');
    scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 230, height: 230 } }, onScan, function () {})
      .then(function () {
        scanning = true;
        toggleBtn.innerHTML = '<i class="bi bi-stop-circle"></i> Stop Camera';
      })
      .catch(function (err) {
        document.getElementById('reader-placeholder').classList.remove('hidden');
        alert('Could not start the camera: ' + err);
      });
  });

  // ------------------------------------------------------------ manual search
  document.getElementById('search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var q = document.getElementById('search-q').value.trim();
    if (!q) return;
    listEl.innerHTML = '<div class="small muted">Searching…</div>';
    api('search', { query: q }).then(function (res) {
      if (!res.ok) { listEl.innerHTML = '<div class="small" style="color:var(--danger)">' + esc(res.message) + '</div>'; return; }
      if (!res.results.length) { listEl.innerHTML = '<div class="small muted">No registered participant matches “' + esc(q) + '” for this conference.</div>'; return; }
      listEl.innerHTML = res.results.map(function (p) {
        var action;
        if (p.checked_in_at) action = '<span class="badge badge-success"><i class="bi bi-check"></i> In at ' + esc(p.checked_in_time) + '</span>';
        else if (p.status === 'cancelled') action = '<span class="badge badge-danger">Cancelled</span>';
        else action = '<button class="btn btn-success btn-sm" data-checkin="' + p.id + '" data-status="' + p.status + '"><i class="bi bi-check-lg"></i> Check In</button>';
        return '<div class="checkin-result flex-between" style="padding:.7rem .9rem"><div>' + personHtml(p) +
          (p.status === 'pending' ? '<span class="badge badge-warning mt-1">Pending</span>' : '') + '</div>' + action + '</div>';
      }).join('');
    });
  });

  // ------------------------------------------------------------ buttons
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-checkin]');
    if (b) {
      var pending = b.getAttribute('data-status') === 'pending';
      if (pending && !confirm('This registration is still PENDING. Confirm it and check the participant in?')) return;
      b.disabled = true;
      api('checkin', { registration_id: +b.getAttribute('data-checkin'), confirm_pending: pending }).then(function (res) {
        showResult(res, 'manual');
        afterCheckin(res);
        if (res.ok || res.result === 'duplicate') {
          b.outerHTML = '<span class="badge badge-success"><i class="bi bi-check"></i> Checked in</span>';
        } else { b.disabled = false; }
      });
      return;
    }
    var c = e.target.closest('[data-confirm-checkin]');
    if (c) {
      c.disabled = true;
      var p = c.getAttribute('data-method') === 'qr'
        ? api('scan', { code: c.getAttribute('data-code'), confirm_pending: true })
        : api('checkin', { registration_id: +c.getAttribute('data-confirm-checkin'), confirm_pending: true });
      p.then(function (res) { showResult(res, 'manual'); afterCheckin(res); });
      return;
    }
    var u = e.target.closest('[data-undo]');
    if (u) {
      if (!confirm('Remove this check-in? The participant will be marked as not yet checked in.')) return;
      u.disabled = true;
      api('undo', { registration_id: +u.getAttribute('data-undo') }).then(function (res) {
        updateStats(res.stats);
        if (res.ok) { var row = u.closest('tr'); if (row) row.remove(); toast(res.message); }
        else { u.disabled = false; alert(res.message); }
      });
    }
  });
}
