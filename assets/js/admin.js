(function () {
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var toastEl = document.getElementById('toast');
  var toastTimer;

  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
  }

  function post(data) {
    var body = new URLSearchParams(data);
    body.append('_csrf', csrf);
    return fetch('api.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf },
      body: body,
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function idFor(el) {
    var holder = el.closest('[data-id]');
    return holder ? holder.getAttribute('data-id') : null;
  }

  function markSent(el) {
    var id = idFor(el);
    if (!id) return;
    post({ action: 'mark_sent', id: id }).then(function (res) {
      if (!res.ok) return;
      var row = el.closest('tr');
      var label = row ? row.querySelector('[data-sent-label]') : document.querySelector('dd[data-sent-label]');
      if (!label) return;
      if (row) {
        label.textContent = 'Enviado';
        label.classList.add('dot-ok');
      } else if (label.textContent.trim() === '—') {
        label.textContent = res.sent_at;
      }
    }).catch(function () {});
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
      document.body.removeChild(ta);
    });
  }

  document.addEventListener('click', function (ev) {
    var copyBtn = ev.target.closest('[data-copy]');
    if (copyBtn) {
      ev.preventDefault();
      copyText(copyBtn.getAttribute('data-copy')).then(function () {
        toast('Copiado!');
      }, function () {
        window.prompt('Copie o texto abaixo:', copyBtn.getAttribute('data-copy'));
      });
      if (copyBtn.hasAttribute('data-mark-sent')) markSent(copyBtn);
      return;
    }

    var sendLink = ev.target.closest('a[data-mark-sent]');
    if (sendLink) {
      markSent(sendLink);
      return;
    }

    var checkinBtn = ev.target.closest('[data-checkin]');
    if (checkinBtn) {
      var li = checkinBtn.closest('li');
      checkinBtn.disabled = true;
      post({ action: 'toggle_checkin', id: idFor(checkinBtn) }).then(function (res) {
        checkinBtn.disabled = false;
        if (!res.ok) return;
        li.classList.toggle('is-in', res.checked_in);
        checkinBtn.textContent = res.checked_in ? 'Presente · ' + res.time : 'Fazer check-in';
        var counter = document.querySelector('[data-present]');
        if (counter) counter.textContent = document.querySelectorAll('.checkin-list li.is-in').length;
        toast(res.checked_in ? 'Check-in registrado' : 'Check-in desfeito');
      }).catch(function () { checkinBtn.disabled = false; toast('Erro de conexão'); });
    }
  });

  // Selecionar todos
  var all = document.querySelector('[data-check-all]');
  if (all) {
    all.addEventListener('change', function () {
      document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
    });
  }

  // Confirmação de exclusão em lote
  var bulkForm = document.getElementById('bulk-form');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function (ev) {
      var action = bulkForm.querySelector('select[name="bulk"]').value;
      var n = bulkForm.querySelectorAll('input[name="ids[]"]:checked').length;
      if (action === 'delete' && !confirm('Excluir ' + n + ' convidado(s)? Esta ação não pode ser desfeita.')) {
        ev.preventDefault();
      }
    });
  }

  // Busca instantânea do check-in
  var live = document.querySelector('[data-live-search]');
  if (live) {
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var items = Array.prototype.slice.call(document.querySelectorAll('.checkin-list li'));
    items.forEach(function (li) { li._s = norm(li.getAttribute('data-search') || ''); });
    live.addEventListener('input', function () {
      var q = norm(live.value.trim());
      items.forEach(function (li) { li.hidden = q !== '' && li._s.indexOf(q) === -1; });
    });
  }
})();
