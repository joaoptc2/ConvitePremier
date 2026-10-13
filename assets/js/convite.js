(function () {
  var box = document.querySelector('[data-companions]');
  if (!box) return;
  var select = box.querySelector('select');
  var inputs = box.querySelectorAll('.companion-name');
  function sync() {
    var n = parseInt(select.value, 10) || 0;
    inputs.forEach(function (input) {
      var show = parseInt(input.getAttribute('data-idx'), 10) <= n;
      input.hidden = !show;
    });
  }
  select.addEventListener('change', sync);
  sync();
})();
