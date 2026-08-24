<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One heavy blog at a time. Prevents critical errors.
 */
class Luxe_BMC_Queue {

	const TRANSIENT = 'luxe_bmc_heavy_lock';
	const OPTION    = 'luxe_bmc_queue';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() {
		add_action( 'luxe_bmc_run_queued', array( $this, 'run_next' ) );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function lock( $post_id ) {
		if ( get_transient( self::TRANSIENT ) ) {
			$q   = get_option( self::OPTION, array() );
			$q[] = (int) $post_id;
			update_option( self::OPTION, array_values( array_unique( $q ) ), false );
			return false;
		}
		set_transient( self::TRANSIENT, (int) $post_id, 90 );
		return true;
	}

	public static function unlock() {
		delete_transient( self::TRANSIENT );
		$q = get_option( self::OPTION, array() );
		if ( is_array( $q ) && $q ) {
			$next = array_shift( $q );
			update_option( self::OPTION, $q, false );
			wp_schedule_single_event( time() + 5, 'luxe_bmc_run_queued', array( (int) $next ) );
		}
	}

	public function run_next( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! self::lock( $post_id ) ) {
			return;
		}
		Luxe_BMC_Repair::repair_draft( $post_id );
		self::unlock();
	}

	/**
	 * @return int
	 */
	public static function waiting() {
		$q = get_option( self::OPTION, array() );
		return is_array( $q ) ? count( $q ) : 0;
	}

	/**
	 * Claim the one-blog heavy lock.
	 *
	 * @param string $key Optional key.
	 * @return bool
	 */
	public static function claim( $key = '' ) {
		unset( $key );
		return self::lock( 1 );
	}

	public static function release() {
		self::unlock();
	}
}
