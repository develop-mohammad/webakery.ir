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
})(jQuery);
