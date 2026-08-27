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
})(jQuery);
