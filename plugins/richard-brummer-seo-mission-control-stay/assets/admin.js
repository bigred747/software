(function () {
  var btn = document.getElementById('rbsmc-stay-run-audit');
  if (!btn || !window.rbsmcStayAdmin) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Running…';
    var body = new URLSearchParams();
    body.set('action', 'rbsmc_stay_run_audit');
    body.set('nonce', rbsmcStayAdmin.nonce);
    fetch(rbsmcStayAdmin.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString()
    }).then(function () {
      window.location.reload();
    }).catch(function () {
      btn.disabled = false;
      btn.textContent = 'Run audit now';
    });
  });
})();
