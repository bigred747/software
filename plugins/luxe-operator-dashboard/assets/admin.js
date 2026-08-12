(function () {
  var cfg = window.luxeOpAdmin || {};
  var body = document.body;
  if (!body) return;

  if (cfg.hideNotices) {
    body.classList.add('luxe-op-hide-notices');
  }
  if (cfg.hideBlog) {
    body.classList.add('luxe-op-hide-blog');
  }

  var btn = document.getElementById('luxe-op-toggle-notices');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var hidden = body.classList.toggle('luxe-op-hide-notices');
    btn.textContent = hidden ? 'Show hidden notices' : 'Hide plugin notices';
  });
})();
