<?php
defined( 'ABSPATH' ) || exit;

class DID_Admin {

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_posts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DID_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function menu() {
		add_menu_page(
			'دیدبانی',
			'دیدبانی',
			'manage_options',
			'didbani',
			array( __CLASS__, 'render' ),
			'dashicons-visibility',
			56
		);
	}

	public static function tabs() {
		return array(
			'dashboard' => 'داشبورد',
			'project'   => 'پروژه',
			'crawl'     => 'کرول',
			'ranks'     => 'رتبه‌ها',
			'settings'  => 'تنظیمات',
			'license'   => 'لایسنس',
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'didbani' ) ) {
			return;
		}
		wp_enqueue_style( 'did-admin', DID_URL . 'assets/css/admin.css', array(), DID_VERSION );
		wp_enqueue_script( 'did-admin', DID_URL . 'assets/js/admin.js', array(), DID_VERSION, true );
		$pid = (int) self::current_project_id();
		wp_localize_script(
			'did-admin',
			'DIDAdmin',
			array(
				'ajax'       => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'did_admin' ),
				'projectId'  => $pid,
				'licensed'   => DID_Plugin::is_usable() ? 1 : 0,
				'i18n'       => array(
					'running' => 'در حال اجرا…',
					'done'    => 'تمام شد.',
					'error'   => 'خطا',
				),
			)
		);
	}

	public static function handle_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! empty( $_POST['did_save_settings'] ) ) { // phpcs:ignore
			check_admin_referer( 'did_settings' );
			$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore
			DID_Settings::save( $input );
			wp_safe_redirect( add_query_arg( array( 'page' => 'didbani', 'tab' => 'settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! empty( $_POST['did_save_project'] ) ) { // phpcs:ignore
			check_admin_referer( 'did_project' );
			if ( ! DID_Plugin::is_usable() ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'didbani', 'tab' => 'project', 'err' => 'license' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			$name = isset( $_POST['project_name'] ) ? wp_unslash( $_POST['project_name'] ) : ''; // phpcs:ignore
			$own  = isset( $_POST['own_domain'] ) ? wp_unslash( $_POST['own_domain'] ) : ''; // phpcs:ignore
			$comp = isset( $_POST['competitors'] ) ? wp_unslash( $_POST['competitors'] ) : ''; // phpcs:ignore
			$kws  = isset( $_POST['keywords'] ) ? wp_unslash( $_POST['keywords'] ) : ''; // phpcs:ignore
			$id   = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0; // phpcs:ignore

			$id = DID_Db::save_project( $id, $name );
			DID_Db::upsert_domains( $id, $own, DID_Text::lines( $comp ) );
			DID_Db::upsert_keywords( $id, DID_Text::lines( $kws ) );
			DID_Settings::save( array( 'active_project' => $id ) );

			wp_safe_redirect( add_query_arg( array( 'page' => 'didbani', 'tab' => 'project', 'saved' => '1', 'project' => $id ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! empty( $_GET['did_switch'] ) && ! empty( $_GET['project'] ) ) { // phpcs:ignore
			check_admin_referer( 'did_switch' );
			$pid = (int) $_GET['project']; // phpcs:ignore
			if ( DID_Db::project( $pid ) ) {
				DID_Settings::save( array( 'active_project' => $pid ) );
			}
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore
			wp_safe_redirect( add_query_arg( array( 'page' => 'didbani', 'tab' => $tab, 'project' => $pid ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! empty( $_GET['did_delete'] ) && ! empty( $_GET['project'] ) ) { // phpcs:ignore
			check_admin_referer( 'did_delete' );
			if ( DID_Plugin::is_usable() ) {
				$pid = (int) $_GET['project']; // phpcs:ignore
				DID_Db::delete_project( $pid );
				if ( (int) DID_Settings::get( 'active_project' ) === $pid ) {
					DID_Settings::save( array( 'active_project' => 0 ) );
				}
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'didbani', 'tab' => 'project', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public static function current_project_id() {
		if ( isset( $_GET['project'] ) ) { // phpcs:ignore
			$pid = (int) $_GET['project']; // phpcs:ignore
			if ( $pid && DID_Db::project( $pid ) ) {
				return $pid;
			}
		}
		$pid = (int) DID_Settings::get( 'active_project', 0 );
		if ( $pid && DID_Db::project( $pid ) ) {
			return $pid;
		}
		$all = DID_Db::projects();
		if ( $all ) {
			return (int) $all[0]['id'];
		}
		return 0;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		$saved    = ! empty( $_GET['saved'] ); // phpcs:ignore
		$deleted  = ! empty( $_GET['deleted'] ); // phpcs:ignore
		$err      = isset( $_GET['err'] ) ? sanitize_key( wp_unslash( $_GET['err'] ) ) : ''; // phpcs:ignore
		$projects = DID_Db::projects();
		$pid      = self::current_project_id();
		$project  = $pid ? DID_Db::project( $pid ) : null;
		$domains  = $pid ? DID_Db::domains( $pid ) : array();
		$keywords = $pid ? DID_Db::keywords( $pid ) : array();
		$s        = DID_Settings::all();
		$usable   = DID_Plugin::is_usable();
		$device   = DID_Settings::device();
		$cities   = DID_Settings::cities();
		$city     = isset( $_GET['city'] ) ? sanitize_key( wp_unslash( $_GET['city'] ) ) : ''; // phpcs:ignore
		if ( ! in_array( $city, $cities, true ) ) {
			$city = $cities ? $cities[0] : 'tehran';
		}

		include DID_PATH . 'templates/layout.php';
	}

	public static function links( $links ) {
		$url = admin_url( 'admin.php?page=didbani' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">پیشخوان</a>' );
		return $links;
	}

	public static function own_host( array $domains ) {
		foreach ( $domains as $d ) {
			if ( 'own' === $d['kind'] ) {
				return $d['host'];
			}
		}
		return '';
	}

	public static function competitor_text( array $domains ) {
		$lines = array();
		foreach ( $domains as $d ) {
			if ( 'competitor' === $d['kind'] ) {
				$lines[] = $d['host'];
			}
		}
		return implode( "\n", $lines );
	}

	public static function keyword_text( array $keywords ) {
		$lines = array();
		foreach ( $keywords as $k ) {
			$lines[] = $k['keyword'];
		}
		return implode( "\n", $lines );
	}

	public static function engine_label( $engine ) {
		return 'google' === $engine ? 'گوگل' : ( 'bing' === $engine ? 'بینگ' : $engine );
	}

	public static function position_html( $row ) {
		if ( ! $row ) {
			return '<span class="did-muted">—</span>';
		}
		$pos = (int) $row['position'];
		if ( $pos < 1 ) {
			$html = '<span class="did-pill did-pill-off">نیست</span>';
		} else {
			$html = '<span class="did-pill did-pill-on">' . (int) $pos . '</span>';
		}
		$chg = DID_Rank::delta( isset( $row['prev_position'] ) ? $row['prev_position'] : 0, $pos );
		if ( $chg['label'] ) {
			$html .= ' <span class="did-delta did-delta-' . esc_attr( $chg['kind'] ) . '">' . esc_html( $chg['label'] ) . '</span>';
		}
		if ( ! empty( $row['approximate'] ) ) {
			$html .= ' <span class="did-approx" title="نتیجهٔ CSE تقریبی است">تقریبی</span>';
		}
		$url = ! empty( $row['result_url'] ) ? $row['result_url'] : '';
		if ( $url && $pos >= 1 ) {
			return '<a class="did-rank-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $html . '</a>';
		}
		return $html;
	}

	public static function city_nav( $tab, $pid, $cities, $current ) {
		if ( count( $cities ) < 2 ) {
			return;
		}
		echo '<div class="did-city-nav">';
		foreach ( $cities as $slug ) {
			$url = add_query_arg(
				array(
					'page'    => 'didbani',
					'tab'     => $tab,
					'project' => $pid,
					'city'    => $slug,
				),
				admin_url( 'admin.php' )
			);
			$cls = $slug === $current ? ' did-city-on' : '';
			echo '<a class="did-city-chip' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( DID_Geo::label( $slug ) ) . '</a>';
		}
		echo '</div>';
	}
}
