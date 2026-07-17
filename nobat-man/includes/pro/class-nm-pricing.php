<?php
defined( 'ABSPATH' ) || exit;

class NM_Pricing {

	public static function price_for( $specialist_id, $weekday, $jalali_date, $start_time ) {
		$base = (int) NM_Settings::get( 'default_price', 0 );
		if ( $specialist_id ) {
			$sp = NM_Specialist::get( $specialist_id );
			if ( $sp && (int) $sp->price > 0 ) {
				$base = (int) $sp->price;
			}
		}

		if ( ! NM_Pro::is_active() ) {
			return $base;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nm_pricing_rules';
		$rules = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE is_active = 1 AND specialist_id IN (0, %d) ORDER BY specialist_id DESC, id DESC",
			$specialist_id
		) );

		$start_min = NM_Availability::time_to_min( $start_time );
		foreach ( $rules as $rule ) {
			if ( $rule->jalali_date && $rule->jalali_date !== $jalali_date ) {
				continue;
			}
			if ( null !== $rule->weekday && '' !== $rule->weekday && (int) $rule->weekday !== (int) $weekday ) {
				continue;
			}
			if ( $rule->start_time && $rule->end_time ) {
				$a = NM_Availability::time_to_min( $rule->start_time );
				$b = NM_Availability::time_to_min( $rule->end_time );
				if ( $start_min < $a || $start_min >= $b ) {
					continue;
				}
			}
			return (int) $rule->price;
		}

		return $base;
	}

	public static function save_rule( array $data, $id = 0 ) {
		if ( ! NM_Pro::is_active() ) {
			return NM_Pro::require_pro();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'nm_pricing_rules';
		$row = array(
			'specialist_id' => (int) ( $data['specialist_id'] ?? 0 ),
			'weekday'       => isset( $data['weekday'] ) && '' !== $data['weekday'] ? (int) $data['weekday'] : null,
			'jalali_date'   => sanitize_text_field( $data['jalali_date'] ?? '' ) ?: null,
			'start_time'    => sanitize_text_field( $data['start_time'] ?? '' ) ?: null,
			'end_time'      => sanitize_text_field( $data['end_time'] ?? '' ) ?: null,
			'price'         => (int) ( $data['price'] ?? 0 ),
			'label'         => sanitize_text_field( $data['label'] ?? '' ),
			'is_active'     => 1,
		);
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}
}
