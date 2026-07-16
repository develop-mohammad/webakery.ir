(function () {
	"use strict";

	var STORAGE_KEY = "hesabdar_order_draft_v1";
	var FIELD_NAMES = ["name", "phone", "product", "qty", "message"];

	function t(key, fallback) {
		if (
			typeof hesabdarData !== "undefined" &&
			hesabdarData.i18n &&
			hesabdarData.i18n[key]
		) {
			return hesabdarData.i18n[key];
		}
		return fallback;
	}

	function setFeedback(el, message, type) {
		if (!el) {
			return;
		}
		el.hidden = false;
		el.textContent = message;
		el.classList.remove("is-success", "is-error", "is-info");
		el.classList.add(
			type === "success"
				? "is-success"
				: type === "info"
					? "is-info"
					: "is-error"
		);
	}

	function readDraft() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return null;
			}
			var data = JSON.parse(raw);
			return data && typeof data === "object" ? data : null;
		} catch (error) {
			return null;
		}
	}

	function writeDraft(data) {
		try {
			window.localStorage.setItem(
				STORAGE_KEY,
				JSON.stringify(
					Object.assign({}, data, {
						savedAt: new Date().toISOString(),
					})
				)
			);
			return true;
		} catch (error) {
			return false;
		}
	}

	function clearDraft() {
		try {
			window.localStorage.removeItem(STORAGE_KEY);
		} catch (error) {
			// Ignore storage failures.
		}
	}

	function collectForm(form) {
		var data = {};
		FIELD_NAMES.forEach(function (name) {
			var field = form.querySelector('[name="' + name + '"]');
			data[name] = field ? String(field.value || "") : "";
		});
		return data;
	}

	function applyForm(form, data) {
		if (!data) {
			return;
		}
		FIELD_NAMES.forEach(function (name) {
			var field = form.querySelector('[name="' + name + '"]');
			if (field && typeof data[name] !== "undefined") {
				field.value = data[name];
			}
		});
	}

	function hasMeaningfulDraft(data) {
		if (!data) {
			return false;
		}
		return Boolean(
			(data.name && data.name.trim()) ||
				(data.phone && data.phone.trim()) ||
				(data.product && data.product.trim()) ||
				(data.message && data.message.trim()) ||
				(data.qty && String(data.qty) !== "1")
		);
	}

	function downloadDraftFile(data) {
		var blob = new Blob([JSON.stringify(data, null, 2)], {
			type: "application/json;charset=utf-8",
		});
		var url = URL.createObjectURL(blob);
		var link = document.createElement("a");
		var stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
		link.href = url;
		link.download = "hesabdar-order-draft-" + stamp + ".json";
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		URL.revokeObjectURL(url);
	}

	function initLocalSave(form) {
		var feedback = form.querySelector(".hsb-order__feedback");
		var saveBtn = form.querySelector(".hsb-order__save-local");
		var downloadBtn = form.querySelector(".hsb-order__download-local");
		var clearBtn = form.querySelector(".hsb-order__clear-local");
		var draftNote = form.querySelector(".hsb-order__draft-note");
		var saveTimer = null;

		var existing = readDraft();
		if (hasMeaningfulDraft(existing)) {
			applyForm(form, existing);
			if (draftNote) {
				draftNote.hidden = false;
				draftNote.textContent = t(
					"draftRestored",
					"پیش‌نویس ذخیره‌شده روی این لپ‌تاپ بازیابی شد."
				);
			}
		}

		function persist(showMessage) {
			var data = collectForm(form);
			if (!hasMeaningfulDraft(data)) {
				clearDraft();
				if (showMessage) {
					setFeedback(
						feedback,
						t("draftEmpty", "چیزی برای ذخیره روی این دستگاه نیست."),
						"error"
					);
				}
				return false;
			}

			var ok = writeDraft(data);
			if (showMessage) {
				setFeedback(
					feedback,
					ok
						? t(
								"draftSaved",
								"روی این لپ‌تاپ ذخیره شد."
							)
						: t(
								"draftSaveFailed",
								"ذخیره روی این دستگاه ممکن نشد."
							),
					ok ? "info" : "error"
				);
			}
			return ok;
		}

		form.addEventListener("input", function () {
			window.clearTimeout(saveTimer);
			saveTimer = window.setTimeout(function () {
				persist(false);
			}, 350);
		});

		form.addEventListener("change", function () {
			persist(false);
		});

		if (saveBtn) {
			saveBtn.addEventListener("click", function () {
				persist(true);
			});
		}

		if (downloadBtn) {
			downloadBtn.addEventListener("click", function () {
				var data = collectForm(form);
				if (!hasMeaningfulDraft(data)) {
					setFeedback(
						feedback,
						t("draftEmpty", "چیزی برای ذخیره روی این دستگاه نیست."),
						"error"
					);
					return;
				}
				writeDraft(data);
				downloadDraftFile(
					Object.assign({}, data, {
						savedAt: new Date().toISOString(),
						source: "hesabdar-order-form",
					})
				);
				setFeedback(
					feedback,
					t(
						"draftDownloaded",
						"فایل پیش‌نویس روی لپ‌تاپ دانلود شد."
					),
					"info"
				);
			});
		}

		if (clearBtn) {
			clearBtn.addEventListener("click", function () {
				clearDraft();
				form.reset();
				if (draftNote) {
					draftNote.hidden = true;
				}
				setFeedback(
					feedback,
					t("draftCleared", "پیش‌نویس این دستگاه پاک شد."),
					"info"
				);
			});
		}

		return {
			clear: clearDraft,
			persist: persist,
		};
	}

	function initOrderForms() {
		var forms = document.querySelectorAll(".hsb-order");
		if (!forms.length || typeof hesabdarData === "undefined") {
			return;
		}

		forms.forEach(function (form) {
			var localSave = initLocalSave(form);

			form.addEventListener("submit", function (event) {
				event.preventDefault();

				var submit = form.querySelector(".hsb-order__submit");
				var feedback = form.querySelector(".hsb-order__feedback");
				var name = form.querySelector('[name="name"]');
				var phone = form.querySelector('[name="phone"]');
				var draftNote = form.querySelector(".hsb-order__draft-note");

				if (!name.value.trim() || !phone.value.trim()) {
					setFeedback(
						feedback,
						t("required", "نام و شماره تماس الزامی است."),
						"error"
					);
					return;
				}

				var body = new FormData(form);
				body.append("action", "hesabdar_submit_order");
				body.append("nonce", hesabdarData.nonce);

				if (submit) {
					submit.disabled = true;
					submit.dataset.originalText = submit.textContent;
					submit.textContent = hesabdarData.i18n.sending;
				}

				fetch(hesabdarData.ajaxUrl, {
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
									hesabdarData.i18n.success,
								"success"
							);
							form.reset();
							localSave.clear();
							if (draftNote) {
								draftNote.hidden = true;
							}
						} else {
							localSave.persist(false);
							setFeedback(
								feedback,
								(payload &&
									payload.data &&
									payload.data.message) ||
									hesabdarData.i18n.error,
								"error"
							);
						}
					})
					.catch(function () {
						localSave.persist(false);
						setFeedback(feedback, hesabdarData.i18n.error, "error");
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
