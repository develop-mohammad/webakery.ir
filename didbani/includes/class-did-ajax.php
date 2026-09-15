<?php
defined( 'ABSPATH' ) || exit;

class DID_Ajax {

	public static function hooks() {
		add_action( 'wp_ajax_did_crawl_step', array( __CLASS__, 'crawl_step' ) );
		add_action( 'wp_ajax_did_rank_step', array( __CLASS__, 'rank_step' ) );
		add_action( 'wp_ajax_did_backlinks_step', array( __CLASS__, 'backlinks_step' ) );
		add_action( 'wp_ajax_did_job_status', array( __CLASS__, 'job_status' ) );
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		check_ajax_referer( 'did_admin', 'nonce' );
		if ( ! DID_Plugin::is_usable() ) {
			wp_send_json_error( array( 'message' => 'لایسنس یا دوره آزمایشی دیدبانی فعال نیست.' ) );
		}
	}

	public static function crawl_step() {
		self::guard();
		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0; // phpcs:ignore
		if ( $project_id < 1 ) {
			wp_send_json_error( array( 'message' => 'پروژه نامعتبر است.' ) );
		}
		$job = DID_Db::active_job( $project_id, 'crawl' );
		if ( ! $job ) {
			$job = DID_Crawler::start( $project_id );
			if ( is_wp_error( $job ) ) {
				wp_send_json_error( array( 'message' => $job->get_error_message() ) );
			}
		}
		$progress = DID_Crawler::step( $job, 3 );
		$progress['job_id'] = (int) $job['id'];
		wp_send_json_success( $progress );
	}

	public static function rank_step() {
		self::guard();
		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0; // phpcs:ignore
		if ( $project_id < 1 ) {
			wp_send_json_error( array( 'message' => 'پروژه نامعتبر است.' ) );
		}
		$job = DID_Db::active_job( $project_id, 'rank' );
		if ( ! $job ) {
			$job = DID_Rank::start( $project_id );
			if ( is_wp_error( $job ) ) {
				wp_send_json_error( array( 'message' => $job->get_error_message() ) );
			}
		}
		$progress = DID_Rank::step( $job );
		$progress['job_id'] = (int) $job['id'];
		wp_send_json_success( $progress );
	}

	public static function backlinks_step() {
		self::guard();
		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0; // phpcs:ignore
		if ( $project_id < 1 ) {
			wp_send_json_error( array( 'message' => 'پروژه نامعتبر است.' ) );
		}
		$job = DID_Db::active_job( $project_id, 'backlinks' );
		if ( ! $job ) {
			$job = DID_Backlinks::start( $project_id );
			if ( is_wp_error( $job ) ) {
				wp_send_json_error( array( 'message' => $job->get_error_message() ) );
			}
		}
		$progress = DID_Backlinks::step( $job );
		$progress['job_id'] = (int) $job['id'];
		wp_send_json_success( $progress );
	}

	public static function job_status() {
		self::guard();
		$project_id = isset( $_POST['project_id'] ) ? (int) $_POST['project_id'] : 0; // phpcs:ignore
		$crawl      = DID_Db::latest_job( $project_id, 'crawl' );
		$rank       = DID_Db::latest_job( $project_id, 'rank' );
		$backlinks  = DID_Db::latest_job( $project_id, 'backlinks' );
		wp_send_json_success(
			array(
				'crawl'     => $crawl,
				'rank'      => $rank,
				'backlinks' => $backlinks,
			)
		);
	}
}
