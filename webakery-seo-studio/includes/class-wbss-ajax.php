<?php
defined( 'ABSPATH' ) || exit;

class WBSS_Ajax {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$actions = array(
			'wbss_dashboard',
			'wbss_list',
			'wbss_get',
			'wbss_save',
			'wbss_delete',
			'wbss_save_rank',
			'wbss_keyword_ranks',
			'wbss_projects',
			'wbss_save_project',
			'wbss_delete_project',
			'wbss_export',
			'wbss_reseed',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'dispatch' ) );
		}
	}

	public function dispatch() {
		check_ajax_referer( 'wbss_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore
		$method = 'do_' . $action;
		if ( ! method_exists( $this, $method ) ) {
			wp_send_json_error( array( 'message' => 'اکشن نامعتبر.' ), 400 );
		}
		$this->$method();
	}

	private function pid() {
		return isset( $_REQUEST['project_id'] ) ? (int) $_REQUEST['project_id'] : 0; // phpcs:ignore
	}

	private function do_wbss_projects() {
		wp_send_json_success( array( 'items' => WBSS_DB::projects() ) );
	}

	private function do_wbss_dashboard() {
		$days = isset( $_REQUEST['days'] ) ? (int) $_REQUEST['days'] : 30; // phpcs:ignore
		wp_send_json_success( WBSS_DB::dashboard( $this->pid(), $days ) );
	}

	private function do_wbss_list() {
		$module = isset( $_REQUEST['module'] ) ? sanitize_key( wp_unslash( $_REQUEST['module'] ) ) : ''; // phpcs:ignore
		$args   = array(
			'days'   => isset( $_REQUEST['days'] ) ? (int) $_REQUEST['days'] : 90, // phpcs:ignore
			'module' => isset( $_REQUEST['filter_module'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_module'] ) ) : '', // phpcs:ignore
			'limit'  => isset( $_REQUEST['limit'] ) ? (int) $_REQUEST['limit'] : 80, // phpcs:ignore
		);
		wp_send_json_success( array( 'items' => WBSS_DB::list_rows( $module, $this->pid(), $args ) ) );
	}

	private function do_wbss_get() {
		$module = isset( $_REQUEST['module'] ) ? sanitize_key( wp_unslash( $_REQUEST['module'] ) ) : ''; // phpcs:ignore
		$id     = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore
		$row    = WBSS_DB::get_row( $module, $id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'یافت نشد.' ), 404 );
		}
		wp_send_json_success( array( 'item' => $row ) );
	}

	private function do_wbss_save() {
		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : ''; // phpcs:ignore
		$data   = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore
		$data['project_id'] = $this->pid();
		$res = WBSS_DB::save_row( $module, $data );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'id' => (int) $res ) );
	}

	private function do_wbss_delete() {
		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : ''; // phpcs:ignore
		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		if ( ! WBSS_DB::delete_row( $module, $id ) ) {
			wp_send_json_error( array( 'message' => 'حذف نشد.' ), 400 );
		}
		wp_send_json_success();
	}

	private function do_wbss_save_rank() {
		$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore
		$res  = WBSS_DB::save_rank( $data );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'id' => (int) $res ) );
	}

	private function do_wbss_keyword_ranks() {
		$id = isset( $_REQUEST['keyword_id'] ) ? (int) $_REQUEST['keyword_id'] : 0; // phpcs:ignore
		wp_send_json_success( array( 'items' => WBSS_DB::keyword_ranks( $id, 180 ) ) );
	}

	private function do_wbss_save_project() {
		$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore
		$res  = WBSS_DB::save_project( $data );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'id' => (int) $res ) );
	}

	private function do_wbss_delete_project() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		if ( ! WBSS_DB::delete_project( $id ) ) {
			wp_send_json_error( array( 'message' => 'حذف نشد.' ), 400 );
		}
		wp_send_json_success();
	}

	private function do_wbss_export() {
		$res = WBSS_DB::export_project( $this->pid() );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	private function do_wbss_reseed() {
		delete_option( 'wbss_seeded' );
		$id = WBSS_Seed::run();
		wp_send_json_success( array( 'id' => (int) $id ) );
	}
}
