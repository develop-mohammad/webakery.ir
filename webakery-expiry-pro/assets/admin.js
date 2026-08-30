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
})(jQuery);
