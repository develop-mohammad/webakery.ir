(function ($) {
	'use strict';

	function nextIndex($body) {
		var max = -1;
		$body.find('tr').each(function () {
			$(this).find('input[name]').each(function () {
				var m = (this.name || '').match(/\[reserve\]\[(\d+)\]/);
				if (!m) {
					m = (this.name || '').match(/wbe_reserve\[(\d+)\]/);
				}
				if (!m) {
					m = (this.name || '').match(/wbe_batches\[(\d+)\]/);
				}
				if (m) {
					max = Math.max(max, parseInt(m[1], 10));
				}
			});
		});
		return max + 1;
	}

	$(document).on('click', '.wbe-add-batch', function (e) {
		e.preventDefault();
		var $panel = $(this).closest('.wbe-product-panel');
		var $box = $(this).closest('.wbe-reserve-box');
		var $body = $box.find('.wbe-batches-body').first();
		var $tpl = $box.find('.wbe-batch-tpl').first();
		if (!$tpl.length) {
			$tpl = $('#wbe-batch-tpl');
		}
		if (!$body.length || !$tpl.length) {
			return;
		}
		$body.find('tr.wbe-reserve-empty').remove();
		var i = nextIndex($body);
		var $row = $tpl.clone().removeAttr('id').removeClass('wbe-batch-tpl');
		var vid = $panel.attr('data-variation-id') || $panel.data('variationId');
		var loop = $panel.data('loop');
		var prefix;
		if (vid !== undefined && vid !== null && String(vid) !== '' && String(vid) !== '0') {
			prefix = 'wbe_var[' + vid + '][reserve]';
		} else if (loop !== undefined && loop !== null && String(loop) !== '') {
			prefix = 'wbe_var[' + loop + '][reserve]';
		} else if ($box.closest('.wbe-reserve-box').length) {
			prefix = 'wbe_reserve';
		} else {
			prefix = 'wbe_batches';
		}
		$row.find('[data-name]').each(function () {
			var name = $(this).attr('data-name');
			$(this).attr('name', prefix + '[' + i + '][' + name + ']').removeAttr('data-name');
		});
		var wcPrice =
			$panel.find('.wbe-active-box .wbe-batch-price').first().val() ||
			$panel.attr('data-wc-price') ||
			$('#_regular_price').val() ||
			'';
		if (wcPrice && !$row.find('input[name$="[price]"]').val()) {
			$row.find('input[name$="[price]"]').val(wcPrice);
		}
		$body.append($row);
	});

	$(document).on('click', '.wbe-remove-batch', function (e) {
		e.preventDefault();
		var $body = $(this).closest('.wbe-batches-body, #wbe-batches-body');
		$(this).closest('tr').remove();
		if (!$body.find('tr.wbe-batch-row').not('.wbe-reserve-empty').length) {
			$body.html('<tr class="wbe-batch-row is-reserve wbe-reserve-empty"><td colspan="5" class="wbe-muted">هنوز بچ رزرو ندارید — «افزودن بچ رزرو» را بزنید.</td></tr>');
		}
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
		var $active = $('#wbe_active_price');
		if ($active.length && (String($active.val()) === String(prev) || !$active.val())) {
			$active.val(val);
			return;
		}
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

	function syncActiveToWc($panel) {
		if (!$panel || !$panel.length) {
			return;
		}
		var $row = $panel.closest('.inline-edit-row');
		var price = $panel.find('#wbe_active_price').val();
		var sale = $panel.find('#wbe_active_sale').val();
		var stock = $panel.find('#wbe_active_stock').val();
		if ($row.length) {
			$row.find('input[name="_regular_price"]').val(price);
			$row.find('input[name="_sale_price"]').val(sale);
			$row.find('input[name="_stock"]').val(stock);
		} else {
			$('#_regular_price').val(price);
			if ($('#_sale_price').length) {
				$('#_sale_price').val(sale);
			}
			if ($('#_stock').length) {
				$('#_stock').val(stock);
			}
		}
	}

	$(document).on('change', '#wbe_active_price, #wbe_active_sale, #wbe_active_stock', function () {
		var $panel = $(this).closest('.wbe-product-panel');
		syncActiveToWc($panel);
		if ($(this).is('#wbe_active_price')) {
			syncSaleFromDisc($panel.find('.wbe-active-box'));
		}
	});

	$(document).on('change', '#wbe-batches-body input[name*="[price]"]', function () {
		if ($('#wbe-batches-body tr').length === 1 && !$('#wbe_active_price').length) {
			$('#_regular_price').val($(this).val());
		}
		syncSaleFromDisc($(this).closest('tr'));
	});

	function parseNum(v) {
		if (v == null) {
			return NaN;
		}
		v = String(v)
			.replace(/[۰-۹]/g, function (d) {
				return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
			})
			.replace(/[٠-٩]/g, function (d) {
				return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);
			})
			.replace(/[,%٬،\s]/g, '');
		var n = parseFloat(v);
		return isNaN(n) ? NaN : n;
	}

	function syncSaleFromDisc($ctx) {
		var $price = $ctx.find('.wbe-batch-price, #wbe_active_price, input[name*="[price]"]').first();
		var $disc = $ctx.find('.wbe-disc, #wbe_active_discount').first();
		var $sale = $ctx.find('.wbe-batch-sale, #wbe_active_sale').first();
		if (!$sale.length) {
			return;
		}
		var price = parseNum($price.val());
		var disc = parseNum($disc.val());
		var discEmpty = String($disc.val() || '').trim() === '' || disc === 0 || isNaN(disc);
		if (!(price > 0)) {
			return;
		}
		if (discEmpty || disc >= 100) {
			$sale.val('');
			return;
		}
		$sale.val(Math.round((price * (100 - disc)) / 100));
	}

	function syncDiscFromSale($ctx) {
		var $price = $ctx.find('.wbe-batch-price, #wbe_active_price, input[name*="[price]"]').first();
		var $disc = $ctx.find('.wbe-disc, #wbe_active_discount').first();
		var $sale = $ctx.find('.wbe-batch-sale, #wbe_active_sale').first();
		if (!$disc.length) {
			return;
		}
		var price = parseNum($price.val());
		var sale = parseNum($sale.val());
		if (!(price > 0) || !(sale > 0) || sale >= price) {
			if (String($sale.val() || '').trim() === '' || sale === 0 || isNaN(sale) || sale >= price) {
				$disc.val('');
			}
			return;
		}
		var disc = Math.round((1 - sale / price) * 100);
		$disc.val(Math.max(0, Math.min(100, disc)));
	}

	$(document).on('change input', '.wbe-product-panel .wbe-disc, #wbe_active_discount, .wbe-batches-body .wbe-disc, #wbe-batches-body .wbe-disc', function () {
		syncSaleFromDisc($(this).closest('.wbe-active-box, tr, .wbe-product-panel'));
	});

	$(document).on('change input', '.wbe-product-panel .wbe-batch-sale, #wbe_active_sale, .wbe-batches-body .wbe-batch-sale', function () {
		syncDiscFromSale($(this).closest('.wbe-active-box, tr, .wbe-product-panel'));
	});

	function refreshReservedTotal() {
		var $tot = $('#wbe-reserved-total');
		if (!$tot.length) {
			return;
		}
		var sum = 0;
		$('#wbe-batches-body tr.is-reserve .wbe-batch-stock, #wbe-batches-body tr.is-reserve input[name*="[stock]"]').each(function () {
			var n = parseNum($(this).val());
			if (n > 0) {
				sum += Math.floor(n);
			}
		});
		$tot.text(String(sum));
	}

	$(document).on('change input', '#wbe-batches-body .wbe-batch-stock, #wbe-batches-body input[name*="[stock]"]', refreshReservedTotal);

	$(document).on('change', '#wbe-bulk-check-all', function () {
		$('.wbe-bulk-row:visible .wbe-bulk-id').prop('checked', this.checked);
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

	function refreshBulkTotalStock($tr) {
		if (!$tr || !$tr.length) {
			return;
		}
		var active = parseNum($tr.find('[data-field="stock"]').val());
		if (isNaN(active) || active < 0) {
			active = 0;
		}
		var res = 0;
		var $resBody = $('.wbe-bulk-reserves-row[data-parent-id="' + $tr.data('id') + '"] .wbe-reserves-body');
		if ($resBody.length) {
			$resBody.find('[data-field$=".stock"]').each(function () {
				var n = parseNum($(this).val());
				if (n > 0) {
					res += Math.floor(n);
				}
			});
		} else {
			res = parseInt($tr.attr('data-reserved'), 10) || 0;
		}
		$tr.find('.wbe-total-stock').text(String(Math.floor(active) + res));
	}

	$(document).on('input change', '.wbe-bulk-row [data-field], .wbe-bulk-reserves-row [data-field]', function () {
		var $i = $(this);
		var $resRow = $i.closest('.wbe-bulk-reserves-row');
		var $tr = $resRow.length
			? $('.wbe-bulk-row[data-id="' + $resRow.data('parent-id') + '"]')
			: $i.closest('.wbe-bulk-row');
		if (!$tr.length) {
			return;
		}
		refreshBulkTotalStock($tr);
		var dirty = false;
		$tr.find('[data-field]').each(function () {
			if (String($(this).val()) !== String($(this).attr('data-orig'))) {
				dirty = true;
				return false;
			}
		});
		if (!dirty) {
			$('.wbe-bulk-reserves-row[data-parent-id="' + $tr.data('id') + '"]')
				.find('[data-field]')
				.each(function () {
					if (String($(this).val()) !== String($(this).attr('data-orig'))) {
						dirty = true;
						return false;
					}
				});
		}
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
				var sale = disc > 0 ? Math.round((regular * (100 - disc)) / 100) : regular;
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
			$tr.toggle(show);
			$('.wbe-bulk-reserves-row[data-parent-id="' + $tr.data('id') + '"]').toggle(show);
			if (show) {
				n++;
			}
		});
		$('#wbe-bulk-count').text(n + ' محصول');
	});

	function reserveTpl(pid, idx, ph) {
		return (
			'<tr class="wbe-reserve-mini-row"><td>' +
			(idx + 1) +
			'</td><td><input type="hidden" name="wbe_row[' +
			pid +
			'][reserves][' +
			idx +
			'][id]" value="" /><input type="text" class="small-text" data-field="reserves.' +
			idx +
			'.price" data-orig="" name="wbe_row[' +
			pid +
			'][reserves][' +
			idx +
			'][price]" value="" dir="ltr" /></td><td><input type="text" class="small-text" data-field="reserves.' +
			idx +
			'.discount" data-orig="" name="wbe_row[' +
			pid +
			'][reserves][' +
			idx +
			'][discount]" value="" dir="ltr" /></td><td><input type="text" class="small-text" data-field="reserves.' +
			idx +
			'.stock" data-orig="" name="wbe_row[' +
			pid +
			'][reserves][' +
			idx +
			'][stock]" value="" dir="ltr" /></td><td><input type="text" class="small-text wbe-date" data-field="reserves.' +
			idx +
			'.expiry" data-orig="" name="wbe_row[' +
			pid +
			'][reserves][' +
			idx +
			'][expiry]" value="" placeholder="' +
			(ph || '') +
			'" dir="ltr" /></td><td><button type="button" class="button-link wbe-bulk-remove-reserve">حذف</button></td></tr>'
		);
	}

	function renumberReserves($body) {
		$body.find('tr.wbe-reserve-mini-row').each(function (i) {
			var $row = $(this);
			$row.find('td:first').text(i + 1);
			$row.find('input[name]').each(function () {
				this.name = this.name.replace(/\[reserves\]\[\d+\]/, '[reserves][' + i + ']');
			});
			$row.find('[data-field]').each(function () {
				var f = String($(this).attr('data-field') || '');
				$(this).attr('data-field', f.replace(/^reserves\.\d+\./, 'reserves.' + i + '.'));
			});
		});
	}

	$(document).on('click', '.wbe-bulk-add-reserve', function (e) {
		e.preventDefault();
		var pid = $(this).data('id');
		var $body = $('.wbe-reserves-body[data-id="' + pid + '"]');
		$body.find('.wbe-reserve-empty-hint').remove();
		var idx = $body.find('tr.wbe-reserve-mini-row').length;
		var ph = $('#wbe_expiry').attr('placeholder') || '';
		$body.append(reserveTpl(pid, idx, ph));
		var $added = $('.wbe-bulk-row[data-id="' + pid + '"]');
		$added.addClass('is-dirty');
		refreshBulkTotalStock($added);
	});

	$(document).on('click', '.wbe-bulk-remove-reserve', function (e) {
		e.preventDefault();
		var $body = $(this).closest('.wbe-reserves-body');
		var pid = $body.data('id');
		$(this).closest('tr').remove();
		renumberReserves($body);
		if (!$body.find('tr.wbe-reserve-mini-row').length) {
			$body.html(
				'<tr class="wbe-reserve-empty-hint"><td colspan="6" class="wbe-muted">رزروی نیست — «افزودن رزرو» را بزنید.</td></tr>'
			);
		}
		var $removed = $('.wbe-bulk-row[data-id="' + pid + '"]');
		$removed.addClass('is-dirty').data('reservesCleared', true);
		refreshBulkTotalStock($removed);
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
		var setStatus = $('#wbe_set_status').val() || '';
		var has = (regularMode !== 'none' && regularVal !== '') ||
			(saleMode !== 'none' && saleVal !== '') ||
			disc !== '' || from !== '' || to !== '' ||
			(stockMode !== 'none' && stockVal !== '') ||
			expiry !== '' || clear || setStatus !== '';
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
				$tr.find('[data-field="sale"]').data('manual', false);
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
				$tr.find('[data-field="sale"]').data('manual', false);
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
			if (setStatus !== '') {
				setField($tr, 'status', setStatus);
			}
		});
		return n;
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
			var add = $tr.data('addBatch');
			if (add) {
				Object.keys(add).forEach(function (k) {
					if (add[k] !== '' && add[k] != null) {
						row[k] = add[k];
					}
				});
			}
			var $resBody = $('.wbe-reserves-body[data-id="' + id + '"]');
			var reservesDirty = !!$tr.data('reservesCleared');
			if (!reservesDirty && $resBody.length) {
				$resBody.find('[data-field]').each(function () {
					if (String($(this).val()) !== String($(this).attr('data-orig'))) {
						reservesDirty = true;
						return false;
					}
				});
			}
			if (reservesDirty && $resBody.length) {
				var list = [];
				$resBody.find('tr.wbe-reserve-mini-row').each(function () {
					var $rr = $(this);
					list.push({
						id: $rr.find('input[name*="[id]"]').val() || '',
						price: $rr.find('input[name*="[price]"]').val() || '',
						discount: $rr.find('input[name*="[discount]"]').val() || '',
						stock: $rr.find('input[name*="[stock]"]').val() || '',
						expiry: $rr.find('input[name*="[expiry]"]').val() || ''
					});
				});
				row.reserves = list;
			}
			if (Object.keys(row).length) {
				row.dirty = 1;
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
			$('.wbe-bulk-reserves-row[data-parent-id="' + id + '"]')
				.find('[data-field]')
				.each(function () {
					$(this).attr('data-orig', $(this).val());
				});
			$tr.removeData('addBatch').removeData('reservesCleared');
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
			if (d.processed && d.processed.length) {
				markSaved(d.processed);
			} else {
				markSaved(Object.keys(chunk));
			}
			return saveChunks(chunks, total, doneUpd, doneSkip);
		}, function () {
			$('#wbe-bulk-progress').prop('hidden', true);
			showNotice(false, 'ذخیره ناقص ماند. دوباره تلاش کنید.');
		});
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

	var bugShot = null;

	function bugCfg() {
		return window.wbeSupport || { telegram: 'HAJITODAY', chat: 'https://t.me/HAJITODAY', version: '', page: '' };
	}

	function setBugShot(dataUrl) {
		bugShot = dataUrl || null;
		var $wrap = $('#wbe-bug-shot-wrap');
		var $img = $('#wbe-bug-shot');
		if (bugShot) {
			$img.attr('src', bugShot);
			$wrap.prop('hidden', false);
		} else {
			$img.attr('src', '');
			$wrap.prop('hidden', true);
		}
	}

	function bugText() {
		var cfg = bugCfg();
		var desc = $.trim($('#wbe-bug-desc').val() || '');
		var lines = [
			'گزارش باگ — انقضای کالا',
			'نسخه: ' + (cfg.version || ''),
			'صفحه: ' + (cfg.page || ''),
			'آدرس: ' + (window.location ? window.location.href : ''),
			'',
			'توضیح:',
			desc || '(بدون توضیح)'
		];
		if (bugShot) {
			lines.push('');
			lines.push('اسکرین‌شات پیوست می‌شود.');
		}
		return lines.join('\n');
	}

	function dataUrlToFile(dataUrl, name) {
		var parts = String(dataUrl).split(',');
		var mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/png';
		var bin = atob(parts[1] || '');
		var arr = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) {
			arr[i] = bin.charCodeAt(i);
		}
		return new File([arr], name, { type: mime });
	}

	function downloadShot() {
		if (!bugShot) {
			return;
		}
		var a = document.createElement('a');
		a.href = bugShot;
		a.download = 'wbe-bug.png';
		document.body.appendChild(a);
		a.click();
		a.remove();
	}

	$(document).on('click', '.wbe-bug-open', function (e) {
		e.preventDefault();
		$('#wbe-bug-modal').prop('hidden', false);
		$('#wbe-bug-status').text('');
	});

	$(document).on('click', '[data-wbe-bug-close]', function (e) {
		e.preventDefault();
		$('#wbe-bug-modal').prop('hidden', true);
	});

	$(document).on('click', '#wbe-bug-shot-clear', function (e) {
		e.preventDefault();
		setBugShot(null);
		$('#wbe-bug-file').val('');
	});

	$(document).on('change', '#wbe-bug-file', function () {
		var file = this.files && this.files[0];
		if (!file) {
			return;
		}
		var reader = new FileReader();
		reader.onload = function () {
			setBugShot(reader.result);
		};
		reader.readAsDataURL(file);
	});

	$(document).on('paste', '#wbe-bug-desc', function (e) {
		var items = e.originalEvent && e.originalEvent.clipboardData && e.originalEvent.clipboardData.items;
		if (!items) {
			return;
		}
		for (var i = 0; i < items.length; i++) {
			if (items[i].type && items[i].type.indexOf('image') === 0) {
				var file = items[i].getAsFile();
				if (!file) {
					continue;
				}
				var reader = new FileReader();
				reader.onload = function () {
					setBugShot(reader.result);
				};
				reader.readAsDataURL(file);
				break;
			}
		}
	});

	$(document).on('click', '#wbe-bug-capture', function (e) {
		e.preventDefault();
		var $st = $('#wbe-bug-status');
		if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
			$st.text('مرورگر گرفتن اسکرین را پشتیبانی نمی‌کند. تصویر را انتخاب یا در توضیح بچسبانید.');
			return;
		}
		$st.text('پنجره یا این تب را انتخاب کنید…');
		navigator.mediaDevices
			.getDisplayMedia({ video: true, audio: false })
			.then(function (stream) {
				var track = stream.getVideoTracks()[0];
				var video = document.createElement('video');
				video.srcObject = stream;
				video.muted = true;
				return video.play().then(function () {
					var canvas = document.createElement('canvas');
					canvas.width = video.videoWidth || 1280;
					canvas.height = video.videoHeight || 720;
					canvas.getContext('2d').drawImage(video, 0, 0);
					track.stop();
					stream.getTracks().forEach(function (t) {
						t.stop();
					});
					setBugShot(canvas.toDataURL('image/png'));
					$st.text('اسکرین گرفته شد.');
				});
			})
			.catch(function () {
				$st.text('گرفتن اسکرین لغو شد. می‌توانید فایل تصویر انتخاب کنید.');
			});
	});

	$(document).on('click', '#wbe-bug-send', function (e) {
		e.preventDefault();
		var cfg = bugCfg();
		var text = bugText();
		var desc = $.trim($('#wbe-bug-desc').val() || '');
		if (!desc && !bugShot) {
			$('#wbe-bug-status').text('حداقل توضیح یا اسکرین لازم است.');
			return;
		}
		var chat = cfg.chat || 'https://t.me/HAJITODAY';
		var url = chat + (chat.indexOf('?') === -1 ? '?text=' : '&text=') + encodeURIComponent(text);
		var finish = function () {
			downloadShot();
			window.open(url, '_blank', 'noopener');
			$('#wbe-bug-status').text(
				bugShot
					? 'تلگرام باز شد. تصویر دانلود شد — همان را در چت @' + (cfg.telegram || 'HAJITODAY') + ' پیوست کنید.'
					: 'تلگرام باز شد.'
			);
		};
		if (bugShot && navigator.canShare) {
			try {
				var file = dataUrlToFile(bugShot, 'wbe-bug.png');
				if (navigator.canShare({ files: [file] })) {
					navigator
						.share({ text: text, files: [file] })
						.then(function () {
							$('#wbe-bug-status').text('گزارش برای ارسال آماده شد.');
						})
						.catch(finish);
					return;
				}
			} catch (err) {
				finish();
				return;
			}
		}
		finish();
	});

	$(document).on('click', '.wbe-copy-variations', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $panel = $btn.closest('.wbe-product-panel');
		var from = $panel.attr('data-variation-id');
		if (!from || from === '0') {
			window.alert('شناسه تنوع پیدا نشد.');
			return;
		}
		if (!window.confirm('بچ‌های همین تنوع روی همه تنوع‌های دیگر این محصول کپی شود؟')) {
			return;
		}
		var active = {
			id: $panel.find('input[name$="[active][id]"]').val() || '',
			price: $panel.find('input[name$="[active][price]"]').val() || '',
			discount: $panel.find('input[name$="[active][discount]"]').val() || '',
			sale: $panel.find('input[name$="[active][sale]"]').val() || '',
			stock: $panel.find('input[name$="[active][stock]"]').val() || '',
			expiry: $panel.find('input[name$="[active][expiry]"]').val() || ''
		};
		var reserve = [];
		$panel.find('.wbe-reserve-box tr.wbe-batch-row').not('.wbe-reserve-empty, .wbe-batch-tpl').each(function () {
			var $tr = $(this);
			reserve.push({
				id: $tr.find('input[name$="[id]"]').val() || '',
				price: $tr.find('input[name$="[price]"]').val() || '',
				discount: $tr.find('input[name$="[discount]"]').val() || '',
				stock: $tr.find('input[name$="[stock]"]').val() || '',
				expiry: $tr.find('input[name$="[expiry]"]').val() || ''
			});
		});
		var cfg = window.wbeAdmin || {};
		$btn.prop('disabled', true);
		$.ajax({
			url: cfg.ajax || (window.ajaxurl || ''),
			method: 'POST',
			data: {
				action: 'wbe_copy_variation_batches',
				nonce: cfg.nonce || '',
				from: from,
				calendar: $panel.find('select[name$="[calendar]"]').val() || '',
				hide_countdown: $panel.find('input[name$="[hide_countdown]"]').is(':checked') ? 1 : 0,
				active: active,
				reserve: reserve
			}
		})
			.done(function (res) {
				var msg = res && res.data && res.data.message ? res.data.message : 'کپی شد.';
				window.alert(msg);
			})
			.fail(function (xhr) {
				var msg = 'کپی نشد.';
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				window.alert(msg);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	function fillWbeQuickEdit(postId) {
		var $src = $('#post-' + postId + ' .wbe-qe-json');
		var $row = $('#edit-' + postId);
		if (!$row.length) {
			$row = $('.inline-edit-row');
		}
		var $panel = $row.find('.wbe-product-panel');
		var $ready = $row.find('.wbe-qe-ready');
		var $note = $row.find('.wbe-qe-variable');
		$ready.val('0');
		if (!$panel.length || !$src.length) {
			if ($panel.length) {
				$panel.attr('hidden', 'hidden');
			}
			return;
		}
		var data;
		try {
			data = JSON.parse($.trim($src.text() || '{}'));
		} catch (err) {
			$panel.attr('hidden', 'hidden');
			return;
		}
		if (data.type === 'variable') {
			$panel.attr('hidden', 'hidden');
			$note.removeAttr('hidden');
			return;
		}
		$note.attr('hidden', 'hidden');
		$panel.removeAttr('hidden');
		$panel.attr('data-calendar', data.effective || '');
		$row.find('#wbe_calendar').val(data.calendar || '');
		$row.find('#wbe_hide_countdown').prop('checked', !!data.hide_cd);
		var a = data.active || {};
		$row.find('#wbe_active_id, input[name="wbe_active[id]"]').val(a.id || '');
		$row.find('#wbe_active_price').val(a.price || '');
		$row.find('#wbe_active_discount').val(a.discount || '');
		$row.find('#wbe_active_sale').val(a.sale || '');
		$row.find('#wbe_active_stock').val(a.stock === 0 || a.stock ? a.stock : '');
		$row.find('#wbe_active_expiry').val(a.expiry || '');
		$row.find('#wbe_sale_from').val(data.sale_from || '');
		$row.find('#wbe_sale_to').val(data.sale_to || '');
		var $body = $row.find('#wbe-batches-body');
		var $tpl = $row.find('.wbe-batch-tpl').first();
		$body.empty();
		var reserves = data.reserves || [];
		if (!reserves.length) {
			$body.html(
				'<tr class="wbe-batch-row is-reserve wbe-reserve-empty"><td colspan="5" class="wbe-muted">هنوز بچ رزرو ندارید — «افزودن بچ رزرو» را بزنید.</td></tr>'
			);
		} else if ($tpl.length) {
			$.each(reserves, function (i, b) {
				var $nr = $tpl.clone().removeAttr('id').removeClass('wbe-batch-tpl');
				$nr.find('[data-name]').each(function () {
					var name = $(this).attr('data-name');
					$(this).attr('name', 'wbe_reserve[' + i + '][' + name + ']').removeAttr('data-name');
				});
				$nr.find('input[name$="[id]"]').val(b.id || '');
				$nr.find('input[name$="[price]"]').val(b.price || '');
				$nr.find('input[name$="[discount]"]').val(b.discount || '');
				$nr.find('input[name$="[stock]"]').val(b.stock === 0 || b.stock ? b.stock : '');
				$nr.find('input[name$="[expiry]"]').val(b.expiry || '');
				$body.append($nr);
			});
		}
		syncActiveToWc($panel);
		$ready.val('1');
	}

	if (typeof window.inlineEditPost !== 'undefined' && window.inlineEditPost.edit) {
		var wbePrevInlineEdit = window.inlineEditPost.edit;
		window.inlineEditPost.edit = function (id) {
			wbePrevInlineEdit.apply(this, arguments);
			var postId = 0;
			if (typeof id === 'object') {
				postId = parseInt(this.getId(id), 10);
			} else {
				postId = parseInt(id, 10);
			}
			if (postId) {
				fillWbeQuickEdit(postId);
			}
		};
	}
})(jQuery);
