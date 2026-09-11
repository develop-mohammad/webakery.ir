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

	public static function kinds() {
		return array( 'cowork', 'hall', 'learner', 'form' );
	}

	public static function kind_of( array $row ) {
		$kind = isset( $row['kind'] ) ? $row['kind'] : '';
		if ( in_array( $kind, array( 'hall', 'learner', 'form' ), true ) ) {
			return $kind;
		}
		return 'cowork';
	}

	public static function kind_label( $kind ) {
		if ( 'hall' === $kind ) {
			return 'اجاره سالن';
		}
		if ( 'learner' === $kind ) {
			return 'پذیرش فراگیر';
		}
		if ( 'form' === $kind ) {
			return 'فرم سفارشی';
		}
		return 'فضای کار اشتراکی';
	}

	public static function payload( array $row ) {
		if ( empty( $row['payload'] ) ) {
			return array();
		}
		$data = json_decode( $row['payload'], true );
		return is_array( $data ) ? $data : array();
	}

	public static function plan_label( array $row ) {
		$kind = self::kind_of( $row );
		if ( 'hall' === $kind ) {
			$p = self::payload( $row );
			$hall = isset( $p['hall_name'] ) ? $p['hall_name'] : 'سالن';
			return 'اجاره سالن — ' . $hall;
		}
		if ( 'learner' === $kind ) {
			$p    = self::payload( $row );
			$term = NCK_Learner::join_labels( isset( $p['term'] ) ? (array) $p['term'] : array(), NCK_Learner::term_options() );
			$sea  = NCK_Learner::join_labels( isset( $p['seasonal'] ) ? (array) $p['seasonal'] : array(), NCK_Learner::seasonal_options() );
			$bits = array();
			if ( $term && '—' !== $term ) {
				$bits[] = $term;
			}
			if ( $sea && '—' !== $sea ) {
				$bits[] = $sea;
			}
			return $bits ? implode( '، ', $bits ) : 'پذیرش فراگیر';
		}
		if ( 'form' === $kind ) {
			$p = self::payload( $row );
			if ( ! empty( $p['form_title'] ) ) {
				return (string) $p['form_title'];
			}
			return 'فرم سفارشی';
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
		$kind = isset( $extra['kind'] ) ? (string) $extra['kind'] : 'cowork';
		if ( ! in_array( $kind, self::kinds(), true ) ) {
			$kind = 'cowork';
		}
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

	public static function merge_payload( $id, array $extra ) {
		$row = self::get( (int) $id );
		if ( ! $row ) {
			return null;
		}
		$payload = array_merge( self::payload( $row ), $extra );
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'payload' => wp_json_encode( $payload ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
		return self::get( (int) $id );
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

	public static function sign_flow( $name, $honorific, $phone, $plan, $signature, $ip, array $pay_in = array(), $shift = '' ) {
		$phone = NCK_Phone::normalize( $phone );
		$name  = sanitize_text_field( $name );
		$plan  = NCK_Shifts::compose_plan( $plan, $shift );

		if ( $name === '' || mb_strlen( $name ) < 3 ) {
			return array( 'ok' => false, 'message' => 'نام و نام خانوادگی را کامل وارد کنید.' );
		}
		if ( ! $phone ) {
			return array( 'ok' => false, 'message' => 'شماره موبایل معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹' );
		}
		if ( ! $plan ) {
			return array( 'ok' => false, 'message' => 'نوع اشتراک را انتخاب کنید. برای تک‌شیفت، صبح یا عصر را هم مشخص کنید.' );
		}

		$fee = NCK_Shifts::package_price( $plan );
		$pay = NCK_Pay::parse_front_payment( $pay_in, $fee );
		if ( empty( $pay['ok'] ) ) {
			return $pay;
		}
		$pkg_id = NCK_Shifts::package_id_from_plan( $plan );
		$pkg    = NCK_Shifts::package( $pkg_id );
		if ( $pkg ) {
			$pay['payload']['package'] = $pkg['id'];
			$pay['payload']['months']  = (int) $pkg['months'];
		}

		$gate = NCK_Pay::assert_can_checkout(
			isset( $pay['payload']['pay_amount'] ) ? $pay['payload']['pay_amount'] : 0,
			isset( $pay['payload']['payment'] ) ? $pay['payload']['payment'] : 'site'
		);
		if ( empty( $gate['ok'] ) ) {
			return $gate;
		}

		$sig = NCK_Contract::prepare_signature( $signature );
		if ( empty( $sig['ok'] ) ) {
			return $sig;
		}
		$signature = $sig['data'];

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
				'kind'    => 'cowork',
				'payload' => $pay['payload'],
			)
		);
		if ( ! $contract ) {
			return array( 'ok' => false, 'message' => 'ذخیره قرارداد انجام نشد.' );
		}

		return self::finish_sign(
			$contract,
			$member,
			'قرارداد با موفقیت ثبت شد.',
			$pay['payload'],
			$plan
		);
	}

	public static function sign_hall_flow( array $input, $signature, $ip ) {
		$check = NCK_Hall::validate( $input );
		if ( empty( $check['ok'] ) ) {
			return $check;
		}
		$p    = $check['payload'];
		$gate = NCK_Pay::assert_can_checkout(
			isset( $p['pay_amount'] ) ? $p['pay_amount'] : ( isset( $p['amount'] ) ? $p['amount'] : 0 ),
			isset( $p['payment'] ) ? $p['payment'] : 'site'
		);
		if ( empty( $gate['ok'] ) ) {
			return $gate;
		}
		$sig = NCK_Contract::prepare_signature( $signature );
		if ( empty( $sig['ok'] ) ) {
			return $sig;
		}
		$signature = $sig['data'];

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

		return self::finish_sign( $contract, $member, 'قرارداد اجاره سالن ثبت شد.', $p );
	}

	public static function sign_learner_flow( array $input, $signature, $ip ) {
		$check = NCK_Learner::validate( $input );
		if ( empty( $check['ok'] ) ) {
			return $check;
		}
		$p    = $check['payload'];
		$gate = NCK_Pay::assert_can_checkout(
			isset( $p['pay_amount'] ) ? $p['pay_amount'] : 0,
			isset( $p['payment'] ) ? $p['payment'] : 'site'
		);
		if ( empty( $gate['ok'] ) ) {
			return $gate;
		}
		$sig = NCK_Contract::prepare_signature( $signature );
		if ( empty( $sig['ok'] ) ) {
			return $sig;
		}
		$signature = $sig['data'];

		$member = NCK_Members::upsert( $p['name'], 'mr', $p['contact_phone'], $p['national_id'] );
		if ( ! $member ) {
			return array( 'ok' => false, 'message' => 'ثبت فراگیر انجام نشد.' );
		}

		$contract = self::create_signed(
			$member,
			'learner',
			$signature,
			$ip,
			array(
				'kind'        => 'learner',
				'national_id' => $p['national_id'],
				'payload'     => $p,
			)
		);
		if ( ! $contract ) {
			return array( 'ok' => false, 'message' => 'ذخیره فرم پذیرش انجام نشد.' );
		}

		return self::finish_sign( $contract, $member, 'فرم پذیرش فراگیر ثبت شد.', $p );
	}

	public static function sign_form_flow( array $form, array $input, $signature, $ip ) {
		$check = NCK_Forms::validate( $form, $input );
		if ( empty( $check['ok'] ) ) {
			return $check;
		}
		$p    = $check['payload'];
		$gate = NCK_Pay::assert_can_checkout(
			isset( $p['pay_amount'] ) ? $p['pay_amount'] : 0,
			isset( $p['payment'] ) ? $p['payment'] : 'site'
		);
		if ( empty( $gate['ok'] ) ) {
			return $gate;
		}
		if ( ! empty( $form['require_signature'] ) ) {
			$sig = NCK_Contract::prepare_signature( $signature );
			if ( empty( $sig['ok'] ) ) {
				return $sig;
			}
			$signature = $sig['data'];
		} else {
			$signature = '';
		}

		$member = NCK_Members::upsert( $p['name'], 'mr', $p['phone'], $p['national_id'] );
		if ( ! $member ) {
			return array( 'ok' => false, 'message' => 'ثبت عضو انجام نشد.' );
		}

		$contract = self::create_signed(
			$member,
			'form',
			$signature,
			$ip,
			array(
				'kind'        => 'form',
				'national_id' => $p['national_id'],
				'payload'     => $p,
			)
		);
		if ( ! $contract ) {
			return array( 'ok' => false, 'message' => 'ذخیره فرم انجام نشد.' );
		}

		return self::finish_sign( $contract, $member, 'فرم «' . $form['title'] . '» ثبت شد.', $p );
	}

	/**
	 * پس از ذخیره قرارداد: اگر پرداخت سایت است به سبد ووکامرس می‌رود.
	 *
	 * @return array{ok:bool,message?:string,pay_url?:string,print?:string,member?:array,contract?:array}
	 */
	private static function finish_sign( $contract, $member, $message, array $pay_payload, $cowork_plan = '' ) {
		$paid = NCK_Pay::after_sign( $contract );
		if ( empty( $paid['ok'] ) ) {
			return $paid;
		}
		$pay_url = isset( $paid['pay_url'] ) ? (string) $paid['pay_url'] : '';
		if ( $pay_url === '' && $cowork_plan !== '' && ! empty( $member['id'] ) && class_exists( 'NCK_Subscriptions' ) ) {
			NCK_Subscriptions::replace_for_contract(
				(int) $member['id'],
				(int) $contract['id'],
				$cowork_plan,
				(int) NCK_Settings::get( 'shifts_per_month', 26 )
			);
		}
		$note = NCK_Pay::tracking_note( $pay_payload );
		if ( $pay_url !== '' ) {
			$message = 'ثبت شد. در حال انتقال به پرداخت سایت، مثل خرید محصولات ووکامرس…' . $note;
		} else {
			$message .= $note;
		}
		return array(
			'ok'       => true,
			'message'  => $message,
			'member'   => $member,
			'contract' => $contract,
			'print'    => self::print_url( $contract['print_token'] ),
			'pay_url'  => $pay_url,
		);
	}
}
