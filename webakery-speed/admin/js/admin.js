(function () {
	"use strict";

	function feedback(message, isError) {
		var el = document.getElementById("wbs-feedback");
		if (!el) {
			return;
		}
		el.hidden = false;
		el.textContent = message;
		el.classList.toggle("is-error", !!isError);
	}

	function post(action) {
		var body = new FormData();
		body.append("action", action);
		body.append("nonce", wbsAdmin.nonce);
		return fetch(wbsAdmin.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			body: body,
		}).then(function (response) {
			return response.json();
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		var scanBtn = document.getElementById("wbs-run-scan");
		var applyBtn = document.getElementById("wbs-apply-safe");
		var disableBtn = document.getElementById("wbs-disable-all");

		if (scanBtn) {
			scanBtn.addEventListener("click", function () {
				scanBtn.disabled = true;
				feedback(wbsAdmin.i18n.scanning, false);
				post("wbs_scan")
					.then(function (payload) {
						if (payload && payload.success) {
							feedback(wbsAdmin.i18n.done, false);
							window.location.reload();
						} else {
							feedback(
								(payload && payload.data && payload.data.message) ||
									wbsAdmin.i18n.error,
								true
							);
						}
					})
					.catch(function () {
						feedback(wbsAdmin.i18n.error, true);
					})
					.finally(function () {
						scanBtn.disabled = false;
					});
			});
		}

		if (applyBtn) {
			applyBtn.addEventListener("click", function () {
				applyBtn.disabled = true;
				feedback(wbsAdmin.i18n.applying, false);
				post("wbs_apply_safe")
					.then(function (payload) {
						if (payload && payload.success) {
							feedback(payload.data.message || wbsAdmin.i18n.done, false);
							window.location.reload();
						} else {
							feedback(
								(payload && payload.data && payload.data.message) ||
									wbsAdmin.i18n.error,
								true
							);
						}
					})
					.catch(function () {
						feedback(wbsAdmin.i18n.error, true);
					})
					.finally(function () {
						applyBtn.disabled = false;
					});
			});
		}

		if (disableBtn) {
			disableBtn.addEventListener("click", function (event) {
				if (!window.confirm("همه اصلاحات خاموش شود؟")) {
					event.preventDefault();
					return;
				}
				event.preventDefault();
				disableBtn.disabled = true;
				feedback(wbsAdmin.i18n.disabling, false);
				post("wbs_disable_all")
					.then(function (payload) {
						if (payload && payload.success) {
							feedback(payload.data.message || wbsAdmin.i18n.done, false);
							window.location.reload();
						} else {
							feedback(wbsAdmin.i18n.error, true);
						}
					})
					.catch(function () {
						feedback(wbsAdmin.i18n.error, true);
					})
					.finally(function () {
						disableBtn.disabled = false;
					});
			});
		}
	});
})();
