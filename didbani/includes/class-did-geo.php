<?php
defined( 'ABSPATH' ) || exit;

/**
 * شهرهای ایران برای SERP موبایل (مختصات + نام ارائه‌دهنده).
 */
class DID_Geo {

	/**
	 * @return array<string,array{fa:string,serp:string,dfs:string,lat:float,lng:float}>
	 */
	public static function cities() {
		return array(
			'tehran'       => array( 'fa' => 'تهران', 'serp' => 'Tehran, Tehran Province, Iran', 'dfs' => 'Tehran,Iran', 'lat' => 35.6892, 'lng' => 51.3890 ),
			'mashhad'      => array( 'fa' => 'مشهد', 'serp' => 'Mashhad, Razavi Khorasan, Iran', 'dfs' => 'Mashhad,Iran', 'lat' => 36.2605, 'lng' => 59.6168 ),
			'isfahan'      => array( 'fa' => 'اصفهان', 'serp' => 'Isfahan, Isfahan Province, Iran', 'dfs' => 'Isfahan,Iran', 'lat' => 32.6546, 'lng' => 51.6680 ),
			'karaj'        => array( 'fa' => 'کرج', 'serp' => 'Karaj, Alborz, Iran', 'dfs' => 'Karaj,Iran', 'lat' => 35.8400, 'lng' => 50.9391 ),
			'shiraz'       => array( 'fa' => 'شیراز', 'serp' => 'Shiraz, Fars, Iran', 'dfs' => 'Shiraz,Iran', 'lat' => 29.5918, 'lng' => 52.5837 ),
			'tabriz'       => array( 'fa' => 'تبریز', 'serp' => 'Tabriz, East Azerbaijan, Iran', 'dfs' => 'Tabriz,Iran', 'lat' => 38.0962, 'lng' => 46.2738 ),
			'qom'          => array( 'fa' => 'قم', 'serp' => 'Qom, Qom Province, Iran', 'dfs' => 'Qom,Iran', 'lat' => 34.6416, 'lng' => 50.8746 ),
			'ahvaz'        => array( 'fa' => 'اهواز', 'serp' => 'Ahvaz, Khuzestan, Iran', 'dfs' => 'Ahvaz,Iran', 'lat' => 31.3183, 'lng' => 48.6706 ),
			'kermanshah'   => array( 'fa' => 'کرمانشاه', 'serp' => 'Kermanshah, Kermanshah Province, Iran', 'dfs' => 'Kermanshah,Iran', 'lat' => 34.3142, 'lng' => 47.0650 ),
			'rasht'        => array( 'fa' => 'رشت', 'serp' => 'Rasht, Gilan, Iran', 'dfs' => 'Rasht,Iran', 'lat' => 37.2808, 'lng' => 49.5832 ),
			'zahedan'      => array( 'fa' => 'زاهدان', 'serp' => 'Zahedan, Sistan and Baluchestan, Iran', 'dfs' => 'Zahedan,Iran', 'lat' => 29.4963, 'lng' => 60.8629 ),
			'kerman'       => array( 'fa' => 'کرمان', 'serp' => 'Kerman, Kerman Province, Iran', 'dfs' => 'Kerman,Iran', 'lat' => 30.2839, 'lng' => 57.0834 ),
			'urmia'        => array( 'fa' => 'ارومیه', 'serp' => 'Urmia, West Azerbaijan, Iran', 'dfs' => 'Urmia,Iran', 'lat' => 37.5527, 'lng' => 45.0761 ),
			'yazd'         => array( 'fa' => 'یزد', 'serp' => 'Yazd, Yazd Province, Iran', 'dfs' => 'Yazd,Iran', 'lat' => 31.8974, 'lng' => 54.3569 ),
			'hamadan'      => array( 'fa' => 'همدان', 'serp' => 'Hamadan, Hamadan Province, Iran', 'dfs' => 'Hamadan,Iran', 'lat' => 34.7983, 'lng' => 48.5146 ),
			'ardabil'      => array( 'fa' => 'اردبیل', 'serp' => 'Ardabil, Ardabil Province, Iran', 'dfs' => 'Ardabil,Iran', 'lat' => 38.2498, 'lng' => 48.2933 ),
			'bandar_abbas' => array( 'fa' => 'بندرعباس', 'serp' => 'Bandar Abbas, Hormozgan, Iran', 'dfs' => 'Bandar Abbas,Iran', 'lat' => 27.1832, 'lng' => 56.2666 ),
			'arak'         => array( 'fa' => 'اراک', 'serp' => 'Arak, Markazi, Iran', 'dfs' => 'Arak,Iran', 'lat' => 34.0917, 'lng' => 49.6892 ),
			'sari'         => array( 'fa' => 'ساری', 'serp' => 'Sari, Mazandaran, Iran', 'dfs' => 'Sari,Iran', 'lat' => 36.5633, 'lng' => 53.0601 ),
			'khorramabad'  => array( 'fa' => 'خرم‌آباد', 'serp' => 'Khorramabad, Lorestan, Iran', 'dfs' => 'Khorramabad,Iran', 'lat' => 33.4878, 'lng' => 48.3558 ),
		);
	}

	/**
	 * @param string $slug
	 * @return array|null
	 */
	public static function city( $slug ) {
		$all = self::cities();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	public static function label( $slug ) {
		$c = self::city( $slug );
		return $c ? $c['fa'] : $slug;
	}

	/**
	 * @param array $slugs
	 * @return string[]
	 */
	public static function sanitize_slugs( array $slugs ) {
		$all = self::cities();
		$out = array();
		foreach ( $slugs as $s ) {
			$s = strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $s ) );
			if ( isset( $all[ $s ] ) && ! in_array( $s, $out, true ) ) {
				$out[] = $s;
			}
		}
		if ( ! $out ) {
			$out[] = 'tehran';
		}
		return $out;
	}

	/**
	 * @param string $device
	 * @return string
	 */
	public static function sanitize_device( $device ) {
		return 'desktop' === $device ? 'desktop' : 'mobile';
	}

	public static function device_label( $device ) {
		return 'desktop' === $device ? 'دسکتاپ' : 'موبایل';
	}

	public static function mobile_ua() {
		return 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
	}
}
