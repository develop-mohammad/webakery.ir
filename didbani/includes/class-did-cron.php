<?php
defined( 'ABSPATH' ) || exit;

class DID_Cron {

	const HOOK = 'did_run_queue';
	const DAILY = 'did_daily';

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run_queue' ) );
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 120, 'hourly', self::HOOK );
		}
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + 600, 'daily', self::DAILY );
		}
	}

	public static function unschedule() {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
		$ts = wp_next_scheduled( self::DAILY );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::DAILY );
		}
	}

	public static function run_queue() {
		if ( ! DID_Plugin::is_usable() ) {
			return;
		}
		$jobs = DID_Db::pending_jobs();
		foreach ( $jobs as $job ) {
			if ( 'crawl' === $job['type'] ) {
				DID_Crawler::step( $job, 4 );
			} elseif ( 'rank' === $job['type'] ) {
				DID_Rank::step( $job );
			} elseif ( 'backlinks' === $job['type'] ) {
				DID_Backlinks::step( $job );
			}
		}
	}

	public static function daily() {
		if ( ! DID_Plugin::is_usable() ) {
			return;
		}
		if ( 'daily' !== DID_Settings::get( 'schedule', 'off' ) ) {
			return;
		}
		foreach ( DID_Db::projects() as $p ) {
			$pid = (int) $p['id'];
			if ( ! DID_Db::active_job( $pid, 'crawl' ) ) {
				$job = DID_Crawler::start( $pid );
				if ( ! is_wp_error( $job ) && $job ) {
					DID_Crawler::step( $job, 2 );
				}
			}
			if ( ! DID_Db::active_job( $pid, 'rank' ) ) {
				$job = DID_Rank::start( $pid );
				if ( ! is_wp_error( $job ) && $job ) {
					DID_Rank::step( $job );
				}
			}
			if ( DID_Settings::has_dataforseo() && ! DID_Db::active_job( $pid, 'backlinks' ) ) {
				$job = DID_Backlinks::start( $pid );
				if ( ! is_wp_error( $job ) && $job ) {
					DID_Backlinks::step( $job );
				}
			}
		}
	}
}
