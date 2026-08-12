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
  if (cfg.hideSiteKit) {
    body.classList.add('luxe-op-hide-sitekit');
    var needles = [
      'reader revenue manager',
      'get better quality leads',
      'connect adsense',
      'adsense is disconnected'
    ];
    var nodes = document.querySelectorAll('section, article, div, aside');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (el.children && el.children.length > 12) continue;
      var t = (el.textContent || '').toLowerCase();
      if (t.length < 40 || t.length > 900) continue;
      for (var n = 0; n < needles.length; n++) {
        if (t.indexOf(needles[n]) !== -1) {
          el.classList.add('luxe-op-sitekit-upsell');
          break;
        }
      }
    }
  }

  var btn = document.getElementById('luxe-op-toggle-notices');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var hidden = body.classList.toggle('luxe-op-hide-notices');
    btn.textContent = hidden ? 'Show hidden notices' : 'Hide plugin notices';
  });
})();
