<?php
defined( 'ABSPATH' ) || exit;

/**
 * ثبت سفارش ووکامرس برای فرم‌هایی که به پرداخت می‌رسند.
 * حسابدار همان سفارش‌های ووکامرس را نشان می‌دهد؛ حسابدار بازنویسی نمی‌شود.
 */
class NCK_Pay {

	public static function method_labels() {
		return array(
			'site'   => 'پرداخت سایت نهال',
			'card'   => 'کارت به کارت',
			'onsite' => 'پرداخت در محل',
		);
	}

	public static function method_label( $method ) {
		$map = self::method_labels();
		$k   = (string) $method;
		return isset( $map[ $k ] ) ? $map[ $k ] : 'پرداخت نهال';
	}

	public static function status_for_method( $method ) {
		if ( 'onsite' === $method ) {
			return 'completed';
		}
		if ( 'card' === $method ) {
			return 'on-hold';
		}
		return 'processing';
	}

	public static function sync_enabled() {
		if ( ! class_exists( 'NCK_Settings' ) ) {
			return true;
		}
		return 1 === (int) NCK_Settings::get( 'wc_sync', 1 );
	}

	public static function wc_ready() {
		return function_exists( 'wc_create_order' ) && class_exists( 'WooCommerce' );
	}

	public static function hesabdar_ready() {
		return class_exists( 'WAP_Order_Service' ) || class_exists( 'WooCommerce_Accounting_Pro' );
	}

	public static function should_record( $amount ) {
		return (int) $amount > 0;
	}

	/**
	 * خواندن روش و مبلغ پرداخت از فرم فرانت.
	 *
	 * @return array{ok:bool,message?:string,payload?:array}
	 */
	public static function parse_front_payment( array $in, $fallback_amount = 0 ) {
		$opts    = class_exists( 'NCK_Learner' ) ? NCK_Learner::payment_options() : array(
			'site'   => 'سایت',
			'card'   => 'کارت به کارت',
			'onsite' => 'در محل کارت کشیده شد',
		);
		$payment = isset( $in['payment'] ) ? (string) $in['payment'] : '';
		if ( ! isset( $opts[ $payment ] ) ) {
			return array( 'ok' => false, 'message' => 'روش پرداخت را انتخاب کنید.' );
		}

		$amount = 0;
		if ( isset( $in['pay_amount'] ) && trim( (string) $in['pay_amount'] ) !== '' ) {
			$amount = NCK_Hall::parse_amount( $in['pay_amount'] );
		}
		if ( $amount < 1 ) {
			$amount = max( 0, (int) $fallback_amount );
		}

		$pay_date = '';
		if ( isset( $in['pay_date'] ) && trim( (string) $in['pay_date'] ) !== '' ) {
			$pay_date = class_exists( 'NCK_Learner' ) ? NCK_Learner::format_date( $in['pay_date'] ) : trim( (string) $in['pay_date'] );
			if ( $pay_date === '' ) {
				return array( 'ok' => false, 'message' => 'تاریخ پرداخت را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' );
			}
		}

		$ref = isset( $in['pay_ref'] ) ? trim( (string) $in['pay_ref'] ) : '';
		if ( function_exists( 'sanitize_text_field' ) ) {
			$ref = sanitize_text_field( $ref );
		}

		return array(
			'ok'      => true,
			'payload' => array(
				'payment'    => $payment,
				'pay_amount' => $amount,
				'pay_date'   => $pay_date,
				'pay_ref'    => $ref,
			),
		);
	}

	public static function maybe_record( $contract ) {
		if ( ! is_array( $contract ) || empty( $contract['id'] ) ) {
			return;
		}
		try {
			self::record_contract( $contract );
		} catch ( Exception $e ) {
			return;
		}
	}

	public static function split_name( $full ) {
		$full  = trim( (string) $full );
		$parts = preg_split( '/\s+/u', $full, 2 );
		if ( ! is_array( $parts ) || ! isset( $parts[0] ) || $parts[0] === '' ) {
			return array( 'first' => $full, 'last' => '' );
		}
		return array(
			'first' => $parts[0],
			'last'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	public static function draft( array $args ) {
		$amount  = isset( $args['amount'] ) ? (int) $args['amount'] : 0;
		$method  = isset( $args['payment'] ) ? (string) $args['payment'] : '';
		$name    = isset( $args['name'] ) ? (string) $args['name'] : '';
		$item    = isset( $args['item_name'] ) ? (string) $args['item_name'] : 'فرم نهال';
		$split   = self::split_name( $name );
		return array(
			'item_name'      => $item,
			'amount'        => $amount,
			'payment'       => $method,
			'payment_title' => self::method_label( $method ),
			'status'         => self::status_for_method( $method ),
			'created_via'   => 'nahal-cowork',
			'first_name'    => $split['first'],
			'last_name'     => $split['last'],
			'phone'         => isset( $args['phone'] ) ? (string) $args['phone'] : '',
			'email'         => isset( $args['email'] ) ? (string) $args['email'] : '',
			'kind'          => isset( $args['kind'] ) ? (string) $args['kind'] : '',
		);
	}

	/**
	 * @param array $contract ردیف قرارداد ذخیره‌شده
	 * @return array{ok:bool,skipped?:bool,reason?:string,order_id?:int,message?:string}
	 */
	public static function record_contract( array $contract ) {
		if ( ! self::sync_enabled() ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'disabled' );
		}

		$kind    = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::kind_of( $contract ) : ( isset( $contract['kind'] ) ? $contract['kind'] : '' );
		$payload = class_exists( 'NCK_Contracts' ) ? NCK_Contracts::payload( $contract ) : array();
		if ( ! empty( $payload['wc_order_id'] ) ) {
			return array( 'ok' => true, 'skipped' => true, 'reason' => 'exists', 'order_id' => (int) $payload['wc_order_id'] );
		}

		$built = self::from_contract( $kind, $contract, $payload );
		if ( empty( $built['ok'] ) ) {
			return $built;
		}

		$created = self::create_order( $built['order'], $contract );
		if ( ! empty( $created['ok'] ) && ! empty( $created['order_id'] ) && class_exists( 'NCK_Contracts' ) ) {
			NCK_Contracts::merge_payload(
				(int) $contract['id'],
				array(
					'wc_order_id' => (int) $created['order_id'],
				)
			);
		}
		return $created;
	}

	/**
	 * @return array{ok:bool,skipped?:bool,reason?:string,order?:array}
	 */
	public static function from_contract( $kind, array $contract, array $payload ) {
		$kind = (string) $kind;
		$name = isset( $contract['full_name'] ) ? $contract['full_name'] : '';
		$phone = isset( $contract['phone'] ) ? $contract['phone'] : '';

		if ( 'hall' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( $amount < 1 ) {
				$amount = isset( $payload['amount'] ) ? (int) $payload['amount'] : 0;
			}
			$hall = isset( $payload['hall_name'] ) ? $payload['hall_name'] : 'سالن';
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$payment = isset( $payload['payment'] ) && $payload['payment'] !== '' ? $payload['payment'] : 'onsite';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'hall',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => 'اجاره سالن — ' . $hall,
					)
				),
			);
		}

		if ( 'learner' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( $amount < 1 && class_exists( 'NCK_Settings' ) ) {
				$amount = (int) NCK_Settings::get( 'learner_fee', 0 );
			}
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$payment = isset( $payload['payment'] ) ? $payload['payment'] : 'site';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'learner',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => 'پذیرش فراگیر نهال',
					)
				),
			);
		}

		if ( 'form' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$title = isset( $payload['item_name'] ) && $payload['item_name'] !== '' ? $payload['item_name'] : ( isset( $payload['form_title'] ) ? $payload['form_title'] : 'فرم نهال' );
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'form',
						'name'      => $name,
						'phone'     => $phone,
						'email'     => isset( $payload['email'] ) ? $payload['email'] : '',
						'amount'    => $amount,
						'payment'   => isset( $payload['payment'] ) ? $payload['payment'] : 'site',
						'item_name' => $title,
					)
				),
			);
		}

		if ( 'cowork' === $kind ) {
			$amount = isset( $payload['pay_amount'] ) ? (int) $payload['pay_amount'] : 0;
			if ( $amount < 1 && class_exists( 'NCK_Settings' ) ) {
				$amount = (int) NCK_Settings::get( 'cowork_fee', 0 );
			}
			if ( ! self::should_record( $amount ) ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
			}
			$plan  = isset( $contract['plan'] ) ? $contract['plan'] : '';
			$label = 'اشتراک فضای کار نهال';
			if ( class_exists( 'NCK_Shifts' ) ) {
				$labels = NCK_Shifts::plan_labels();
				if ( isset( $labels[ $plan ] ) ) {
					$label .= ' — ' . $labels[ $plan ];
				}
			}
			$payment = isset( $payload['payment'] ) && $payload['payment'] !== '' ? $payload['payment'] : 'onsite';
			return array(
				'ok'    => true,
				'order' => self::draft(
					array(
						'kind'      => 'cowork',
						'name'      => $name,
						'phone'     => $phone,
						'amount'    => $amount,
						'payment'   => $payment,
						'item_name' => $label,
					)
				),
			);
		}

		return array( 'ok' => false, 'skipped' => true, 'reason' => 'kind' );
	}

	/**
	 * @return array{ok:bool,skipped?:bool,reason?:string,order_id?:int,message?:string}
	 */
	public static function create_order( array $draft, array $contract = array() ) {
		if ( ! self::should_record( isset( $draft['amount'] ) ? $draft['amount'] : 0 ) ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'amount' );
		}
		if ( ! self::wc_ready() ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'woocommerce' );
		}

		try {
			$order = wc_create_order( array( 'status' => 'pending' ) );
			if ( is_wp_error( $order ) || ! $order ) {
				return array( 'ok' => false, 'skipped' => true, 'reason' => 'create', 'message' => 'ساخت سفارش ووکامرس ناموفق بود.' );
			}

			$item = new WC_Order_Item_Fee();
			$item->set_name( $draft['item_name'] );
			$item->set_total( (float) $draft['amount'] );
			$order->add_item( $item );

			$order->set_billing_first_name( $draft['first_name'] );
			$order->set_billing_last_name( $draft['last_name'] );
			if ( ! empty( $draft['email'] ) ) {
				$order->set_billing_email( $draft['email'] );
			}
			$order->set_billing_phone( $draft['phone'] );
			$order->set_created_via( 'nahal-cowork' );
			$order->set_payment_method( 'nahal-' . ( $draft['payment'] ? $draft['payment'] : 'pay' ) );
			$order->set_payment_method_title( $draft['payment_title'] );

			$order->update_meta_data( '_nck_kind', $draft['kind'] );
			$order->update_meta_data( '_nck_form', $draft['kind'] );
			if ( ! empty( $contract['id'] ) ) {
				$order->update_meta_data( '_nck_contract_id', (int) $contract['id'] );
			}
			if ( ! empty( $contract['print_token'] ) ) {
				$order->update_meta_data( '_nck_print_token', (string) $contract['print_token'] );
			}
			$order->add_order_note( 'ثبت‌شده از افزونه قرارداد نهال (' . $draft['item_name'] . ').' );
			$order->calculate_totals();
			$order->save();

			$status = $draft['status'];
			$allowed = array( 'pending', 'processing', 'on-hold', 'completed' );
			if ( ! in_array( $status, $allowed, true ) ) {
				$status = 'processing';
			}
			$order->update_status( $status, 'وضعیت اولیه بر اساس روش پرداخت فرم نهال.', true );

			return array(
				'ok'       => true,
				'order_id' => (int) $order->get_id(),
			);
		} catch ( Exception $e ) {
			return array( 'ok' => false, 'skipped' => true, 'reason' => 'exception', 'message' => $e->getMessage() );
		}
	}
}
