<?php
defined( 'ABSPATH' ) || exit;
/** @var array $contract */
/** @var array $s */
/** @var array $p */
$print_css = NCK_URL . 'assets/css/print.css?v=' . NCK_VERSION;
$front_css = NCK_URL . 'assets/css/frontend.css?v=' . NCK_VERSION;
$title     = isset( $p['form_title'] ) && $p['form_title'] !== '' ? $p['form_title'] : 'فرم نهال';
$fa        = static function ( $v ) {
	return $v === '' || null === $v ? '—' : NCK_Jalali::fa_digits( (string) $v );
};
$row       = static function ( $label, $value ) {
	echo '<div class="nck-print-row"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
};
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( $contract['full_name'] ); ?></title>
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
				<h1 class="nck-title"><?php echo esc_html( $title ); ?></h1>
			</div>
		</header>

		<section class="nck-section">
			<h3>مشخصات</h3>
			<?php
			$row( 'نام', $contract['full_name'] );
			$row( 'موبایل', $fa( $contract['phone'] ) );
			if ( ! empty( $p['national_id'] ) ) {
				$row( 'کد ملی', $fa( $p['national_id'] ) );
			}
			if ( ! empty( $p['email'] ) ) {
				$row( 'ایمیل', $p['email'] );
			}
			if ( ! empty( $p['address'] ) ) {
				$row( 'آدرس', $p['address'] );
			}
			?>
		</section>

		<?php if ( ! empty( $p['answers'] ) && is_array( $p['answers'] ) ) : ?>
			<section class="nck-section">
				<h3>پاسخ‌ها</h3>
				<?php
				foreach ( $p['answers'] as $ans ) {
					$label = isset( $ans['label'] ) ? $ans['label'] : '';
					$disp  = isset( $ans['display'] ) ? $ans['display'] : '';
					if ( $label === '' ) {
						continue;
					}
					$row( $label, $disp !== '' ? $fa( $disp ) : '—' );
				}
				?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $p['pay_amount'] ) || ! empty( $p['payment'] ) ) : ?>
			<section class="nck-section">
				<h3>پرداخت</h3>
				<?php
				$pay = isset( $p['payment'] ) ? $p['payment'] : '';
				$opts = NCK_Learner::payment_options();
				$row( 'روش پرداخت', isset( $opts[ $pay ] ) ? $opts[ $pay ] : '—' );
				$row( 'مبلغ', ! empty( $p['pay_amount'] ) ? NCK_Hall::format_money( (int) $p['pay_amount'] ) : '—' );
				$row( 'تاریخ پرداخت', $fa( isset( $p['pay_date'] ) ? $p['pay_date'] : '' ) );
				$row( 'شماره پیگیری', $fa( isset( $p['pay_ref'] ) ? $p['pay_ref'] : '' ) );
				if ( ! empty( $p['wc_order_id'] ) ) {
					$row( 'شماره سفارش ووکامرس', $fa( $p['wc_order_id'] ) );
				}
				?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $contract['signature_png'] ) ) : ?>
			<section class="nck-section">
				<h3>امضا</h3>
				<img class="nck-sign-img" src="<?php echo esc_attr( $contract['signature_png'] ); ?>" alt="امضا" />
				<p class="nck-note">تاریخ ثبت: <?php echo esc_html( $fa( $contract['signed_at'] ) ); ?></p>
			</section>
		<?php endif; ?>
	</article>
</body>
</html>
