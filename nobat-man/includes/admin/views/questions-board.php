<?php
defined( 'ABSPATH' ) || exit;
/** @var array $board */
$fields    = $board['fields'] ?? array();
$active    = $board['active'] ?? array();
$available = $board['available'] ?? array();
?>
<div class="nm-qboard-board">
	<section class="nm-qboard-col">
		<header>
			<strong>فیلدهای موجود</strong>
			<button type="button" class="button" id="nm-q-add-field">+ سوال جدید</button>
		</header>
		<ul class="nm-qboard-list" id="nm-q-available" data-list="available">
			<?php foreach ( $available as $id ) :
				$f = $fields[ $id ] ?? null;
				if ( ! $f ) { continue; }
				$key = (string) $id;
				include NM_PATH . 'includes/admin/views/questions-row.php';
			endforeach; ?>
		</ul>
		<p class="nm-qboard-muted nm-qboard-tip">روی + بزنید یا بکشید به ستون فعال.</p>
	</section>

	<div class="nm-qboard-swap" aria-hidden="true">⇄</div>

	<section class="nm-qboard-col">
		<header>
			<strong>فیلدهای فعال — برای مرتب‌سازی بکشید</strong>
		</header>
		<ul class="nm-qboard-list" id="nm-q-active" data-list="active">
			<?php foreach ( $active as $id ) :
				$f = $fields[ $id ] ?? null;
				if ( ! $f ) { continue; }
				$key = (string) $id;
				include NM_PATH . 'includes/admin/views/questions-row.php';
			endforeach; ?>
		</ul>
		<input type="hidden" id="nm-q-active-input" value="<?php echo esc_attr( wp_json_encode( array_values( $active ) ) ); ?>" />
	</section>
</div>
