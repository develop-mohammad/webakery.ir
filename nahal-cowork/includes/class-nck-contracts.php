<?php
defined( 'ABSPATH' ) || exit;

class NCK_Contracts {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nck_contracts';
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function by_token( $token ) {
		$token = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token ) );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE print_token = %s', $token ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	public static function latest_for_member( $member_id, $kind = '' ) {
		global $wpdb;
		if ( $kind ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE member_id = %d AND status = %s AND kind = %s ORDER BY id DESC LIMIT 1',
					(int) $member_id,
					'signed',
					$kind
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE member_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
					(int) $member_id,
					'signed'
				),
				ARRAY_A
			);
		}
		return $row ? $row : null;
	}

	public static function kind_of( array $row ) {
		$kind = isset( $row['kind'] ) ? $row['kind'] : '';
		if ( 'hall' === $kind ) {
			return 'hall';
		}
		return 'cowork';
	}

	public static function kind_label( $kind ) {
		return 'hall' === $kind ? 'اجاره سالن' : 'فضای کار اشتراکی';
	}

	public static function payload( array $row ) {
		if ( empty( $row['payload'] ) ) {
			return array();
		}
		$data = json_decode( $row['payload'], true );
		return is_array( $data ) ? $data : array();
	}

	public static function plan_label( array $row ) {
		if ( 'hall' === self::kind_of( $row ) ) {
			$p = self::payload( $row );
			$hall = isset( $p['hall_name'] ) ? $p['hall_name'] : 'سالن';
			return 'اجاره سالن — ' . $hall;
		}
		$plans = NCK_Shifts::plan_labels();
		$plan  = isset( $row['plan'] ) ? $row['plan'] : '';
		return isset( $plans[ $plan ] ) ? $plans[ $plan ] : $plan;
	}

	public static function count_month( $jy, $jm, $kind = '' ) {
		list( $start, $end ) = NCK_Jalali::month_range_g( $jy, $jm );
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s AND DATE(signed_at) BETWEEN %s AND %s';
		$args = array( 'signed', $start, $end );
		if ( $kind ) {
			$sql   .= ' AND kind = %s';
			$args[] = $kind;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
	}

	public static function search( $q = '', $limit = 40, $kind = '' ) {
		global $wpdb;
		$limit = max( 1, min( 200, (int) $limit ) );
		$q     = trim( (string) $q );
		$where = '1=1';
		$args  = array();
		if ( $kind ) {
			$where .= ' AND kind = %s';
			$args[] = $kind;
		}
		if ( $q !== '' ) {
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$where  .= ' AND (full_name LIKE %s OR phone LIKE %s OR national_id LIKE %s)';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}
		$args[] = $limit;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT %d',
				...$args
			),
			ARRAY_A
		);
	}

	public static function create_signed( $member, $plan, $signature, $ip, $extra = array() ) {
		$token = bin2hex( random_bytes( 16 ) );
		$now   = current_time( 'mysql' );
		$kind  = isset( $extra['kind'] ) && 'hall' === $extra['kind'] ? 'hall' : 'cowork';
		$nid   = isset( $extra['national_id'] ) ? (string) $extra['national_id'] : '';
		$json  = '';
		if ( ! empty( $extra['payload'] ) && is_array( $extra['payload'] ) ) {
			$json = wp_json_encode( $extra['payload'] );
		}
		global $wpdb;
		$ok    = $wpdb->insert(
			self::table(),
			array(
				'member_id'     => (int) $member['id'],
				'kind'          => $kind,
				'plan'          => $plan,
				'full_name'     => $member['full_name'],
				'honorific'     => $member['honorific'],
				'phone'         => $member['phone'],
				'national_id'  => $nid,
				'print_token'   => $token,
				'signature_png' => $signature,
				'payload'       => $json,
				'signed_at'     => $now,
				'signed_ip'     => substr( (string) $ip, 0, 45 ),
				'status'        => 'signed',
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return null;
		}
		return self::get( (int) $wpdb->insert_id );
	}

	public static function void_contract( $id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => 'void' ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function print_url( $token ) {
		return add_query_arg(
			array(
				'nck_print' => $token,
			),
			home_url( '/' )
		);
	}

	public static function sign_flow( $name, $honorific, $phone, $plan, $signature, $ip ) {
		$phone = NCK_Phone::normalize( $phone );
		$name  = sanitize_text_field( $name );
		$plan  = in_array( $plan, array( 'morning', 'evening', 'both' ), true ) ? $plan : '';

		if ( $name === '' || mb_strlen( $name ) < 3 ) {
			return array( 'ok' => false, 'message' => 'نام و نام خانوادگی را کامل وارد کنید.' );
		}
		if ( ! $phone ) {
			return array( 'ok' => false, 'message' => 'شماره موبایل معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹' );
		}
		if ( ! $plan ) {
			return array( 'ok' => false, 'message' => 'نوع اشتراک (صبح، عصر یا هر دو) را انتخاب کنید.' );
		}

		$sig = NCK_Contract::validate_signature( $signature );
		if ( ! $sig['ok'] ) {
			return $sig;
		}

		$member = NCK_Members::upsert( $name, $honorific, $phone );
		if ( ! $member ) {
			return array( 'ok' => false, 'message' => 'ثبت عضو انجام نشد.' );
		}

		$contract = self::create_signed(
			$member,
			$plan,
			$signature,
			$ip,
			array(
				'kind' => 'cowork',
			)
		);
		if ( ! $contract ) {
			return array( 'ok' => false, 'message' => 'ذخیره قرارداد انجام نشد.' );
		}

		NCK_Subscriptions::replace_for_contract(
			(int) $member['id'],
			(int) $contract['id'],
			$plan,
			(int) NCK_Settings::get( 'shifts_per_month', 26 )
		);

		return array(
			'ok'       => true,
			'message'  => 'قرارداد با موفقیت ثبت شد.',
			'member'   => $member,
			'contract' => $contract,
			'print'    => self::print_url( $contract['print_token'] ),
		);
	}

	public static function sign_hall_flow( array $input, $signature, $ip ) {
		$check = NCK_Hall::validate( $input );
		if ( empty( $check['ok'] ) ) {
			return $check;
		}
		$sig = NCK_Contract::validate_signature( $signature );
		if ( ! $sig['ok'] ) {
			return $sig;
		}

		$p      = $check['payload'];
		$member = NCK_Members::upsert( $p['name'], $p['honorific'], $p['phone'], $p['national_id'] );
		if ( ! $member ) {
			return array( 'ok' => false, 'message' => 'ثبت برگزارکننده انجام نشد.' );
		}

		$contract = self::create_signed(
			$member,
			'hall',
			$signature,
			$ip,
			array(
				'kind'        => 'hall',
				'national_id' => $p['national_id'],
				'payload'     => $p,
			)
		);
		if ( ! $contract ) {
			return array( 'ok' => false, 'message' => 'ذخیره قرارداد سالن انجام نشد.' );
		}

		return array(
			'ok'       => true,
			'message'  => 'قرارداد اجاره سالن ثبت شد.',
			'member'   => $member,
			'contract' => $contract,
			'print'    => self::print_url( $contract['print_token'] ),
		);
	}
}
