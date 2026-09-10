<?php
defined( 'ABSPATH' ) || exit;
/** @var array $contract */
/** @var array $s */
/** @var array $vars */
/** @var array $clauses */
/** @var string $note */
$print_css = NCK_URL . 'assets/css/print.css?v=' . NCK_VERSION;
$front_css = NCK_URL . 'assets/css/frontend.css?v=' . NCK_VERSION;
$payload   = NCK_Contracts::payload( $contract );
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>اجاره سالن — <?php echo esc_html( $contract['full_name'] ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $front_css ); ?>" />
	<link rel="stylesheet" href="<?php echo esc_url( $print_css ); ?>" />
	<style>.nck-root{--nck-leaf:<?php echo esc_attr( $s['accent'] ); ?>;}</style>
</head>
<body class="nck-print-body">
	<div class="nck-print-bar">
		<button type="button" onclick="window.print()">چاپ</button>
		<button type="button" onclick="window.close()">بستن</button>
	</div>
	<article class="nck-root nck-paper nck-print-paper" dir="rtl">
		<header class="nck-paper-head">
			<div class="nck-mark" aria-hidden="true">
				<svg viewBox="0 0 48 48" width="42" height="42"><path fill="currentColor" d="M24 4c1.2 6 3 10 8 14-6 1-10 4-12 10-2-6-6-9-12-10 5-4 6.8-8 8-14 2 5 4 8 8 10z"/></svg>
			</div>
			<div>
				<p class="nck-kicker">بسمه تعالی</p>
				<h1 class="nck-title">قرارداد اجاره سالن</h1>
				<p class="nck-tag"><?php echo esc_html( $s['hall_org'] ); ?></p>
			</div>
		</header>

		<p class="nck-preamble">
			این قرارداد بین «<?php echo esc_html( $vars['org'] ); ?>» به عنوان طرف اول و
			<?php echo esc_html( $vars['title'] . ' ' . $vars['name'] ); ?>
			به شماره ملی <?php echo esc_html( $vars['nid'] ); ?>
			و شماره تماس <?php echo esc_html( $vars['phone'] ); ?>
			به عنوان طرف دوم منعقد می‌گردد.
		</p>

		<?php foreach ( $clauses as $block ) : ?>
			<section class="nck-section">
				<h3><?php echo esc_html( $block['num'] . '- ' . $block['title'] ); ?>:</h3>
				<?php if ( ! empty( $block['body'] ) ) : ?>
					<p><?php echo esc_html( $block['body'] ); ?></p>
				<?php endif; ?>
				<?php if ( '۲' === $block['num'] ) : ?>
					<p><?php echo esc_html( $note ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $block['items'] ) ) : ?>
					<?php foreach ( $block['items'] as $item ) : ?>
						<p><?php echo esc_html( $item ); ?></p>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>

		<?php if ( ! empty( $payload['projector'] ) ) : ?>
			<p class="nck-note">ویدئو پروژکتور درخواست شده است. <?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<footer class="nck-sign-row">
			<div>
				<p>امضای برگزارکننده مراسم</p>
				<?php if ( ! empty( $contract['signature_png'] ) ) : ?>
					<img class="nck-sign-img" src="<?php echo esc_attr( $contract['signature_png'] ); ?>" alt="امضا" />
				<?php endif; ?>
				<p><?php echo esc_html( $vars['title'] . ' ' . $contract['full_name'] ); ?></p>
			</div>
			<div>
				<p>امضا</p>
				<p class="nck-org-sign"><?php echo esc_html( $s['hall_signer'] ); ?></p>
				<p><?php echo esc_html( $s['hall_org'] ); ?></p>
			</div>
		</footer>
	</article>
</body>
</html>
