(function () {
  var gate = document.getElementById('gate');
  if (!gate) return;

  var api = gate.getAttribute('data-api');
  var total = parseInt(gate.getAttribute('data-seconds'), 10) || 0;
  var countdown = gate.querySelector('[data-countdown]');
  var countEl = gate.querySelector('[data-count]');
  var bar = gate.querySelector('.bar');
  var label = gate.querySelector('[data-gate-label]');
  var unlockBtn = gate.querySelector('[data-unlock]');
  var errorEl = gate.querySelector('[data-gate-error]');
  var galleryCard = document.getElementById('galeria');
  var gallery = document.querySelector('[data-gallery]');
  var photos = [];
  var timer = null;

  function startCountdown(seconds) {
    var remaining = seconds;
    countdown.hidden = false;
    unlockBtn.hidden = true;
    label.textContent = 'Suas fotos serão liberadas em';
    countEl.textContent = remaining;
    bar.style.strokeDashoffset = '0';
    clearInterval(timer);
    timer = setInterval(function () {
      remaining -= 1;
      countEl.textContent = Math.max(0, remaining);
      bar.style.strokeDashoffset = String(100 - (remaining / seconds) * 100);
      if (remaining <= 0) {
        clearInterval(timer);
        countdown.hidden = true;
        label.textContent = 'Suas fotos estão liberadas';
        unlockBtn.hidden = false;
      }
    }, 1000);
  }

  function unlock() {
    unlockBtn.disabled = true;
    errorEl.hidden = true;
    fetch(api + '&acao=liberar', { method: 'POST', credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (data) { return { status: r.status, data: data }; }); })
      .then(function (res) {
        unlockBtn.disabled = false;
        if (res.data.ok) {
          photos = res.data.photos || [];
          renderGallery();
          unlockBtn.hidden = true;
          label.textContent = 'Fotos liberadas! Veja logo abaixo.';
          galleryCard.hidden = false;
          galleryCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else if (res.data.wait) {
          startCountdown(res.data.wait);
        } else {
          errorEl.textContent = res.data.error || 'Não foi possível carregar as fotos.';
          errorEl.hidden = false;
        }
      })
      .catch(function () {
        unlockBtn.disabled = false;
        errorEl.textContent = 'Falha de conexão. Tente novamente.';
        errorEl.hidden = false;
      });
  }

  function renderGallery() {
    gallery.innerHTML = '';
    var count = document.querySelector('[data-gallery-count]');
    if (count) count.textContent = photos.length;
    photos.forEach(function (p, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'thumb';
      btn.setAttribute('aria-label', 'Abrir foto ' + (i + 1));
      var img = document.createElement('img');
      img.src = p.t;
      img.loading = 'lazy';
      img.alt = '';
      btn.appendChild(img);
      btn.addEventListener('click', function () { openLightbox(i); });
      gallery.appendChild(btn);
    });
  }

  /* ---------- Visualizador ---------- */
  var lb = document.querySelector('[data-lightbox]');
  var lbImg = lb.querySelector('[data-lb-img]');
  var lbPos = lb.querySelector('[data-lb-pos]');
  var lbDownload = lb.querySelector('[data-lb-download]');
  var current = 0;

  function show(i) {
    current = (i + photos.length) % photos.length;
    var p = photos[current];
    lbImg.src = p.w;
    lbPos.textContent = (current + 1) + ' / ' + photos.length;
    lbDownload.href = p.o;
    lbDownload.setAttribute('download', p.n);
    [current + 1, current - 1].forEach(function (j) {
      var n = photos[(j + photos.length) % photos.length];
      if (n) { var pre = new Image(); pre.src = n.w; }
    });
  }

  function openLightbox(i) {
    show(i);
    lb.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lb.hidden = true;
    lbImg.removeAttribute('src');
    document.body.style.overflow = '';
  }

  lb.querySelector('[data-lb-close]').addEventListener('click', closeLightbox);
  lb.querySelector('[data-lb-prev]').addEventListener('click', function () { show(current - 1); });
  lb.querySelector('[data-lb-next]').addEventListener('click', function () { show(current + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });
  document.addEventListener('keydown', function (e) {
    if (lb.hidden) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
  var touchX = null;
  lb.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', function (e) {
    if (touchX === null) return;
    var dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    touchX = null;
  });

  unlockBtn.addEventListener('click', unlock);
  if (total > 0) {
    startCountdown(total);
  } else {
    unlock();
  }
})();
