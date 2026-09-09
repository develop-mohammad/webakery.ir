<?php
defined( 'ABSPATH' ) || exit;

/**
 * ساخت کوئری‌های Suggest و پارس پاسخ واقعی گوگل.
 * هیچ کیورد ساختگی تولید نمی‌کند.
 */
class WBGS_Suggest {

	/**
	 * @return string[]
	 */
	public static function persian_letters() {
		return array(
			'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ',
			'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض',
			'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل',
			'م', 'ن', 'و', 'ه', 'ی',
		);
	}

	/**
	 * پیشوند/پسوندهایی که به‌عنوان کوئری جدا به گوگل زده می‌شوند.
	 *
	 * @return string[]
	 */
	public static function modifiers() {
		return array(
			'خرید',
			'قیمت',
			'بهترین',
			'ارزان',
			'انواع',
			'مدل',
			'فروش',
			'آنلاین',
			'چیست',
			'چگونه',
			'آموزش',
			'مقایسه',
		);
	}

	/**
	 * @param string $seed
	 * @return string
	 */
	public static function normalize_seed( $seed ) {
		$seed = is_string( $seed ) ? $seed : '';
		$seed = preg_replace( '/\s+/u', ' ', $seed );
		return trim( (string) $seed );
	}

	/**
	 * @param array<string,bool> $modes
	 * @return array<string,bool>
	 */
	public static function normalize_modes( $modes ) {
		$modes = is_array( $modes ) ? $modes : array();
		return array(
			'space'     => ! empty( $modes['space'] ),
			'alphabet'  => ! empty( $modes['alphabet'] ),
			'latin'     => ! empty( $modes['latin'] ),
			'digits'    => ! empty( $modes['digits'] ),
			'modifiers' => ! empty( $modes['modifiers'] ),
		);
	}

	/**
	 * @param string             $seed
	 * @param array<string,bool> $modes
	 * @return string[]
	 */
	public static function build_queries( $seed, $modes = array() ) {
		$seed  = self::normalize_seed( $seed );
		$modes = self::normalize_modes( $modes );
		if ( $seed === '' ) {
			return array();
		}

		$out = array( $seed );

		if ( $modes['space'] ) {
			$out[] = $seed . ' ';
			$out[] = ' ' . $seed;
		}

		if ( $modes['alphabet'] ) {
			foreach ( self::persian_letters() as $letter ) {
				$out[] = $seed . ' ' . $letter;
				$out[] = $letter . ' ' . $seed;
			}
		}

		if ( $modes['latin'] ) {
			foreach ( range( 'a', 'z' ) as $letter ) {
				$out[] = $seed . ' ' . $letter;
				$out[] = $letter . ' ' . $seed;
			}
		}

		if ( $modes['digits'] ) {
			for ( $i = 0; $i <= 9; $i++ ) {
				$out[] = $seed . ' ' . $i;
			}
			foreach ( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ) as $digit ) {
				$out[] = $seed . ' ' . $digit;
			}
		}

		if ( $modes['modifiers'] ) {
			foreach ( self::modifiers() as $mod ) {
				$out[] = $mod . ' ' . $seed;
				$out[] = $seed . ' ' . $mod;
			}
		}

		$unique = array();
		foreach ( $out as $q ) {
			$unique[ $q ] = true;
		}
		return array_keys( $unique );
	}

	/**
	 * @param string $body
	 * @return string[]
	 */
	public static function parse_response( $body ) {
		$body = is_string( $body ) ? $body : '';
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
		$body = preg_replace( '/^\)\]\}\'\s*/', '', $body );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data[1] ) || ! is_array( $data[1] ) ) {
			return array();
		}

		$items = array();
		foreach ( $data[1] as $row ) {
			$phrase = '';
			if ( is_string( $row ) ) {
				$phrase = $row;
			} elseif ( is_array( $row ) && isset( $row[0] ) && is_string( $row[0] ) ) {
				$phrase = $row[0];
			}
			$phrase = trim( $phrase );
			if ( $phrase !== '' ) {
				$items[] = $phrase;
			}
		}

		$unique = array();
		foreach ( $items as $phrase ) {
			$unique[ $phrase ] = true;
		}
		return array_keys( $unique );
	}

	/**
	 * @param string $hl
	 * @param string $gl
	 * @return string
	 */
	public static function suggest_url( $query, $hl = 'fa', $gl = 'ir' ) {
		$hl = preg_replace( '/[^a-zA-Z\-]/', '', (string) $hl );
		$gl = preg_replace( '/[^a-zA-Z]/', '', (string) $gl );
		if ( $hl === '' ) {
			$hl = 'fa';
		}
		if ( $gl === '' ) {
			$gl = 'ir';
		}

		return 'https://suggestqueries.google.com/complete/search?' . http_build_query(
			array(
				'client' => 'firefox',
				'hl'     => $hl,
				'gl'     => $gl,
				'q'      => (string) $query,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * یک کوئری را از گوگل می‌پرسد. فقط همان پیشنهادهایی که گوگل برمی‌گرداند.
	 *
	 * @return array{ok:bool,error:string,items:string[],status:int}
	 */
	public static function fetch( $query, $hl = 'fa', $gl = 'ir' ) {
		$url  = self::suggest_url( $query, $hl, $gl );
		$args = array(
			'timeout'     => 12,
			'redirection' => 2,
			'headers'     => array(
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Accept'          => 'application/json,text/javascript,*/*;q=0.8',
				'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
			),
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'error'  => $response->get_error_message(),
				'items'  => array(),
				'status' => 0,
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );

		if ( 429 === $status ) {
			return array(
				'ok'     => false,
				'error'  => 'limited',
				'items'  => array(),
				'status' => $status,
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			return array(
				'ok'     => false,
				'error'  => 'http_' . $status,
				'items'  => array(),
				'status' => $status,
			);
		}

		return array(
			'ok'     => true,
			'error'  => '',
			'items'  => self::parse_response( $body ),
			'status' => $status,
		);
	}
}
