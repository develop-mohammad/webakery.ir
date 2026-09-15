(function ($) {
	'use strict';

	var FA = '۰۱۲۳۴۵۶۷۸۹';

	function toFa(str) {
		return String(str).replace(/\d/g, function (d) {
			return FA[d];
		});
	}

	function pad(n) {
		n = Math.max(0, parseInt(n, 10) || 0);
		var s = String(n);
		if (s.length < 2) {
			s = '0' + s;
		}
		return toFa(s);
	}

	function tick(el) {
		var end = parseInt(el.getAttribute('data-end'), 10);
		if (!end) {
			return false;
		}
		var left = Math.max(0, end - Math.floor(Date.now() / 1000));
		if (left <= 0) {
			el.setAttribute('hidden', 'hidden');
			return false;
		}
		var d = el.querySelector('[data-unit="d"] .wbe-countdown__num');
		var h = el.querySelector('[data-unit="h"] .wbe-countdown__num');
		var m = el.querySelector('[data-unit="m"] .wbe-countdown__num');
		var s = el.querySelector('[data-unit="s"] .wbe-countdown__num');
		if (d) {
			d.textContent = pad(Math.floor(left / 86400));
		}
		if (h) {
			h.textContent = pad(Math.floor((left % 86400) / 3600));
		}
		if (m) {
			m.textContent = pad(Math.floor((left % 3600) / 60));
		}
		if (s) {
			s.textContent = pad(left % 60);
		}
		return true;
	}

	var live = [];

	function scanCountdowns(root) {
		root = root || document;
		var nodes = root.querySelectorAll
			? root.querySelectorAll('.wbe-countdown[data-end]')
			: [];
		for (var i = 0; i < nodes.length; i++) {
			if (tick(nodes[i]) && live.indexOf(nodes[i]) === -1) {
				live.push(nodes[i]);
			}
		}
	}

	function run() {
		scanCountdowns(document);
		if (!live.length) {
			return;
		}
		setInterval(function () {
			var next = [];
			for (var j = 0; j < live.length; j++) {
				if (tick(live[j])) {
					next.push(live[j]);
				}
			}
			live = next;
		}, 1000);
	}

	function fillVariationSlot(variationId) {
		var slot = document.getElementById('wbe-expiry-slot');
		if (!slot) {
			return;
		}
		var map = (window.wbeFront && wbeFront.variations) || {};
		var html = variationId && map[String(variationId)] ? map[String(variationId)] : '';
		slot.innerHTML = html || '';
		if (html) {
			scanCountdowns(slot);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}

	if ($ && $.fn) {
		$(document.body).on('found_variation', 'form.variations_form', function (e, variation) {
			var id = variation && variation.variation_id ? variation.variation_id : 0;
			fillVariationSlot(id);
		});
		$(document.body).on('reset_data hide_variation', 'form.variations_form', function () {
			fillVariationSlot(0);
		});
	}
})(window.jQuery);
