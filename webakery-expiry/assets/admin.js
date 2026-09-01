(function ($) {
	'use strict';

	function nextIndex() {
		var max = -1;
		$('#wbe-batches-body tr').each(function () {
			$(this).find('input[name]').each(function () {
				var m = (this.name || '').match(/wbe_batches\[(\d+)\]/);
				if (m) {
					max = Math.max(max, parseInt(m[1], 10));
				}
			});
		});
		return max + 1;
	}

	$(document).on('click', '.wbe-add-batch', function (e) {
		e.preventDefault();
		var $tpl = $('#wbe-batch-tpl');
		if (!$tpl.length) {
			return;
		}
		var i = nextIndex();
		var $row = $tpl.clone().removeAttr('id');
		$row.find('[data-name]').each(function () {
			var name = $(this).attr('data-name');
			$(this).attr('name', 'wbe_batches[' + i + '][' + name + ']').removeAttr('data-name');
		});
		var wcPrice = $('#_regular_price').val() || $('#wbe-product-panel').attr('data-wc-price') || '';
		if (wcPrice && !$row.find('input[name$="[price]"]').val()) {
			$row.find('input[name$="[price]"]').val(wcPrice);
		}
		$('#wbe-batches-body').append($row);
	});

	$(document).on('click', '.wbe-remove-batch', function (e) {
		e.preventDefault();
		var $body = $('#wbe-batches-body');
		if ($body.find('tr').length <= 1) {
			$(this).closest('tr').find('input').val('');
			return;
		}
		$(this).closest('tr').remove();
	});

	$(document).on('click', '.wbe-add-point', function (e) {
		e.preventDefault();
		var $box = $('#wbe-alert-points');
		if (!$box.length) {
			return;
		}
		$box.append(
			'<p class="wbe-point-row">وقتی <input type="number" min="0" max="3650" class="small-text" name="wbe_settings[alert_points][]" value="14" /> روز تا انقضا مانده <button type="button" class="button-link wbe-remove-point">حذف</button></p>'
		);
	});

	$(document).on('click', '.wbe-remove-point', function (e) {
		e.preventDefault();
		var $box = $('#wbe-alert-points');
		if ($box.find('.wbe-point-row').length <= 1) {
			$box.find('input').val('7');
			return;
		}
		$(this).closest('.wbe-point-row').remove();
	});

	$(document).on('focus', '#_regular_price', function () {
		$(this).data('wbePrev', $(this).val());
	});

	$(document).on('change', '#_regular_price', function () {
		var val = $(this).val();
		var prev = $(this).data('wbePrev');
		var $rows = $('#wbe-batches-body tr');
		if (!$rows.length) {
			return;
		}
		if ($rows.length === 1) {
			$rows.find('input[name*="[price]"]').val(val);
			return;
		}
		var $match = $();
		$rows.each(function () {
			if (String($(this).find('input[name*="[price]"]').val()) === String(prev)) {
				$match = $match.add(this);
			}
		});
		if ($match.length === 1) {
			$match.find('input[name*="[price]"]').val(val);
		}
	});

	$(document).on('change', '#wbe-batches-body input[name*="[price]"]', function () {
		if ($('#wbe-batches-body tr').length === 1) {
			$('#_regular_price').val($(this).val());
		}
	});

	$(document).on('change', '#wbe-bulk-check-all', function () {
		$('.wbe-bulk-id').prop('checked', this.checked);
	});

	$(document).on('change', '.wbe-bulk-mode', function () {
		var $field = $(this).closest('.wbe-bulk-field, .input-text-wrap, label');
		var off = $(this).val() === 'none';
		$field.find('.wbe-bulk-value').prop('disabled', off);
	});

	var lastCheck = null;
	$(document).on('click', '.wbe-bulk-id', function (e) {
		if (e.shiftKey && lastCheck) {
			var $boxes = $('.wbe-bulk-row:visible .wbe-bulk-id');
			var from = $boxes.index(lastCheck);
			var to = $boxes.index(this);
			if (from > -1 && to > -1) {
				var lo = Math.min(from, to);
				var hi = Math.max(from, to);
				var on = this.checked;
				$boxes.slice(lo, hi + 1).prop('checked', on);
			}
		}
		lastCheck = this;
	});

	$(document).on('input change', '.wbe-bulk-row [data-field]', function () {
		var $i = $(this);
		var $tr = $i.closest('.wbe-bulk-row');
		if (String($i.val()) !== String($i.attr('data-orig'))) {
			$tr.addClass('is-dirty');
		}
		var dirty = false;
		$tr.find('[data-field]').each(function () {
			if (String($(this).val()) !== String($(this).attr('data-orig'))) {
				dirty = true;
				return false;
			}
		});
		$tr.toggleClass('is-dirty', dirty);
		if ($i.data('field') === 'sale') {
			$i.data('manual', true);
		}
		if ($i.data('field') === 'regular' || $i.data('field') === 'discount') {
			var $sale = $tr.find('[data-field="sale"]');
			if (!$sale.data('manual')) {
				var regular = parseFloat($tr.find('[data-field="regular"]').val()) || 0;
				var disc = parseFloat($tr.find('[data-field="discount"]').val()) || 0;
				disc = Math.max(0, Math.min(100, disc));
				var sale = disc > 0 ? Math.round(regular * (100 - disc) / 100) : regular;
				$sale.val(sale);
			}
		}
	});

	$(document).on('input', '#wbe-bulk-live', function () {
		var q = $.trim($(this).val()).toLowerCase();
		var n = 0;
		$('.wbe-bulk-row').each(function () {
			var $tr = $(this);
			var hay = String($tr.data('name') || '').toLowerCase();
			hay += ' ' + String($tr.find('[data-field="name"]').val() || '').toLowerCase();
			hay += ' ' + String($tr.find('[data-field="sku"]').val() || '').toLowerCase();
			var show = !q || hay.indexOf(q) !== -1;
			$(this).toggle(show);
			if (show) {
				n++;
			}
		});
		$('#wbe-bulk-count').text(n + ' محصول');
	});

	function changeAmount(current, mode, value) {
		current = parseFloat(current) || 0;
		value = parseFloat(value) || 0;
		var out = current;
		if (mode === 'set') {
			out = value;
		} else if (mode === 'inc') {
			out = current + value;
		} else if (mode === 'dec') {
			out = current - value;
		} else if (mode === 'inc_pct') {
			out = current * (1 + value / 100);
		} else if (mode === 'dec_pct') {
			out = current * (1 - value / 100);
		}
		if (out < 0) {
			out = 0;
		}
		return Math.round(out * 100) / 100;
	}

	function roundMoney(amount, mode) {
		if (mode === 'ceil') {
			return Math.ceil(amount);
		}
		if (mode === 'floor') {
			return Math.floor(amount);
		}
		if (mode === 'round') {
			return Math.round(amount);
		}
		return Math.round(amount * 100) / 100;
	}

	function setField($tr, field, val) {
		var $i = $tr.find('[data-field="' + field + '"]');
		if (!$i.length) {
			return;
		}
		$i.val(val);
		$tr.toggleClass('is-dirty', String($i.val()) !== String($i.attr('data-orig')) || $tr.hasClass('is-dirty'));
		var dirty = false;
		$tr.find('[data-field]').each(function () {
			if (String($(this).val()) !== String($(this).attr('data-orig'))) {
				dirty = true;
				return false;
			}
		});
		$tr.toggleClass('is-dirty', dirty);
	}

	function collectDirty() {
		var rows = {};
		$('.wbe-bulk-row.is-dirty:visible').each(function () {
			var $tr = $(this);
			var id = $tr.data('id');
			var row = {};
			$tr.find('[data-field]').each(function () {
				var $i = $(this);
				if (String($i.val()) !== String($i.attr('data-orig'))) {
					row[$i.data('field')] = $i.val();
				}
			});
			if (Object.keys(row).length) {
				rows[id] = row;
			}
		});
		return rows;
	}

	function chunkKeys(obj, size) {
		var keys = Object.keys(obj);
		var out = [];
		for (var i = 0; i < keys.length; i += size) {
			var part = {};
			keys.slice(i, i + size).forEach(function (k) {
				part[k] = obj[k];
			});
			out.push(part);
		}
		return out;
	}

	function showNotice(ok, text) {
		var $n = $('#wbe-bulk-notice');
		$n.removeClass('notice-success notice-error notice-warning').addClass(ok ? 'notice-success' : 'notice-error');
		$n.html('<p>' + text + '</p>').prop('hidden', false);
	}

	function markSaved(ids) {
		ids.forEach(function (id) {
			var $tr = $('.wbe-bulk-row[data-id="' + id + '"]');
			$tr.find('[data-field]').each(function () {
				$(this).attr('data-orig', $(this).val()).removeData('manual');
			});
			$tr.removeClass('is-dirty');
		});
	}

	function saveChunks(chunks, total, doneUpd, doneSkip) {
		var $bar = $('#wbe-bulk-bar');
		var $txt = $('#wbe-bulk-prog-txt');
		$('#wbe-bulk-progress').prop('hidden', false);
		if (!chunks.length) {
			$('#wbe-bulk-progress').prop('hidden', true);
			showNotice(true, doneUpd + ' محصول ذخیره شد' + (doneSkip ? ' — ' + doneSkip + ' رد شد' : '') + '.');
			return $.Deferred().resolve().promise();
		}
		var chunk = chunks.shift();
		var left = 0;
		chunks.forEach(function (c) {
			left += Object.keys(c).length;
		});
		var pct = total ? Math.round(((total - left) / total) * 100) : 100;
		$bar.css('width', pct + '%');
		$txt.text('ذخیره ' + (total - left) + ' از ' + total);
		return $.ajax({
			url: wbeBulk.ajax,
			method: 'POST',
			data: {
				action: 'wbe_bulk_save',
				nonce: wbeBulk.nonce,
				wbe_bulk_mode: 'rows',
				wbe_row: chunk
			}
		}).then(function (res) {
			var d = (res && res.data) ? res.data : {};
			doneUpd += d.updated ? parseInt(d.updated, 10) : 0;
			doneSkip += d.skipped ? parseInt(d.skipped, 10) : 0;
			markSaved(Object.keys(chunk));
			return saveChunks(chunks, total, doneUpd, doneSkip);
		}, function () {
			$('#wbe-bulk-progress').prop('hidden', true);
			showNotice(false, 'ذخیره ناقص ماند. دوباره تلاش کنید.');
		});
	}

	function applyToolbarToSelected() {
		var regularMode = $('#wbe_regular_mode').val() || 'none';
		var regularVal = $('input[name="wbe_regular_value"]').val();
		var saleMode = $('#wbe_sale_mode').val() || 'none';
		var saleVal = $('input[name="wbe_sale_value"]').val();
		var disc = $('#wbe_discount').val();
		var from = $('#wbe_sale_from').val();
		var to = $('#wbe_sale_to').val();
		var stockMode = $('#wbe_stock_mode').val() || 'none';
		var stockVal = $('input[name="wbe_stock_value"]').val();
		var expiry = $('#wbe_expiry').val();
		var round = $('#wbe_round').val();
		var clear = $('input[name="wbe_clear_sale"]').prop('checked');
		var has = (regularMode !== 'none' && regularVal !== '') ||
			(saleMode !== 'none' && saleVal !== '') ||
			disc !== '' || from !== '' || to !== '' ||
			(stockMode !== 'none' && stockVal !== '') ||
			expiry !== '' || clear;
		if (!has) {
			showNotice(false, 'حداقل یک فیلد نوار بالا را پر کنید.');
			return 0;
		}
		var n = 0;
		$('.wbe-bulk-row:visible').each(function () {
			var $tr = $(this);
			if (!$tr.find('.wbe-bulk-id').prop('checked')) {
				return;
			}
			n++;
			var regular = parseFloat($tr.find('[data-field="regular"]').val()) || 0;
			var sale = parseFloat($tr.find('[data-field="sale"]').val()) || 0;
			var stock = parseFloat($tr.find('[data-field="stock"]').val()) || 0;
			if (regularMode !== 'none' && regularVal !== '') {
				regular = roundMoney(changeAmount(regular, regularMode, regularVal), round);
				setField($tr, 'regular', regular);
			}
			if (clear) {
				setField($tr, 'discount', 0);
				setField($tr, 'sale', regular);
				setField($tr, 'from', '');
				setField($tr, 'to', '');
			} else if (saleMode !== 'none' && saleVal !== '') {
				sale = roundMoney(changeAmount(sale, saleMode, saleVal), round);
				setField($tr, 'sale', sale);
				$tr.find('[data-field="sale"]').data('manual', true);
				var d = regular > 0 && sale < regular ? Math.round(100 - (sale / regular) * 100) : 0;
				setField($tr, 'discount', d);
			} else if (disc !== '') {
				var dv = parseFloat(disc) || 0;
				setField($tr, 'discount', dv);
				setField($tr, 'sale', Math.round(regular * (100 - Math.max(0, Math.min(100, dv))) / 100));
			}
			if (from !== '') {
				setField($tr, 'from', from);
			}
			if (to !== '') {
				setField($tr, 'to', to);
			}
			if (stockMode !== 'none' && stockVal !== '') {
				setField($tr, 'stock', Math.max(0, Math.round(changeAmount(stock, stockMode, stockVal))));
			}
			if (expiry !== '') {
				setField($tr, 'expiry', expiry);
			}
		});
		return n;
	}

	$(document).on('submit', '#wbe-bulk-form', function (e) {
		if (typeof wbeBulk === 'undefined' || !wbeBulk.ajax) {
			return;
		}
		e.preventDefault();
		var mode = ($(document.activeElement).attr('name') === 'wbe_bulk_mode') ? $(document.activeElement).val() : 'rows';
		if (e.originalEvent && e.originalEvent.submitter) {
			mode = $(e.originalEvent.submitter).val() || mode;
		}
		if (mode === 'selected') {
			if (!applyToolbarToSelected()) {
				return;
			}
		}
		var dirty = collectDirty();
		var keys = Object.keys(dirty);
		if (!keys.length) {
			showNotice(false, 'سلول تغییریافته‌ای نیست.');
			return;
		}
		var size = (wbeBulk.chunk || 40);
		saveChunks(chunkKeys(dirty, size), keys.length, 0, 0);
	});
})(jQuery);
