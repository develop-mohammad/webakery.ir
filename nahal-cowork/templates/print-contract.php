<?php
defined( 'ABSPATH' ) || exit;
/** @var array $contract */
/** @var array $s */
/** @var array $vars */
/** @var string $intro */
/** @var string $preamble */
/** @var array $sections */
$print_css = NCK_URL . 'assets/css/print.css?v=' . NCK_VERSION;
$front_css = NCK_URL . 'assets/css/frontend.css?v=' . NCK_VERSION;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>قرارداد <?php echo esc_html( $contract['full_name'] ); ?></title>
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
				<p class="nck-kicker"><?php echo esc_html( $s['org_name'] ); ?></p>
				<h1 class="nck-title">قرارداد فضای کار اشتراکی</h1>
				<p class="nck-tag"><?php echo esc_html( $vars['date'] ); ?> — <?php echo esc_html( $vars['plan'] ); ?></p>
			</div>
		</header>
		<p class="nck-intro"><?php echo esc_html( $intro ); ?></p>
		<p class="nck-preamble"><?php echo esc_html( $preamble ); ?></p>
		<?php foreach ( $sections as $sec ) : ?>
			<section class="nck-section">
				<h3><?php echo esc_html( $sec['title'] ); ?></h3>
				<?php foreach ( preg_split( '/\n+/', $sec['body'] ) as $p ) : ?>
					<?php if ( trim( $p ) !== '' ) : ?>
						<p><?php echo esc_html( $p ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
		<footer class="nck-sign-row">
			<div>
				<p>امضای عضو</p>
				<?php if ( ! empty( $contract['signature_png'] ) ) : ?>
					<img class="nck-sign-img" src="<?php echo esc_attr( $contract['signature_png'] ); ?>" alt="امضا" />
				<?php endif; ?>
				<p><?php echo esc_html( $contract['full_name'] ); ?></p>
			</div>
			<div>
				<p>مجموعه</p>
				<p class="nck-org-sign"><?php echo esc_html( $s['org_name'] ); ?></p>
			</div>
		</footer>
	</article>
</body>
</html>
