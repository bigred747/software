(function () {
  var btn = document.getElementById('rbsmc-run-audit');
  if (!btn || !window.rbsmcAdmin) return;
  btn.addEventListener('click', function () {
    btn.disabled = true;
    var body = new URLSearchParams();
    body.set('action', 'rbsmc_run_audit');
    body.set('nonce', rbsmcAdmin.nonce);
    fetch(rbsmcAdmin.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString()
    }).then(function () { window.location.reload(); }).catch(function () { btn.disabled = false; });
  });
})();
