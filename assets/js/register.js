/* Registration form: show only relevant fields + friendly client-side validation.
   (The server validates everything again – this is only for convenience.) */
(function () {
  'use strict';
  var form = document.getElementById('reg-form');
  if (!form) {
    var es = document.getElementById('error-summary');
    if (es) es.focus();
    return;
  }

  var wrap = document.getElementById('type-dependent');
  var submitBar = form.querySelector('.sticky-actions');

  function currentType() {
    var r = form.querySelector('input[name="type"]:checked') || form.querySelector('input[type="hidden"][name="type"]');
    return r ? r.value : '';
  }

  function inList(attr, type) {
    return attr.split(',').map(function (s) { return s.trim(); }).indexOf(type) !== -1;
  }

  function applyType() {
    var type = currentType();
    var waiting = !type;
    wrap.classList.toggle('hidden', waiting);
    submitBar.classList.toggle('hidden', waiting);
    if (waiting) return;

    form.querySelectorAll('[data-show]').forEach(function (el) {
      el.classList.toggle('hidden', !inList(el.getAttribute('data-show'), type));
    });
    form.querySelectorAll('[data-req-for]').forEach(function (el) {
      el.classList.toggle('hidden', !inList(el.getAttribute('data-req-for'), type));
    });
    form.querySelectorAll('[data-required-for]').forEach(function (el) {
      el.required = inList(el.getAttribute('data-required-for'), type);
    });
    form.querySelectorAll('[data-label-' + type + ']').forEach(function (el) {
      el.textContent = el.getAttribute('data-label-' + type);
    });
  }

  function applyOther(sel) {
    var target = document.getElementById(sel.getAttribute('data-other'));
    if (!target) return;
    var show = sel.value === 'other' && !sel.closest('.hidden');
    target.classList.toggle('hidden', !show);
    var input = target.querySelector('input');
    if (input) input.required = show;
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'type') {
      applyType();
      form.querySelectorAll('select[data-other]').forEach(applyOther);
    }
    if (e.target.matches('select[data-other]')) applyOther(e.target);
    if (e.target.classList.contains('is-invalid')) clearError(e.target);
  });
  form.addEventListener('input', function (e) {
    if (e.target.classList.contains('is-invalid')) clearError(e.target);
  });

  function clearError(el) {
    el.classList.remove('is-invalid');
    var f = el.closest('.field');
    var msg = f && f.querySelector('.error-text.js');
    if (msg) msg.remove();
  }

  function setError(el, msg) {
    el.classList.add('is-invalid');
    var f = el.closest('.field');
    if (!f || f.querySelector('.error-text')) return;
    var d = document.createElement('div');
    d.className = 'error-text js';
    d.innerHTML = '<i class="bi bi-exclamation-circle"></i> ';
    d.appendChild(document.createTextNode(msg));
    f.appendChild(d);
  }

  form.addEventListener('submit', function (e) {
    var first = null;
    form.querySelectorAll('.error-text.js').forEach(function (n) { n.remove(); });

    if (!currentType()) {
      e.preventDefault();
      alert('Please choose how you are registering (Student, Faculty or Guest).');
      return;
    }

    form.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (el.type === 'hidden' || el.closest('.hidden')) return;
      var v = (el.value || '').trim();
      var label = (el.closest('.field') && el.closest('.field').querySelector('label'));
      var name = label ? label.textContent.replace(/[*]|\(optional\)/g, '').trim() : 'This field';
      var msg = '';
      if (el.type === 'checkbox') {
        if (el.required && !el.checked) msg = 'Please tick this box to continue.';
      } else if (el.required && !v) {
        msg = name + ' is required.';
      } else if (el.name === 'contact_number' && v) {
        var n = v.replace(/[\s\-().]/g, '');
        if (!/^(?:\+?63|0)?9\d{9}$/.test(n) && !/^\+\d{8,15}$/.test(n)) msg = 'Enter a valid mobile number, e.g. 09171234567.';
      } else if (el.name === 'address' && v && v.length < 8) {
        msg = 'Please enter your complete address.';
      }
      if (msg) {
        setError(el, msg);
        if (!first) first = el;
      }
    });

    if (first) {
      e.preventDefault();
      first.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(function () { first.focus({ preventScroll: true }); }, 300);
    }
  });

  applyType();
  form.querySelectorAll('select[data-other]').forEach(applyOther);

  var es = document.getElementById('error-summary');
  if (es) {
    var bad = form.querySelector('.is-invalid');
    (bad || es).scrollIntoView({ block: 'center' });
  }
})();
