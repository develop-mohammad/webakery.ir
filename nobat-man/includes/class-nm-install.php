<?php
defined( 'ABSPATH' ) || exit;

class NM_Install {

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}nm_specialists (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			name VARCHAR(190) NOT NULL,
			slug VARCHAR(190) NOT NULL,
			skills TEXT NULL,
			bio TEXT NULL,
			avatar_id BIGINT UNSIGNED NULL,
			price DECIMAL(14,0) NOT NULL DEFAULT 0,
			duration INT UNSIGNED NOT NULL DEFAULT 60,
			buffer_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			business_id BIGINT UNSIGNED NULL,
			google_calendar_id VARCHAR(255) NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY slug (slug),
			KEY business_id (business_id),
			KEY is_active (is_active)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_businesses (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'consulting',
			owner_user_id BIGINT UNSIGNED NULL,
			settings LONGTEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_code VARCHAR(32) NOT NULL,
			specialist_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			business_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(190) NOT NULL,
			customer_email VARCHAR(190) NULL,
			customer_phone VARCHAR(40) NOT NULL,
			customer_city VARCHAR(120) NULL,
			customer_gender VARCHAR(20) NULL,
			jalali_date VARCHAR(10) NOT NULL,
			g_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			duration INT UNSIGNED NOT NULL DEFAULT 60,
			price DECIMAL(14,0) NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			order_id BIGINT UNSIGNED NULL,
			problem_category VARCHAR(120) NULL,
			description LONGTEXT NULL,
			answers LONGTEXT NULL,
			attachments LONGTEXT NULL,
			invoice_no VARCHAR(50) NULL,
			google_event_id VARCHAR(255) NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY booking_code (booking_code),
			KEY specialist_date (specialist_id, g_date),
			KEY status (status),
			KEY order_id (order_id),
			KEY g_date (g_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_schedules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			specialist_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			weekday TINYINT UNSIGNED NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY specialist_weekday (specialist_id, weekday)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_exceptions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			specialist_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			jalali_date VARCHAR(10) NOT NULL,
			g_date DATE NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'closed',
			start_time TIME NULL,
			end_time TIME NULL,
			note VARCHAR(255) NULL,
			PRIMARY KEY (id),
			KEY specialist_gdate (specialist_id, g_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_questions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category VARCHAR(120) NOT NULL,
			question TEXT NOT NULL,
			type VARCHAR(30) NOT NULL DEFAULT 'text',
			options LONGTEXT NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY category (category)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_tickets (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NULL,
			specialist_id BIGINT UNSIGNED NULL,
			customer_name VARCHAR(190) NOT NULL,
			customer_email VARCHAR(190) NULL,
			customer_phone VARCHAR(40) NULL,
			subject VARCHAR(255) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'open',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_ticket_replies (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id BIGINT UNSIGNED NOT NULL,
			sender_type VARCHAR(20) NOT NULL,
			sender_id BIGINT UNSIGNED NULL,
			message LONGTEXT NOT NULL,
			attachments LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY ticket_id (ticket_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_pricing_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			specialist_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			weekday TINYINT NULL,
			jalali_date VARCHAR(10) NULL,
			start_time TIME NULL,
			end_time TIME NULL,
			price DECIMAL(14,0) NOT NULL,
			label VARCHAR(120) NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY specialist_id (specialist_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}nm_subscriptions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_email VARCHAR(190) NOT NULL,
			customer_phone VARCHAR(40) NULL,
			plan_key VARCHAR(60) NOT NULL,
			credits INT NOT NULL DEFAULT 0,
			starts_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			order_id BIGINT UNSIGNED NULL,
			meta LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY customer_email (customer_email),
			KEY status (status)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
	}

	public static function seed_defaults() {
		if ( ! get_option( 'nm_settings' ) ) {
			update_option( 'nm_settings', NM_Settings::defaults() );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nm_questions';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		$now = current_time( 'mysql' );
		$seed = array(
			array( 'اضطراب و استرس', 'چه مدتی است این مشکل را تجربه می‌کنید؟', 'select', wp_json_encode( array( 'کمتر از یک ماه', '۱ تا ۳ ماه', 'بیش از ۳ ماه' ) ), 1, 10 ),
			array( 'اضطراب و استرس', 'شدت مشکل را از ۱ تا ۱۰ چگونه ارزیابی می‌کنید؟', 'select', wp_json_encode( range( 1, 10 ) ), 1, 20 ),
			array( 'روابط و خانواده', 'موضوع اصلی مشاوره شما چیست؟', 'textarea', '', 1, 10 ),
			array( 'رشد فردی', 'هدف شما از این جلسه چیست؟', 'textarea', '', 1, 10 ),
			array( 'سایر', 'توضیح کوتاه مشکل خود را بنویسید', 'textarea', '', 1, 10 ),
		);

		foreach ( $seed as $row ) {
			$wpdb->insert(
				$table,
				array(
					'category'    => $row[0],
					'question'    => $row[1],
					'type'        => $row[2],
					'options'     => $row[3],
					'is_required' => $row[4],
					'sort_order'  => $row[5],
					'is_active'   => 1,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
			);
		}

		// برنامه پیش‌فرض شنبه تا چهارشنبه ۹ تا ۱۷، پنج‌شنبه ۹ تا ۱۳
		$sched = $wpdb->prefix . 'nm_schedules';
		$sc = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sched}" );
		if ( 0 === $sc ) {
			for ( $d = 0; $d <= 4; $d++ ) {
				$wpdb->insert( $sched, array(
					'specialist_id' => 0,
					'weekday'       => $d,
					'start_time'    => '09:00:00',
					'end_time'      => ( 4 === $d ? '13:00:00' : '17:00:00' ),
					'is_active'     => 1,
				) );
			}
		}
	}
}
