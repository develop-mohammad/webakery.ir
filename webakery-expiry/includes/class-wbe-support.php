<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * راهنما و گزارش باگ — ارسال به تلگرام @HAJITODAY
 */
class WBE_Support {

	const TELEGRAM = 'HAJITODAY';

	public static function telegram_handle() {
		return self::TELEGRAM;
	}

	public static function telegram_chat_url( $text = '' ) {
		$url = 'https://t.me/' . self::TELEGRAM;
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return $url;
		}
		return $url . '?text=' . rawurlencode( $text );
	}

	/**
	 * متن گزارش باگ برای تلگرام.
	 *
	 * @param string $description
	 * @param array  $meta
	 * @return string
	 */
	public static function report_text( $description, array $meta = array() ) {
		$description = trim( (string) $description );
		$lines       = array(
			'گزارش باگ — انقضای کالا',
			'نسخه: ' . ( isset( $meta['version'] ) ? (string) $meta['version'] : '' ),
			'صفحه: ' . ( isset( $meta['page'] ) ? (string) $meta['page'] : '' ),
		);
		if ( ! empty( $meta['url'] ) ) {
			$lines[] = 'آدرس: ' . (string) $meta['url'];
		}
		$lines[] = '';
		$lines[] = 'توضیح:';
		$lines[] = $description !== '' ? $description : '(بدون توضیح)';
		if ( ! empty( $meta['has_screenshot'] ) ) {
			$lines[] = '';
			$lines[] = 'اسکرین‌شات پیوست می‌شود.';
		}
		return implode( "\n", $lines );
	}

	/**
	 * آیا لود گروهی بدون برند باید خالی بماند؟ (رفع کندی)
	 *
	 * @param array $filters
	 * @return bool
	 */
	public static function bulk_should_skip_load( array $filters ) {
		if ( ! class_exists( 'WBE_Admin_Bulk' ) ) {
			return true;
		}
		return ! WBE_Admin_Bulk::has_brand_filter( $filters );
	}
}
