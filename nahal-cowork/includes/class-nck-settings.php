<?php
defined( 'ABSPATH' ) || exit;

class NCK_Settings {

	const OPTION = 'nck_settings';

	public static function defaults() {
		$sections = NCK_Contract::default_sections();
		$packed   = array();
		foreach ( $sections as $row ) {
			$packed[] = $row['title'] . "\n" . $row['body'];
		}

		return array(
			'org_name'          => 'مجموعه فرهنگی نهال',
			'org_tagline'       => 'فضای کار، تمرکز و رشد',
			'shifts_per_month'  => 26,
			'morning_start'     => '8:00',
			'morning_end'       => '13:00',
			'evening_start'     => '16:30',
			'evening_end'       => '22:00',
			'grace_minutes'     => 15,
			'official_policy'   => 'morning',
			'full_close_dates'  => '',
			'event_notice'      => '',
			'contract_intro'    => NCK_Contract::default_intro(),
			'contract_preamble' => NCK_Contract::default_preamble(),
			'contract_sections' => implode( "\n---\n", $packed ),
			'accent'            => '#3f5d45',
			'hall_org'          => NCK_Hall::default_org(),
			'hall_signer'       => NCK_Hall::default_signer(),
			'hall_list'         => implode( "\n", NCK_Hall::default_halls() ),
			'projector_price'   => 500000,
			'projector_minutes' => 90,
			'wc_sync'           => 1,
			'cowork_fee'        => 0,
			'learner_fee'       => 0,
		);
	}

	public static function all() {
		$saved = array();
		if ( function_exists( 'get_option' ) ) {
			$saved = (array) get_option( self::OPTION, array() );
		}
		$all = array_merge( self::defaults(), $saved );
		if ( isset( $all['contract_intro'] ) && self::is_legacy_intro( $all['contract_intro'] ) ) {
			$all['contract_intro'] = NCK_Contract::default_intro();
		}
		return $all;
	}

	public static function is_legacy_intro( $text ) {
		return trim( (string) $text ) === NCK_Contract::legacy_intro();
	}

	public static function fix_legacy_intro() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) || empty( $saved['contract_intro'] ) ) {
			return false;
		}
		if ( ! self::is_legacy_intro( $saved['contract_intro'] ) ) {
			return false;
		}
		$saved['contract_intro'] = NCK_Contract::default_intro();
		update_option( self::OPTION, $saved, false );
		return true;
	}

	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( ! array_key_exists( $key, $all ) ) {
			return $default;
		}
		return $all[ $key ];
	}

	public static function hours() {
		$s = self::all();
		return array(
			'morning_start' => $s['morning_start'],
			'morning_end'   => $s['morning_end'],
			'evening_start' => $s['evening_start'],
			'evening_end'   => $s['evening_end'],
			'grace_minutes' => (int) $s['grace_minutes'],
		);
	}

	public static function official_extra() {
		return NCK_Holidays::parse_full_close_list( self::get( 'full_close_dates', '' ) );
	}

	public static function holiday_context( $jy, $jm, $jd ) {
		$extra    = self::official_extra();
		$official = NCK_Holidays::official_title( $jy, $jm, $jd );
		return array(
			'official'        => $official,
			'official_policy' => self::get( 'official_policy', 'morning' ),
			'full_close'      => $extra,
		);
	}

	public static function parse_sections( $raw = null ) {
		if ( null === $raw ) {
			$raw = self::get( 'contract_sections', '' );
		}
		$chunks = preg_split( '/\n---\n/', (string) $raw );
		$out    = array();
		foreach ( $chunks as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			$parts = explode( "\n", $chunk, 2 );
			$out[] = array(
				'title' => trim( $parts[0] ),
				'body'  => isset( $parts[1] ) ? trim( $parts[1] ) : '',
			);
		}
		return $out ? $out : NCK_Contract::default_sections();
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		$out['org_name']    = sanitize_text_field( isset( $in['org_name'] ) ? $in['org_name'] : $d['org_name'] );
		$out['org_tagline'] = sanitize_text_field( isset( $in['org_tagline'] ) ? $in['org_tagline'] : $d['org_tagline'] );

		$quota = isset( $in['shifts_per_month'] ) ? (int) NCK_Phone::latin_digits( $in['shifts_per_month'] ) : 26;
		$out['shifts_per_month'] = min( 62, max( 1, $quota ) );

		foreach ( array( 'morning_start', 'morning_end', 'evening_start', 'evening_end' ) as $k ) {
			$val = isset( $in[ $k ] ) ? NCK_Phone::latin_digits( $in[ $k ] ) : $d[ $k ];
			$out[ $k ] = null !== NCK_Shifts::parse_hhmm( $val ) ? $val : $d[ $k ];
		}

		$grace = isset( $in['grace_minutes'] ) ? (int) NCK_Phone::latin_digits( $in['grace_minutes'] ) : 15;
		$out['grace_minutes'] = min( 60, max( 0, $grace ) );

		$policy = isset( $in['official_policy'] ) ? sanitize_key( $in['official_policy'] ) : 'morning';
		$out['official_policy'] = in_array( $policy, array( 'morning', 'full', 'none' ), true ) ? $policy : 'morning';

		$out['full_close_dates']  = sanitize_textarea_field( isset( $in['full_close_dates'] ) ? $in['full_close_dates'] : '' );
		$out['event_notice']      = sanitize_textarea_field( isset( $in['event_notice'] ) ? $in['event_notice'] : '' );
		$out['contract_intro']    = sanitize_textarea_field( isset( $in['contract_intro'] ) ? $in['contract_intro'] : $d['contract_intro'] );
		$out['contract_preamble'] = sanitize_textarea_field( isset( $in['contract_preamble'] ) ? $in['contract_preamble'] : $d['contract_preamble'] );
		$out['contract_sections'] = sanitize_textarea_field( isset( $in['contract_sections'] ) ? $in['contract_sections'] : $d['contract_sections'] );

		$color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( isset( $in['accent'] ) ? $in['accent'] : '' ) : '';
		$out['accent'] = $color ? $color : $d['accent'];

		$out['hall_org']    = sanitize_text_field( isset( $in['hall_org'] ) ? $in['hall_org'] : $d['hall_org'] );
		$out['hall_signer'] = sanitize_text_field( isset( $in['hall_signer'] ) ? $in['hall_signer'] : $d['hall_signer'] );
		$out['hall_list']   = sanitize_textarea_field( isset( $in['hall_list'] ) ? $in['hall_list'] : $d['hall_list'] );

		$price = isset( $in['projector_price'] ) ? NCK_Hall::parse_amount( $in['projector_price'] ) : (int) $d['projector_price'];
		$out['projector_price'] = max( 0, $price );
		$mins = isset( $in['projector_minutes'] ) ? (int) NCK_Phone::latin_digits( $in['projector_minutes'] ) : (int) $d['projector_minutes'];
		$out['projector_minutes'] = min( 600, max( 15, $mins ) );

		$out['wc_sync'] = empty( $in['wc_sync'] ) ? 0 : 1;
		$out['cowork_fee']  = max( 0, NCK_Hall::parse_amount( isset( $in['cowork_fee'] ) ? $in['cowork_fee'] : $d['cowork_fee'] ) );
		$out['learner_fee'] = max( 0, NCK_Hall::parse_amount( isset( $in['learner_fee'] ) ? $in['learner_fee'] : $d['learner_fee'] ) );

		return $out;
	}

	public static function save( $input ) {
		$clean = self::sanitize( $input );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function contract_vars( array $person = array() ) {
		$s = self::all();
		$t = NCK_Jalali::today();
		return NCK_Contract::vars_from(
			array_merge(
				array(
					'org'     => $s['org_name'],
					'shifts'  => $s['shifts_per_month'],
					'morning' => NCK_Contract::hours_label( $s['morning_start'], $s['morning_end'] ),
					'evening' => NCK_Contract::hours_label( $s['evening_start'], $s['evening_end'] ),
					'date'    => NCK_Jalali::format_long( $t['y'], $t['m'], $t['d'] ),
				),
				$person
			)
		);
	}
}
