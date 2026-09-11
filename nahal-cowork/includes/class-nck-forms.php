<?php
defined( 'ABSPATH' ) || exit;

/**
 * فرم‌های سفارشی نهال — ذخیره در تنظیمات، شورت‌کد [nahal_form].
 */
class NCK_Forms {

	const OPTION = 'nck_custom_forms';

	/** @var array<string,array> */
	private static $memory = array();

	public static function field_types() {
		return array(
			'text'            => 'متن',
			'tel'             => 'موبایل',
			'email'           => 'ایمیل',
			'textarea'        => 'چندخطی',
			'date'            => 'تاریخ شمسی',
			'number'          => 'عدد',
			'amount'          => 'مبلغ (تومان)',
			'radio'           => 'تک‌انتخاب',
			'checkbox'        => 'چند‌انتخاب',
			'select'          => 'فهرست کشویی',
			'payment_method'  => 'روش پرداخت',
			'note'            => 'توضیح ثابت',
		);
	}

	public static function roles() {
		return array(
			''            => '—',
			'name'        => 'نام (عضو و سفارش)',
			'phone'       => 'موبایل (عضو و سفارش)',
			'email'       => 'ایمیل سفارش',
			'national_id' => 'کد ملی',
			'amount'      => 'مبلغ سفارش',
			'address'     => 'آدرس',
		);
	}

	public static function reserved_slugs() {
		return array( 'contract', 'hall', 'admission', 'learner', 'portal', 'cowork', 'form' );
	}

	public static function reserved_names() {
		return array( 'action', 'nonce', 'signature', 'form_id', 'form_slug', 'agree', 'submit' );
	}

	public static function blank() {
		return array(
			'id'                 => '',
			'slug'               => '',
			'title'              => 'فرم جدید',
			'kicker'             => 'مجموعه فرهنگی نهال',
			'tagline'            => '',
			'slogan'             => 'هوای رشدت رو داریم',
			'status'             => 'publish',
			'require_signature' => 1,
			'product_id'         => 0,
			'payment'            => array(
				'enabled'   => 1,
				'amount'    => 0,
				'methods'   => array( 'site' ),
				'item_name' => '',
			),
			'steps'              => array(
				array(
					'label'  => 'اطلاعات',
					'fields' => array(
						array(
							'type'     => 'text',
							'name'     => 'name',
							'label'    => 'نام و نام خانوادگی',
							'required' => 1,
							'role'     => 'name',
							'options'  => '',
						),
						array(
							'type'     => 'tel',
							'name'     => 'phone',
							'label'    => 'موبایل',
							'required' => 1,
							'role'     => 'phone',
							'options'  => '',
						),
					),
				),
			),
		);
	}

	public static function all() {
		$rows = self::store_get();
		$out  = array();
		foreach ( $rows as $row ) {
			$clean = self::sanitize_form( $row );
			if ( $clean['id'] !== '' ) {
				$out[ $clean['id'] ] = $clean;
			}
		}
		return $out;
	}

	public static function published() {
		$out = array();
		foreach ( self::all() as $id => $form ) {
			if ( 'publish' === $form['status'] ) {
				$out[ $id ] = $form;
			}
		}
		return $out;
	}

	public static function get( $id ) {
		$id = self::id_key( $id );
		if ( $id === '' ) {
			return null;
		}
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	public static function by_slug( $slug ) {
		$slug = self::slugify( $slug );
		if ( $slug === '' ) {
			return null;
		}
		foreach ( self::all() as $form ) {
			if ( $form['slug'] === $slug ) {
				return $form;
			}
		}
		return null;
	}

	public static function save( $input ) {
		$form = self::sanitize_form( $input );
		if ( $form['id'] === '' ) {
			$form['id'] = self::new_id();
		}
		$form['slug'] = self::unique_slug( $form['slug'] !== '' ? $form['slug'] : $form['title'], $form['id'] );
		if ( class_exists( 'NCK_Pay' ) ) {
			$pid = NCK_Pay::ensure_form_product( $form );
			if ( $pid ) {
				$form['product_id'] = $pid;
			}
		}
		$all                = self::all();
		$all[ $form['id'] ] = $form;
		self::store_set( $all );
		return $form;
	}

	public static function delete( $id ) {
		$id = self::id_key( $id );
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		if ( class_exists( 'NCK_Pay' ) ) {
			NCK_Pay::trash_form_product( $all[ $id ] );
		}
		unset( $all[ $id ] );
		self::store_set( $all );
		return true;
	}

	public static function sync_all_products() {
		if ( ! class_exists( 'NCK_Pay' ) || ! NCK_Pay::wc_ready() ) {
			return;
		}
		$all     = self::all();
		$changed = false;
		foreach ( $all as $id => $form ) {
			$existing = isset( $form['product_id'] ) ? (int) $form['product_id'] : 0;
			if ( $existing && function_exists( 'wc_get_product' ) && wc_get_product( $existing ) ) {
				continue;
			}
			$pid = NCK_Pay::ensure_form_product( $form );
			if ( $pid ) {
				$form['product_id'] = $pid;
				$all[ $id ]         = $form;
				$changed            = true;
			}
		}
		if ( $changed ) {
			self::store_set( $all );
		}
	}

	public static function shortcode( array $form ) {
		$slug = isset( $form['slug'] ) ? $form['slug'] : '';
		if ( $slug === '' ) {
			return '';
		}
		return '[nahal_form slug="' . $slug . '"]';
	}

	public static function sanitize_form( $input ) {
		$d  = self::blank();
		$in = is_array( $input ) ? $input : array();
		$out = array();

		$out['id']     = self::id_key( isset( $in['id'] ) ? $in['id'] : '' );
		$out['title']  = self::text( isset( $in['title'] ) ? $in['title'] : $d['title'], 120 );
		if ( $out['title'] === '' ) {
			$out['title'] = $d['title'];
		}
		$out['slug']    = self::slugify( isset( $in['slug'] ) ? $in['slug'] : '' );
		$out['kicker']  = self::text( isset( $in['kicker'] ) ? $in['kicker'] : $d['kicker'], 160 );
		$out['tagline'] = self::text( isset( $in['tagline'] ) ? $in['tagline'] : '', 220 );
		$out['slogan']  = self::text( isset( $in['slogan'] ) ? $in['slogan'] : $d['slogan'], 160 );
		$status         = isset( $in['status'] ) ? self::key( $in['status'] ) : 'publish';
		$out['status']  = in_array( $status, array( 'publish', 'draft' ), true ) ? $status : 'publish';
		$out['require_signature'] = empty( $in['require_signature'] ) ? 0 : 1;
		$out['product_id']        = isset( $in['product_id'] ) ? max( 0, (int) $in['product_id'] ) : 0;

		$pay_in = isset( $in['payment'] ) && is_array( $in['payment'] ) ? $in['payment'] : array();
		$out['payment'] = array(
			'enabled'   => empty( $pay_in['enabled'] ) ? 0 : 1,
			'amount'    => max( 0, NCK_Hall::parse_amount( isset( $pay_in['amount'] ) ? $pay_in['amount'] : 0 ) ),
			'methods'   => array( 'site' ),
			'item_name' => self::text( isset( $pay_in['item_name'] ) ? $pay_in['item_name'] : '', 160 ),
		);

		$steps_in = isset( $in['steps'] ) && is_array( $in['steps'] ) ? $in['steps'] : $d['steps'];
		$out['steps'] = self::sanitize_steps( $steps_in );
		if ( ! $out['steps'] ) {
			$out['steps'] = $d['steps'];
		}

		return $out;
	}

	public static function unique_slug( $wanted, $except_id = '' ) {
		$base = self::slugify( $wanted );
		if ( $base === '' || in_array( $base, self::reserved_slugs(), true ) ) {
			$base = 'form';
		}
		$except_id = self::id_key( $except_id );
		$slug      = $base;
		$i         = 2;
		while ( self::slug_taken( $slug, $except_id ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		return $slug;
	}

	/**
	 * مراحل نمایش: مراحل تعریف‌شده + مرحله پرداخت در صورت نیاز.
	 *
	 * @return array<int,array>
	 */
	public static function display_steps( array $form ) {
		$steps = isset( $form['steps'] ) && is_array( $form['steps'] ) ? $form['steps'] : array();
		if ( self::needs_injected_payment( $form ) ) {
			$steps[] = self::payment_step( $form );
		}
		return $steps;
	}

	public static function needs_injected_payment( array $form ) {
		if ( empty( $form['payment']['enabled'] ) ) {
			return false;
		}
		foreach ( self::all_fields( $form ) as $field ) {
			if ( in_array( $field['type'], array( 'payment_method', 'amount' ), true ) || 'amount' === $field['role'] ) {
				return false;
			}
		}
		return true;
	}

	public static function all_fields( array $form ) {
		$out = array();
		$steps = isset( $form['steps'] ) && is_array( $form['steps'] ) ? $form['steps'] : array();
		foreach ( $steps as $step ) {
			if ( empty( $step['fields'] ) || ! is_array( $step['fields'] ) ) {
				continue;
			}
			foreach ( $step['fields'] as $field ) {
				$out[] = $field;
			}
		}
		return $out;
	}

	/**
	 * @return array{ok:bool,message:string,payload?:array}
	 */
	public static function validate( array $form, array $in ) {
		if ( 'publish' !== $form['status'] ) {
			return array( 'ok' => false, 'message' => 'این فرم در حال حاضر پذیرای ثبت نیست.' );
		}

		$answers = array();
		$roles   = array(
			'name'        => '',
			'phone'      => '',
			'email'      => '',
			'national_id'=> '',
			'amount'     => 0,
			'address'    => '',
		);

		$fields = self::all_fields( $form );
		if ( self::needs_injected_payment( $form ) ) {
			$fields = array_merge( $fields, self::payment_step( $form )['fields'] );
		}

		foreach ( $fields as $field ) {
			if ( 'note' === $field['type'] ) {
				continue;
			}
			$name = $field['name'];
			$raw  = isset( $in[ $name ] ) ? $in[ $name ] : ( in_array( $field['type'], array( 'checkbox' ), true ) ? array() : '' );
			$got  = self::read_field( $field, $raw, $form );
			if ( empty( $got['ok'] ) ) {
				return $got;
			}
			$val = $got['value'];
			if ( $field['required'] && self::is_empty_value( $val ) ) {
				return array( 'ok' => false, 'message' => 'لطفاً «' . $field['label'] . '» را کامل کنید.' );
			}
			$display = $got['display'];
			$answers[] = array(
				'name'    => $name,
				'label'   => $field['label'],
				'type'    => $field['type'],
				'value'   => $val,
				'display' => $display,
			);
			if ( $field['role'] && array_key_exists( $field['role'], $roles ) ) {
				if ( 'amount' === $field['role'] ) {
					$roles['amount'] = NCK_Hall::parse_amount( is_array( $val ) ? '' : $val );
				} elseif ( 'phone' === $field['role'] ) {
					$roles['phone'] = is_string( $val ) ? $val : '';
				} else {
					$roles[ $field['role'] ] = is_array( $val ) ? implode( '، ', $val ) : (string) $val;
				}
			}
			if ( 'amount' === $field['type'] && $roles['amount'] < 1 ) {
				$roles['amount'] = NCK_Hall::parse_amount( is_array( $val ) ? '' : $val );
			}
			if ( 'tel' === $field['type'] && $roles['phone'] === '' && is_string( $val ) ) {
				$roles['phone'] = $val;
			}
		}

		$name = $roles['name'];
		if ( $name === '' ) {
			$name = self::text( isset( $in['name'] ) ? $in['name'] : '', 120 );
		}
		if ( $name === '' || self::len( $name ) < 3 ) {
			return array( 'ok' => false, 'message' => 'نام و نام خانوادگی را کامل وارد کنید.' );
		}

		$phone = $roles['phone'] !== '' ? $roles['phone'] : NCK_Phone::normalize( isset( $in['phone'] ) ? $in['phone'] : '' );
		if ( ! $phone ) {
			return array( 'ok' => false, 'message' => 'شماره موبایل معتبر لازم است تا فرم ثبت شود.' );
		}

		if ( $roles['national_id'] !== '' ) {
			$nid = NCK_Hall::normalize_nid( $roles['national_id'] );
			if ( ! $nid ) {
				return array( 'ok' => false, 'message' => 'کد ملی معتبر نیست.' );
			}
			$roles['national_id'] = $nid;
		}

		$payment = 'site';
		$pay_ref = class_exists( 'NCK_Pay' ) ? NCK_Pay::make_ref() : self::text( isset( $in['pay_ref'] ) ? $in['pay_ref'] : '', 80 );
		$pay_date = '';
		if ( isset( $in['pay_date'] ) && trim( (string) $in['pay_date'] ) !== '' ) {
			$pay_date = NCK_Learner::format_date( $in['pay_date'] );
			if ( $pay_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ پرداخت را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}
		$amount = (int) $roles['amount'];
		if ( ! empty( $form['payment']['amount'] ) ) {
			$amount = (int) $form['payment']['amount'];
		}
		if ( ! empty( $form['payment']['enabled'] ) ) {
			if ( $amount < 1 ) {
				$amount = NCK_Hall::parse_amount( isset( $in['pay_amount'] ) ? $in['pay_amount'] : ( isset( $in['amount'] ) ? $in['amount'] : 0 ) );
			}
			if ( $amount < 1 ) {
				return array( 'ok' => false, 'message' => 'مبلغ پرداخت را وارد کنید تا در حسابدار و ووکامرس ثبت شود.' );
			}
		} else {
			$payment = '';
		}

		if ( empty( $in['agree'] ) ) {
			return array( 'ok' => false, 'message' => 'برای ثبت فرم باید صحت اطلاعات را بپذیرید.' );
		}

		$item = isset( $form['payment']['item_name'] ) ? $form['payment']['item_name'] : '';
		if ( $item === '' ) {
			$item = $form['title'];
		}

		return array(
			'ok'      => true,
			'message' => '',
			'payload' => array(
				'kind'         => 'form',
				'form_id'      => $form['id'],
				'form_slug'    => $form['slug'],
				'form_title'   => $form['title'],
				'product_id'   => isset( $form['product_id'] ) ? (int) $form['product_id'] : 0,
				'name'         => $name,
				'phone'        => $phone,
				'email'        => $roles['email'],
				'national_id'  => $roles['national_id'],
				'address'      => $roles['address'],
				'honorific'    => 'mr',
				'payment'      => $payment,
				'pay_amount'   => $amount,
				'pay_date'     => $pay_date,
				'pay_ref'      => $pay_ref,
				'item_name'    => $item,
				'answers'      => $answers,
			),
		);
	}

	public static function parse_options( $raw ) {
		$out  = array();
		$raw  = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		$i    = 1;
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}
			if ( false !== strpos( $line, '|' ) ) {
				$parts = explode( '|', $line, 2 );
				$key   = self::key( $parts[0] );
				$label = self::text( isset( $parts[1] ) ? $parts[1] : $parts[0], 120 );
			} else {
				$label = self::text( $line, 120 );
				$key   = self::key( $line );
				if ( $key === '' ) {
					$key = 'opt' . $i;
				}
			}
			if ( $key === '' || $label === '' ) {
				continue;
			}
			$out[ $key ] = $label;
			$i++;
			if ( count( $out ) >= 40 ) {
				break;
			}
		}
		return $out;
	}

	public static function payment_options_for( array $form ) {
		unset( $form );
		return array(
			'site' => class_exists( 'NCK_Learner' ) ? NCK_Learner::payment_label( 'site' ) : 'سایت',
		);
	}

	public static function slugify( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		$raw = str_replace( array( ' ', '_', 'ـ' ), '-', $raw );
		$raw = preg_replace( '/[^a-z0-9\-]/', '', $raw );
		$raw = preg_replace( '/-+/', '-', $raw );
		$raw = trim( $raw, '-' );
		if ( function_exists( 'mb_substr' ) ) {
			$raw = mb_substr( $raw, 0, 40 );
		} else {
			$raw = substr( $raw, 0, 40 );
		}
		return $raw;
	}

	public static function id_key( $raw ) {
		$raw = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $raw ) );
		return substr( (string) $raw, 0, 24 );
	}

	private static function sanitize_steps( array $steps ) {
		$out   = array();
		$used  = array();
		$count = 0;
		foreach ( $steps as $step ) {
			if ( $count >= 12 ) {
				break;
			}
			if ( ! is_array( $step ) ) {
				continue;
			}
			$label  = self::text( isset( $step['label'] ) ? $step['label'] : '', 80 );
			$fields = isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array();
			$clean  = array();
			foreach ( $fields as $field ) {
				if ( count( $clean ) >= 40 || ! is_array( $field ) ) {
					continue;
				}
				$row = self::sanitize_field( $field, $used );
				if ( ! $row ) {
					continue;
				}
				$used[ $row['name'] ] = true;
				$clean[] = $row;
			}
			if ( $label === '' && ! $clean ) {
				continue;
			}
			if ( $label === '' ) {
				$label = 'مرحله ' . ( count( $out ) + 1 );
			}
			$out[] = array(
				'label'  => $label,
				'fields' => $clean,
			);
			$count++;
		}
		return $out;
	}

	private static function sanitize_field( array $field, array $used ) {
		$types = self::field_types();
		$type = isset( $field['type'] ) ? self::key( $field['type'] ) : 'text';
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'text';
		}
		$label = self::text( isset( $field['label'] ) ? $field['label'] : '', 120 );
		if ( $label === '' && 'note' !== $type ) {
			$label = $types[ $type ];
		}
		$name = self::key( isset( $field['name'] ) ? $field['name'] : '' );
		if ( $name === '' || isset( $used[ $name ] ) || in_array( $name, self::reserved_names(), true ) ) {
			$base = $type !== 'note' ? $type : 'note';
			$name = $base;
			$i    = 2;
			while ( isset( $used[ $name ] ) || in_array( $name, self::reserved_names(), true ) ) {
				$name = $base . $i;
				$i++;
			}
		}
		$role = isset( $field['role'] ) ? self::key( $field['role'] ) : '';
		if ( ! array_key_exists( $role, self::roles() ) ) {
			$role = '';
		}
		if ( 'payment_method' === $type ) {
			$name = 'payment';
			$role = '';
		}
		if ( 'amount' === $type && $role === '' ) {
			$role = 'amount';
		}
		if ( 'tel' === $type && $role === '' ) {
			$role = 'phone';
		}
		$options = isset( $field['options'] ) ? (string) $field['options'] : '';
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			$options = sanitize_textarea_field( $options );
		} else {
			$options = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $options ) );
		}
		if ( in_array( $type, array( 'radio', 'checkbox', 'select' ), true ) && self::parse_options( $options ) === array() ) {
			$options = "opt1|گزینه ۱\nopt2|گزینه ۲";
		}
		return array(
			'type'     => $type,
			'name'     => $name,
			'label'    => $label,
			'required' => ( 'note' === $type ) ? 0 : ( empty( $field['required'] ) ? 0 : 1 ),
			'role'     => $role,
			'options'  => $options,
		);
	}

	private static function payment_step( array $form ) {
		$fields = array(
			array(
				'type'     => 'payment_method',
				'name'     => 'payment',
				'label'    => 'روش پرداخت',
				'required' => 1,
				'role'     => '',
				'options'  => '',
			),
		);
		if ( empty( $form['payment']['amount'] ) ) {
			$fields[] = array(
				'type'     => 'amount',
				'name'     => 'pay_amount',
				'label'    => 'مبلغ (تومان)',
				'required' => 1,
				'role'     => 'amount',
				'options'  => '',
			);
		}
		$fields[] = array(
			'type'     => 'date',
			'name'     => 'pay_date',
			'label'    => 'تاریخ پرداخت',
			'required' => 0,
			'role'     => '',
			'options'  => '',
		);
		return array(
			'label'  => 'پرداخت',
			'fields' => $fields,
		);
	}

	private static function read_field( array $field, $raw, array $form ) {
		$type = $field['type'];
		if ( 'payment_method' === $type ) {
			unset( $raw );
			return array(
				'ok'      => true,
				'value'   => 'site',
				'display' => class_exists( 'NCK_Learner' ) ? NCK_Learner::payment_label( 'site' ) : 'سایت',
			);
		}
		if ( 'checkbox' === $type ) {
			$opts = self::parse_options( $field['options'] );
			$list = NCK_Learner::pick_list( $raw, $opts );
			$labels = array();
			foreach ( $list as $k ) {
				$labels[] = $opts[ $k ];
			}
			return array(
				'ok'      => true,
				'value'   => $list,
				'display' => $labels ? implode( '، ', $labels ) : '',
			);
		}
		if ( 'radio' === $type || 'select' === $type ) {
			$opts = self::parse_options( $field['options'] );
			$val  = NCK_Learner::pick_one( is_array( $raw ) ? '' : $raw, $opts );
			return array(
				'ok'      => true,
				'value'   => $val,
				'display' => ( $val && isset( $opts[ $val ] ) ) ? $opts[ $val ] : '',
			);
		}
		$str = is_array( $raw ) ? '' : (string) $raw;
		if ( 'tel' === $type ) {
			if ( trim( $str ) === '' ) {
				return array( 'ok' => true, 'value' => '', 'display' => '' );
			}
			$phone = NCK_Phone::normalize( $str );
			if ( ! $phone ) {
				return array( 'ok' => false, 'message' => 'شماره موبایل «' . $field['label'] . '» معتبر نیست.' );
			}
			return array( 'ok' => true, 'value' => $phone, 'display' => $phone );
		}
		if ( 'email' === $type ) {
			$str = self::text( $str, 160 );
			if ( $str !== '' && function_exists( 'is_email' ) && ! is_email( $str ) ) {
				return array( 'ok' => false, 'message' => 'ایمیل «' . $field['label'] . '» معتبر نیست.' );
			}
			if ( $str !== '' && ! function_exists( 'is_email' ) && ! preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $str ) ) {
				return array( 'ok' => false, 'message' => 'ایمیل «' . $field['label'] . '» معتبر نیست.' );
			}
			return array( 'ok' => true, 'value' => $str, 'display' => $str );
		}
		if ( 'date' === $type ) {
			if ( trim( $str ) === '' ) {
				return array( 'ok' => true, 'value' => '', 'display' => '' );
			}
			$d = NCK_Learner::format_date( $str );
			if ( $d === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ «' . $field['label'] . '» را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
			return array( 'ok' => true, 'value' => $d, 'display' => $d );
		}
		if ( 'amount' === $type || 'number' === $type ) {
			$n = NCK_Hall::parse_amount( $str );
			return array(
				'ok'      => true,
				'value'   => $n,
				'display' => 'amount' === $type ? NCK_Hall::format_money( $n ) : (string) $n,
			);
		}
		if ( 'textarea' === $type ) {
			$str = self::textarea( $str );
			return array( 'ok' => true, 'value' => $str, 'display' => $str );
		}
		$str = self::text( $str, 300 );
		return array( 'ok' => true, 'value' => $str, 'display' => $str );
	}

	private static function is_empty_value( $val ) {
		if ( is_array( $val ) ) {
			return count( $val ) === 0;
		}
		if ( is_int( $val ) || is_float( $val ) ) {
			return (int) $val < 1;
		}
		return trim( (string) $val ) === '';
	}

	private static function clean_methods( $raw ) {
		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}
		$all = NCK_Learner::payment_options();
		$out = array();
		foreach ( $raw as $v ) {
			$k = self::key( $v );
			if ( isset( $all[ $k ] ) ) {
				$out[] = $k;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function slug_taken( $slug, $except_id ) {
		if ( in_array( $slug, self::reserved_slugs(), true ) ) {
			return true;
		}
		foreach ( self::all() as $form ) {
			if ( $form['slug'] === $slug && $form['id'] !== $except_id ) {
				return true;
			}
		}
		return false;
	}

	private static function new_id() {
		try {
			return 'f' . bin2hex( random_bytes( 6 ) );
		} catch ( Exception $e ) {
			return 'f' . substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 12 );
		}
	}

	public static function render_field( array $field, array $form ) {
		$type = $field['type'];
		$name = $field['name'];
		$req  = ! empty( $field['required'] ) ? ' required' : '';
		$id   = 'nck-f-' . preg_replace( '/[^a-z0-9_\-]/', '', $name );

		if ( 'note' === $type ) {
			echo '<p class="nck-note">' . esc_html( $field['label'] ) . '</p>';
			return;
		}

		if ( 'payment_method' === $type ) {
			echo '<div class="nck-pay-site">';
			echo '<input type="hidden" name="payment" value="site" />';
			echo '<p class="nck-pay-site-kicker">پرداخت فقط از درگاه بانک انجام می‌شود.</p>';
			echo '</div>';
			return;
		}

		if ( 'checkbox' === $type ) {
			$opts = self::parse_options( $field['options'] );
			$need = ! empty( $field['required'] ) ? ' data-nck-need-one="' . esc_attr( $name ) . '"' : '';
			echo '<fieldset class="nck-fieldset"' . $need . '><legend>' . esc_html( $field['label'] ) . '</legend><div class="nck-chips">';
			foreach ( $opts as $k => $label ) {
				echo '<label class="nck-chip"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $k ) . '" /> ' . esc_html( $label ) . '</label>';
			}
			echo '</div></fieldset>';
			return;
		}

		if ( 'radio' === $type ) {
			$opts = self::parse_options( $field['options'] );
			echo '<fieldset class="nck-fieldset"><legend>' . esc_html( $field['label'] ) . '</legend><div class="nck-chips">';
			$i = 0;
			foreach ( $opts as $k => $label ) {
				$r = ( $req && 0 === $i ) ? ' required' : '';
				echo '<label class="nck-chip"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . $r . ' /> ' . esc_html( $label ) . '</label>';
				$i++;
			}
			echo '</div></fieldset>';
			return;
		}

		echo '<div class="nck-field">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3"' . $req . '></textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . '>';
			echo '<option value="">انتخاب کنید</option>';
			foreach ( self::parse_options( $field['options'] ) as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		} else {
			$input = 'text';
			$dir   = '';
			$ph    = '';
			if ( 'tel' === $type ) {
				$input = 'tel';
				$dir   = ' dir="ltr"';
				$ph    = ' placeholder="09123456789"';
			} elseif ( 'email' === $type ) {
				$input = 'email';
				$dir   = ' dir="ltr"';
			} elseif ( 'number' === $type || 'amount' === $type ) {
				$input = 'text';
				$dir   = ' dir="ltr"';
				$ph    = 'amount' === $type ? ' placeholder="مبلغ به تومان"' : '';
			} elseif ( 'date' === $type ) {
				$dir = ' dir="ltr"';
				$ph  = ' placeholder="1404/06/20"';
			}
			echo '<input id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $input ) . '"' . $dir . $ph . $req . ' />';
		}
		echo '</div>';
	}

	private static function store_get() {
		if ( function_exists( 'get_option' ) ) {
			$rows = get_option( self::OPTION, array() );
			return is_array( $rows ) ? $rows : array();
		}
		return self::$memory;
	}

	private static function store_set( array $rows ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, array_values( $rows ), false );
			return;
		}
		self::$memory = array_values( $rows );
	}

	private static function key( $v ) {
		$v = strtolower( trim( (string) $v ) );
		$v = preg_replace( '/[^a-z0-9_\-]/', '', $v );
		return $v;
	}

	private static function text( $v, $max = 190 ) {
		$v = trim( (string) $v );
		$v = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v );
		if ( function_exists( 'sanitize_text_field' ) ) {
			$v = sanitize_text_field( $v );
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $v, 0, $max );
		}
		return substr( $v, 0, $max );
	}

	private static function textarea( $v ) {
		$v = (string) $v;
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $v );
		}
		return trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v ) );
	}

	private static function len( $v ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $v ) : strlen( $v );
	}
}
