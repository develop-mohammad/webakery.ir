<?php
defined( 'ABSPATH' ) || exit;

/**
 * اینتنت جستجو از خودِ عبارت (قانونی، نه حدس حجم).
 *
 * اولویت: اطلاعاتی → تراکنشی → تجاری مقایسه‌ای → ناوبری فقط برای مقصد/برندِ تنها.
 * ذکر مارکت‌پلیس کنار دسته (مثل «کفش دیجی کالا») ناوبری نیست.
 */
class WBGS_Intent {

	const INFORMATIONAL = 'informational';
	const COMMERCIAL    = 'commercial';
	const TRANSACTIONAL = 'transactional';
	const NAVIGATIONAL  = 'navigational';

	/**
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			self::INFORMATIONAL => 'اطلاعاتی',
			self::COMMERCIAL    => 'تجاری',
			self::TRANSACTIONAL => 'تراکنشی',
			self::NAVIGATIONAL  => 'ناوبری/راهبری',
		);
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function classify( $keyword ) {
		$text = WBGS_Suggest::normalize_seed( $keyword );
		if ( $text === '' ) {
			return self::COMMERCIAL;
		}

		$flags  = class_exists( 'WBGS_Entity' ) ? WBGS_Entity::inspect( $text ) : array();
		$entity = class_exists( 'WBGS_Entity' ) ? WBGS_Entity::classify( $text ) : '';

		if ( ! empty( $flags['has_info'] ) ) {
			return self::INFORMATIONAL;
		}
		if ( ! empty( $flags['has_trans'] ) ) {
			return self::TRANSACTIONAL;
		}
		if ( ! empty( $flags['has_comm'] ) ) {
			return self::COMMERCIAL;
		}
		if ( ! empty( $flags['is_dest'] ) && ( ! empty( $flags['has_maker'] ) || ! empty( $flags['has_market'] ) || ! empty( $flags['has_tld'] ) ) ) {
			return self::NAVIGATIONAL;
		}
		if ( class_exists( 'WBGS_Entity' ) && WBGS_Entity::BRAND === $entity && empty( $flags['has_category'] ) && empty( $flags['has_model'] ) && empty( $flags['has_line'] ) ) {
			return self::NAVIGATIONAL;
		}

		return self::COMMERCIAL;
	}

	/**
	 * @param string $keyword
	 * @return string
	 */
	public static function label( $keyword ) {
		$labels = self::labels();
		$key    = self::classify( $keyword );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels[ self::COMMERCIAL ];
	}

	/**
	 * @param array<int,array> $rows
	 * @return array<int,array>
	 */
	public static function attach( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$text             = isset( $row['text'] ) ? (string) $row['text'] : '';
			$row['intent']    = self::classify( $text );
			$row['intent_fa'] = self::label( $text );
			if ( class_exists( 'WBGS_Entity' ) ) {
				$row['entity']    = WBGS_Entity::classify( $text );
				$row['entity_fa'] = WBGS_Entity::label( $text );
			}
			$out[] = $row;
		}
		return $out;
	}
}
