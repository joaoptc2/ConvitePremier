(function () {
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var zone = document.querySelector('[data-dropzone]');
  var input = document.querySelector('[data-file-input]');
  var grid = document.querySelector('[data-photo-grid]');
  var status = document.querySelector('[data-upload-status]');
  var bar = document.querySelector('[data-upload-bar]');
  var text = document.querySelector('[data-upload-text]');
  var errors = document.querySelector('[data-upload-errors]');
  var limit = parseInt(zone.getAttribute('data-limit'), 10) || 0;
  var busy = false;

  function addError(msg) {
    var li = document.createElement('li');
    li.textContent = msg;
    errors.appendChild(li);
  }

  function addThumb(id, src) {
    var empty = document.querySelector('[data-empty]');
    if (empty) empty.remove();
    var fig = document.createElement('figure');
    fig.className = 'photo-item';
    fig.setAttribute('data-id', id);
    fig.innerHTML = '<a target="_blank" rel="noopener"><img alt=""></a>' +
      '<button type="button" class="photo-del" data-photo-delete title="Excluir foto" aria-label="Excluir foto">&times;</button>';
    fig.querySelector('img').src = src;
    fig.querySelector('a').href = src.replace('/thumb/', '/web/');
    grid.appendChild(fig);
  }

  function uploadAll(files) {
    if (busy) return;
    files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type) || /\.(jpe?g|png|webp)$/i.test(f.name); });
    if (!files.length) return;
    busy = true;
    status.hidden = false;
    errors.innerHTML = '';
    var done = 0, ok = 0;
    window.onbeforeunload = function () { return 'O envio das fotos ainda não terminou.'; };

    function next() {
      if (done >= files.length) {
        busy = false;
        window.onbeforeunload = null;
        text.textContent = ok + ' de ' + files.length + ' foto(s) enviadas.' + (ok < files.length ? ' Veja os erros abaixo.' : '');
        input.value = '';
        return;
      }
      var f = files[done];
      text.textContent = 'Enviando ' + (done + 1) + ' de ' + files.length + ': ' + f.name;
      if (limit && f.size > limit) {
        addError(f.name + ': maior que o limite do servidor.');
        done++;
        bar.style.width = (done / files.length * 100) + '%';
        return next();
      }
      var fd = new FormData();
      fd.append('op', 'upload');
      fd.append('_csrf', csrf);
      fd.append('photo', f);
      fetch('photos.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) { ok++; addThumb(res.id, res.thumb); }
          else addError(f.name + ': ' + (res.error || 'erro'));
        })
        .catch(function () { addError(f.name + ': falha no envio (arquivo grande demais ou conexão).'); })
        .then(function () {
          done++;
          bar.style.width = (done / files.length * 100) + '%';
          next();
        });
    }
    next();
  }

  input.addEventListener('change', function () { uploadAll(input.files); });
  ['dragenter', 'dragover'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
  });
  zone.addEventListener('drop', function (e) { uploadAll(e.dataTransfer.files); });

  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-photo-delete]');
    if (!btn || !confirm('Excluir esta foto?')) return;
    var fig = btn.closest('[data-id]');
    var fd = new FormData();
    fd.append('op', 'delete');
    fd.append('id', fig.getAttribute('data-id'));
    fd.append('_csrf', csrf);
    fetch('photos.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (res) { if (res.ok) fig.remove(); });
  });
})();
