(function () {
  var cfg = window.rbsmcStay || {};
  function hideFiller() {
    if (!cfg.filler || !cfg.utility) return;
    var nodes = document.querySelectorAll('p, div, section, article, li');
    for (var i = 0; i < nodes.length; i++) {
      var t = (nodes[i].textContent || '');
      if (/expanded automatically|Expanded Analysis:/i.test(t)) {
        nodes[i].classList.add('rbsmc-stay-ai-filler');
        nodes[i].style.display = 'none';
      }
    }
  }
  function progress() {
    if (!cfg.progress) return;
    var bar = document.querySelector('.rbsmc-stay-progress span');
    if (!bar) return;
    function tick() {
      var doc = document.documentElement;
      var max = doc.scrollHeight - window.innerHeight;
      var pct = max > 0 ? Math.min(100, Math.round((window.scrollY / max) * 100)) : 0;
      bar.style.width = pct + '%';
    }
    window.addEventListener('scroll', tick, { passive: true });
    tick();
  }
  function beacon() {
    if (!cfg.beacon) return;
    var start = Date.now();
    var sent = false;
    var maxScroll = 0;
    function scrollPct() {
      var doc = document.documentElement;
      var max = doc.scrollHeight - window.innerHeight;
      return max > 0 ? Math.min(100, Math.round((window.scrollY / max) * 100)) : 0;
    }
    window.addEventListener('scroll', function () {
      maxScroll = Math.max(maxScroll, scrollPct());
    }, { passive: true });
    function send() {
      if (sent) return;
      sent = true;
      var body = new URLSearchParams();
      body.set('action', 'rbsmc_stay_dwell');
      body.set('nonce', cfg.nonce);
      body.set('seconds', String(Math.round((Date.now() - start) / 1000)));
      body.set('scroll', String(maxScroll));
      body.set('path', location.pathname || '/');
      if (navigator.sendBeacon) {
        navigator.sendBeacon(cfg.ajax, body);
      }
    }
    window.addEventListener('pagehide', send);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') send();
    });
  }
  function neutralize() {
    if (!cfg.neutralize) return;
    try {
      window.onpopstate = null;
    } catch (e) {}
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      hideFiller();
      progress();
      beacon();
      neutralize();
    });
  } else {
    hideFiller();
    progress();
    beacon();
    neutralize();
  }
})();
