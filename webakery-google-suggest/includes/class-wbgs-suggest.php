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
		if ( class_exists( 'WBGS_Affixes' ) ) {
			return WBGS_Affixes::probe_list();
		}
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
			'longtail'  => ! empty( $modes['longtail'] ),
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
			if ( class_exists( 'WBGS_Affixes' ) ) {
				foreach ( WBGS_Affixes::queries_for( $seed ) as $q ) {
					$out[] = $q;
				}
			} else {
				foreach ( self::modifiers() as $mod ) {
					$out[] = $mod . ' ' . $seed;
					$out[] = $seed . ' ' . $mod;
				}
			}
		}

		$unique = array();
		foreach ( $out as $q ) {
			$unique[ $q ] = true;
		}
		return array_keys( $unique );
	}

	/**
	 * تعداد واژه‌های عبارت (برای تشخیص لانگ‌تیل).
	 *
	 * @param string $text
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = self::normalize_seed( $text );
		if ( $text === '' ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * لانگ‌تیل: چهار واژه یا بیشتر.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function is_longtail( $text ) {
		return self::word_count( $text ) >= 4;
	}

	/**
	 * کوئری ادامهٔ سجست برای یک عبارت واقعی گوگل (فاصلهٔ بعد).
	 * خودِ کیورد را نمی‌سازد؛ فقط از گوگل دنباله می‌پرسد.
	 *
	 * @param string $phrase
	 * @return string[]
	 */
	public static function expand_queries( $phrase ) {
		$phrase = self::normalize_seed( $phrase );
		if ( $phrase === '' ) {
			return array();
		}
		$n = self::word_count( $phrase );
		if ( $n < 2 || $n > 6 ) {
			return array();
		}
		return array( $phrase . ' ' );
	}

	/**
	 * @param string $body
	 * @return string[]
	 */
	public static function parse_response( $body ) {
		$out = array();
		foreach ( self::parse_enriched( $body ) as $row ) {
			$out[] = $row['text'];
		}
		return $out;
	}

	/**
	 * متن + امتیاز واقعی google:suggestrelevance (اگر گوگل فرستاده باشد).
	 *
	 * @param string $body
	 * @return array<int,array{text:string,relevance:int,rank:int}>
	 */
	public static function parse_enriched( $body ) {
		$body = is_string( $body ) ? $body : '';
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
		$body = preg_replace( '/^\)\]\}\'\s*/', '', $body );
		$body = self::unwrap_suggest( $body );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data[1] ) || ! is_array( $data[1] ) ) {
			return array();
		}

		$rel = array();
		foreach ( $data as $part ) {
			if ( is_array( $part ) && isset( $part['google:suggestrelevance'] ) && is_array( $part['google:suggestrelevance'] ) ) {
				$rel = $part['google:suggestrelevance'];
				break;
			}
		}

		$seen  = array();
		$items = array();
		$rank  = 0;
		foreach ( $data[1] as $i => $row ) {
			$phrase = '';
			if ( is_string( $row ) ) {
				$phrase = $row;
			} elseif ( is_array( $row ) && isset( $row[0] ) && is_string( $row[0] ) ) {
				$phrase = $row[0];
			}
			$phrase = trim( $phrase );
			if ( $phrase === '' || isset( $seen[ $phrase ] ) ) {
				continue;
			}
			$seen[ $phrase ] = true;
			$rank++;
			$items[] = array(
				'text'       => $phrase,
				'relevance'  => isset( $rel[ $i ] ) ? (int) $rel[ $i ] : 0,
				'rank'       => $rank,
			);
		}
		return $items;
	}

	/**
	 * @param string $hl
	 * @param string $gl
	 * @return string
	 */
	/**
	 * پاسخ یوتیوب: window.google.ac.h([...])
	 *
	 * @param string $body
	 * @return string
	 */
	public static function unwrap_suggest( $body ) {
		$body = trim( (string) $body );
		if ( preg_match( '/^window\.google\.ac\.h\((.*)\)\s*;?\s*$/s', $body, $m ) ) {
			return $m[1];
		}
		return $body;
	}

	/**
	 * @return array<string,string>
	 */
	public static function sources() {
		return array(
			'google'  => 'گوگل',
			'youtube' => 'یوتیوب',
		);
	}

	/**
	 * @param string $source
	 * @return string
	 */
	public static function normalize_source( $source ) {
		$source = preg_replace( '/[^a-z]/', '', strtolower( (string) $source ) );
		return isset( self::sources()[ $source ] ) ? $source : 'google';
	}

	/**
	 * @param string $query
	 * @param string $hl
	 * @param string $gl
	 * @param string $source
	 * @return string
	 */
	public static function suggest_url( $query, $hl = 'fa', $gl = 'ir', $source = 'google' ) {
		$hl     = preg_replace( '/[^a-zA-Z\-]/', '', (string) $hl );
		$gl     = preg_replace( '/[^a-zA-Z]/', '', (string) $gl );
		$source = self::normalize_source( $source );
		if ( $hl === '' ) {
			$hl = 'fa';
		}
		if ( $gl === '' ) {
			$gl = 'ir';
		}

		$args = array(
			'client' => 'youtube' === $source ? 'youtube' : 'chrome',
			'hl'     => $hl,
			'gl'     => $gl,
			'q'      => (string) $query,
		);
		if ( 'youtube' === $source ) {
			$args['ds'] = 'yt';
		}

		return 'https://suggestqueries.google.com/complete/search?' . http_build_query(
			$args,
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
	public static function fetch( $query, $hl = 'fa', $gl = 'ir', $source = 'google' ) {
		$url  = self::suggest_url( $query, $hl, $gl, $source );
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
			'items'  => self::parse_enriched( $body ),
			'status' => $status,
		);
	}
}
