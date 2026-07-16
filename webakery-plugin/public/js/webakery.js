(function () {
	"use strict";

	function setFeedback(el, message, type) {
		if (!el) {
			return;
		}
		el.hidden = false;
		el.textContent = message;
		el.classList.remove("is-success", "is-error");
		el.classList.add(type === "success" ? "is-success" : "is-error");
	}

	function initOrderForms() {
		var forms = document.querySelectorAll(".wbk-order");
		if (!forms.length || typeof webakeryData === "undefined") {
			return;
		}

		forms.forEach(function (form) {
			form.addEventListener("submit", function (event) {
				event.preventDefault();

				var submit = form.querySelector(".wbk-order__submit");
				var feedback = form.querySelector(".wbk-order__feedback");
				var name = form.querySelector('[name="name"]');
				var phone = form.querySelector('[name="phone"]');

				if (!name.value.trim() || !phone.value.trim()) {
					setFeedback(
						feedback,
						"نام و شماره تماس الزامی است.",
						"error"
					);
					return;
				}

				var body = new FormData(form);
				body.append("action", "webakery_submit_order");
				body.append("nonce", webakeryData.nonce);

				if (submit) {
					submit.disabled = true;
					submit.dataset.originalText = submit.textContent;
					submit.textContent = webakeryData.i18n.sending;
				}

				fetch(webakeryData.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: body,
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (payload && payload.success) {
							setFeedback(
								feedback,
								(payload.data && payload.data.message) ||
									webakeryData.i18n.success,
								"success"
							);
							form.reset();
						} else {
							setFeedback(
								feedback,
								(payload &&
									payload.data &&
									payload.data.message) ||
									webakeryData.i18n.error,
								"error"
							);
						}
					})
					.catch(function () {
						setFeedback(feedback, webakeryData.i18n.error, "error");
					})
					.finally(function () {
						if (submit) {
							submit.disabled = false;
							submit.textContent =
								submit.dataset.originalText || "ارسال سفارش";
						}
					});
			});
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initOrderForms);
	} else {
		initOrderForms();
	}
})();
