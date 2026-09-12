<?php
defined( 'ABSPATH' ) || exit;
$title = isset( $nck_save_title ) ? (string) $nck_save_title : 'قرارداد-نهال';
?>
<div class="nck-print-bar">
	<button type="button" onclick="window.print()">چاپ</button>
	<button type="button" data-nck-save-html data-nck-save-name="<?php echo esc_attr( $title ); ?>">دانلود قرارداد</button>
	<button type="button" onclick="window.close()">بستن</button>
</div>
<script>
(function () {
	var btn = document.querySelector('[data-nck-save-html]');
	if (!btn) return;
	btn.addEventListener('click', function () {
		var html = '<!DOCTYPE html>' + document.documentElement.outerHTML;
		var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
		var a = document.createElement('a');
		var name = btn.getAttribute('data-nck-save-name') || 'قرارداد-نهال';
		a.href = URL.createObjectURL(blob);
		a.download = name + '.html';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 800);
	});
})();
</script>
