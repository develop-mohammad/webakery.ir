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
			'logo_id'           => 0,
			'hall_org'          => NCK_Hall::default_org(),
			'hall_signer'       => NCK_Hall::default_signer(),
			'hall_list'         => implode( "\n", NCK_Hall::default_halls() ),
			'hall_space_library'  => 1500000,
			'hall_space_cafe'     => 2500000,
			'hall_space_woodshop' => 1500000,
			'projector_price'   => 500000,
			'projector_minutes' => 90,
			'wc_sync'           => 1,
			'cowork_fee'        => 1950000,
			'fee_m1_one'        => 1950000,
			'fee_m1_two'        => 3900000,
			'fee_m2_one'        => 3600000,
			'fee_m2_two'        => 7200000,
			'fee_m3_one'        => 5250000,
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
		return in_array( trim( (string) $text ), NCK_Contract::legacy_intros(), true );
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

		$logo_raw = isset( $in['logo_id'] ) ? $in['logo_id'] : 0;
		$logo_id  = function_exists( 'absint' ) ? absint( $logo_raw ) : (int) $logo_raw;
		if ( $logo_id && function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $logo_id ) ) {
			$logo_id = 0;
		}
		$out['logo_id'] = $logo_id;

		$out['hall_org']    = sanitize_text_field( isset( $in['hall_org'] ) ? $in['hall_org'] : $d['hall_org'] );
		$out['hall_signer'] = sanitize_text_field( isset( $in['hall_signer'] ) ? $in['hall_signer'] : $d['hall_signer'] );
		foreach ( array( 'hall_space_library', 'hall_space_cafe', 'hall_space_woodshop' ) as $space_fee ) {
			$out[ $space_fee ] = max( 0, NCK_Hall::parse_amount( isset( $in[ $space_fee ] ) ? $in[ $space_fee ] : $d[ $space_fee ] ) );
		}
		$out['hall_list'] = implode( "\n", NCK_Hall::space_names() );

		$price = isset( $in['projector_price'] ) ? NCK_Hall::parse_amount( $in['projector_price'] ) : (int) $d['projector_price'];
		$out['projector_price'] = max( 0, $price );
		$mins = isset( $in['projector_minutes'] ) ? (int) NCK_Phone::latin_digits( $in['projector_minutes'] ) : (int) $d['projector_minutes'];
		$out['projector_minutes'] = min( 600, max( 15, $mins ) );

		$out['wc_sync'] = empty( $in['wc_sync'] ) ? 0 : 1;
		foreach ( array( 'fee_m1_one', 'fee_m1_two', 'fee_m2_one', 'fee_m2_two', 'fee_m3_one' ) as $fee_key ) {
			$out[ $fee_key ] = max( 0, NCK_Hall::parse_amount( isset( $in[ $fee_key ] ) ? $in[ $fee_key ] : $d[ $fee_key ] ) );
		}
		$out['cowork_fee']  = $out['fee_m1_one'];
		$out['learner_fee'] = max( 0, NCK_Hall::parse_amount( isset( $in['learner_fee'] ) ? $in['learner_fee'] : $d['learner_fee'] ) );

		return $out;
	}

	public static function logo_id() {
		return (int) self::get( 'logo_id', 0 );
	}

	public static function logo_url( $size = 'medium' ) {
		$id = self::logo_id();
		if ( $id < 1 || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, $size );
		if ( ! $url && function_exists( 'wp_get_attachment_url' ) ) {
			$url = wp_get_attachment_url( $id );
		}
		return $url ? (string) $url : '';
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
