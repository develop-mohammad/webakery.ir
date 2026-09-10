<?php
defined( 'ABSPATH' ) || exit;
/** @var array $contract */
/** @var array $s */
/** @var array $p */
$print_css = NCK_URL . 'assets/css/print.css?v=' . NCK_VERSION;
$front_css = NCK_URL . 'assets/css/frontend.css?v=' . NCK_VERSION;
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
	<title>پذیرش فراگیر — <?php echo esc_html( $contract['full_name'] ); ?></title>
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
				<p class="nck-kicker"><?php echo esc_html( NCK_Learner::center_name() ); ?></p>
				<h1 class="nck-title">فرم پذیرش و شناخت فراگیر</h1>
				<p class="nck-tag"><?php echo esc_html( NCK_Learner::tagline() ); ?></p>
			</div>
		</header>

		<section class="nck-section">
			<h3>نحوه آشنایی و پرداخت</h3>
			<?php
			$row( 'نحوه آشنایی', NCK_Learner::join_labels( isset( $p['heard'] ) ? (array) $p['heard'] : array(), NCK_Learner::heard_options() ) );
			$pay = isset( $p['payment'] ) ? $p['payment'] : '';
			$pay_opts = NCK_Learner::payment_options();
			$row( 'وضعیت پرداخت', isset( $pay_opts[ $pay ] ) ? $pay_opts[ $pay ] : '—' );
			$row( 'مبلغ', ! empty( $p['pay_amount'] ) ? NCK_Hall::format_money( (int) $p['pay_amount'] ) : '—' );
			$row( 'تاریخ پرداخت', $fa( isset( $p['pay_date'] ) ? $p['pay_date'] : '' ) );
			$row( 'شماره پیگیری', $fa( isset( $p['pay_ref'] ) ? $p['pay_ref'] : '' ) );
			if ( ! empty( $p['wc_order_id'] ) ) {
				$row( 'شماره سفارش ووکامرس', $fa( $p['wc_order_id'] ) );
			}
			$row( 'کد فراگیر', isset( $p['learner_code'] ) && $p['learner_code'] !== '' ? $p['learner_code'] : '—' );
			$row( 'تاریخ پذیرش', $fa( isset( $p['admit_date'] ) ? $p['admit_date'] : '' ) );
			$row( 'مسئول پذیرش', isset( $p['staff_name'] ) && $p['staff_name'] !== '' ? $p['staff_name'] : '—' );
			$row( 'محدودیت سنی / ساعت کلاس', isset( $p['class_limit'] ) && $p['class_limit'] !== '' ? $p['class_limit'] : '—' );
			?>
		</section>

		<section class="nck-section">
			<h3>اطلاعات فراگیر</h3>
			<?php
			$row( 'نام و نام خانوادگی', $contract['full_name'] );
			$row( 'تاریخ تولد', $fa( isset( $p['birth_date'] ) ? $p['birth_date'] : '' ) );
			$row( 'کد ملی', $fa( isset( $p['national_id'] ) ? $p['national_id'] : $contract['national_id'] ) );
			$row( 'پایه تحصیلی', isset( $p['grade'] ) ? $p['grade'] : '—' );
			$row( 'مدرسه', isset( $p['school'] ) ? $p['school'] : '—' );
			$row( 'شماره تماس', $fa( isset( $p['phone'] ) ? $p['phone'] : $contract['phone'] ) );
			$row( 'آدرس', isset( $p['address'] ) ? $p['address'] : '—' );
			?>
		</section>

		<section class="nck-section">
			<h3>اطلاعات والدین</h3>
			<?php
			$row( 'پدر', ( isset( $p['father_name'] ) ? $p['father_name'] : '—' ) . ' — ' . ( isset( $p['father_job'] ) && $p['father_job'] !== '' ? $p['father_job'] : '—' ) . ' — ' . $fa( isset( $p['father_phone'] ) ? $p['father_phone'] : '' ) );
			$row( 'مادر', ( isset( $p['mother_name'] ) ? $p['mother_name'] : '—' ) . ' — ' . ( isset( $p['mother_job'] ) && $p['mother_job'] !== '' ? $p['mother_job'] : '—' ) . ' — ' . $fa( isset( $p['mother_phone'] ) ? $p['mother_phone'] : '' ) );
			?>
		</section>

		<section class="nck-section">
			<h3>دوره‌ها و شناخت</h3>
			<?php
			$row( 'دوره‌های ترمی', NCK_Learner::join_labels( isset( $p['term'] ) ? (array) $p['term'] : array(), NCK_Learner::term_options() ) );
			$row( 'دوره‌های فصلی', NCK_Learner::join_labels( isset( $p['seasonal'] ) ? (array) $p['seasonal'] : array(), NCK_Learner::seasonal_options() ) );
			$row( 'پزشکی', NCK_Learner::join_labels( isset( $p['medical'] ) ? (array) $p['medical'] : array(), NCK_Learner::medical_options() ) );
			if ( ! empty( $p['medical_notes'] ) ) {
				$row( 'توضیحات پزشکی', $p['medical_notes'] );
			}
			$g = isset( $p['group_work'] ) ? $p['group_work'] : '';
			$pr = isset( $p['problem'] ) ? $p['problem'] : '';
			$ln = isset( $p['learning'] ) ? $p['learning'] : '';
			$go = NCK_Learner::group_options();
			$po = NCK_Learner::problem_options();
			$lo = NCK_Learner::learning_options();
			$row( 'کار گروهی', isset( $go[ $g ] ) ? $go[ $g ] : '—' );
			$row( 'مسئله جدید', isset( $po[ $pr ] ) ? $po[ $pr ] : '—' );
			$row( 'سبک یادگیری', isset( $lo[ $ln ] ) ? $lo[ $ln ] : '—' );
			$row( 'اهداف ثبت‌نام', NCK_Learner::join_labels( isset( $p['goals'] ) ? (array) $p['goals'] : array(), NCK_Learner::goal_options() ) );
			?>
		</section>

		<section class="nck-section">
			<h3>قوانین و تعهدات</h3>
			<p>قوانین آموزشی و انضباطی نهال پذیرفته شد.</p>
			<p>مسئولیت به‌روزرسانی اطلاعات تماس پذیرفته شد.</p>
			<p>موافقت با استفاده از تصاویر (عکس و فیلم) در کلاس‌ها ثبت شد.</p>
		</section>

		<footer class="nck-sign-row">
			<div>
				<p>امضای والدین / سرپرست</p>
				<?php if ( ! empty( $contract['signature_png'] ) ) : ?>
					<img class="nck-sign-img" src="<?php echo esc_attr( $contract['signature_png'] ); ?>" alt="امضا" />
				<?php endif; ?>
				<p><?php echo esc_html( $contract['full_name'] ); ?></p>
				<?php if ( ! empty( $p['sign_date'] ) ) : ?>
					<p>تاریخ: <?php echo esc_html( $fa( $p['sign_date'] ) ); ?></p>
				<?php endif; ?>
			</div>
			<div>
				<p><?php echo esc_html( NCK_Learner::center_name() ); ?></p>
				<p class="nck-slogan"><?php echo esc_html( NCK_Learner::slogan() ); ?></p>
			</div>
		</footer>
	</article>
</body>
</html>
