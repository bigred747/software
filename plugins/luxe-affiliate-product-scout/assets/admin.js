(function () {
	document.querySelectorAll("[data-laps-confirm]").forEach(function (btn) {
		btn.addEventListener("click", function (e) {
			var msg = btn.getAttribute("data-laps-confirm");
			if (msg && !window.confirm(msg)) {
				e.preventDefault();
			}
		});
	});
})();
