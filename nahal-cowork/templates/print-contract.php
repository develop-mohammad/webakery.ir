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
	<?php
	$nck_save_title = 'قرارداد-' . ( isset( $contract['full_name'] ) ? $contract['full_name'] : 'نهال' );
	include NCK_PATH . 'templates/print-bar.php';
	?>
	<article class="nck-root nck-paper nck-print-paper" dir="rtl">
		<header class="nck-paper-head">
			<?php include NCK_PATH . 'templates/brand-mark.php'; ?>
			<div>
				<p class="nck-kicker"><?php echo esc_html( $s['org_name'] ); ?></p>
				<h1 class="nck-title">قرارداد فضای کار اشتراکی</h1>
				<p class="nck-tag"><?php echo esc_html( $vars['date'] ); ?> — <?php echo esc_html( $vars['plan'] ); ?></p>
			</div>
		</header>
		<p class="nck-intro"><?php echo esc_html( $intro ); ?></p>
		<p class="nck-preamble"><?php echo esc_html( $preamble ); ?></p>
		<?php
		$pay = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::payload( $contract ) : array();
		if ( ! empty( $pay['pay_amount'] ) || ! empty( $pay['payment'] ) ) :
			$pay_opts = NCK_Learner::payment_options();
			$pay_key  = isset( $pay['payment'] ) ? $pay['payment'] : '';
			$fa       = static function ( $v ) {
				return NCK_Jalali::fa_digits( (string) $v );
			};
			?>
			<section class="nck-section">
				<h3>پرداخت</h3>
				<p>روش: <?php echo esc_html( isset( $pay_opts[ $pay_key ] ) ? $pay_opts[ $pay_key ] : '—' ); ?></p>
				<?php if ( ! empty( $pay['pay_amount'] ) ) : ?>
					<p>مبلغ: <?php echo esc_html( NCK_Hall::format_money( (int) $pay['pay_amount'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $pay['pay_date'] ) ) : ?>
					<p>تاریخ: <?php echo esc_html( $fa( $pay['pay_date'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $pay['pay_ref'] ) ) : ?>
					<p>پیگیری: <?php echo esc_html( $pay['pay_ref'] ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>
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
			<?php
			$plate_src  = ! empty( $contract['signature_png'] ) ? $contract['signature_png'] : '';
			$plate_name = $contract['full_name'];
			$plate_role = 'امضای عضو';
			$plate_date = $vars['date'];
			$plate_org  = false;
			include NCK_PATH . 'templates/sign-plate.php';
			$plate_src   = '';
			$plate_name  = $s['org_name'];
			$plate_role  = 'مهر و امضای مجموعه';
			$plate_date  = '';
			$plate_extra = $s['org_tagline'];
			$plate_org   = true;
			include NCK_PATH . 'templates/sign-plate.php';
			?>
		</footer>
	</article>
</body>
</html>
