<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hard locks: never publish, never delete, never write master #10833,
 * never rewrite published bodies, never fake-green Product #0.
 */
class Luxe_BMC_Safety {

	const LOG = 'luxe_bmc_action_log';

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
		add_filter( 'wp_insert_post_data', array( $this, 'block_status_change' ), 99, 2 );
		register_shutdown_function( array( $this, 'capture_fatal' ) );
	}

	/**
	 * Never let this plugin's writes publish or trash a post.
	 *
	 * @param array $data    Data.
	 * @param array $postarr Original.
	 * @return array
	 */
	public function block_status_change( $data, $postarr ) {
		if ( empty( $GLOBALS['luxe_bmc_writing'] ) ) {
			return $data;
		}
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id === Luxe_BMC_Plugin::MASTER ) {
			unset( $data['post_content'], $data['post_title'], $data['post_excerpt'], $data['post_status'] );
			return $data;
		}
		if ( isset( $postarr['post_status'] ) ) {
			$data['post_status'] = $postarr['post_status'];
		}
		if ( isset( $data['post_status'] ) && in_array( $data['post_status'], array( 'publish', 'future', 'trash' ), true ) ) {
			$current = $id ? get_post_status( $id ) : 'draft';
			if ( 'publish' !== $current && 'future' !== $current ) {
				$data['post_status'] = $current ? $current : 'draft';
			}
		}
		return $data;
	}

	public function capture_fatal() {
		$err = error_get_last();
		if ( ! is_array( $err ) || empty( $err['type'] ) ) {
			return;
		}
		if ( ! in_array( (int) $err['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			return;
		}
		$file = isset( $err['file'] ) ? (string) $err['file'] : '';
		if ( false === strpos( $file, 'luxe-blog-master-command-center' ) ) {
			return;
		}
		self::log( 'fatal', 'Critical error captured: ' . ( isset( $err['message'] ) ? $err['message'] : '' ) . '. Nothing published.' );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_write_body( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 || $post_id === Luxe_BMC_Plugin::MASTER ) {
			return false;
		}
		$status = get_post_status( $post_id );
		if ( 'publish' === $status || 'future' === $status ) {
			return false;
		}
		return in_array( $status, array( 'draft', 'pending', 'private', 'auto-draft' ), true );
	}

	/**
	 * Backup post_content before a draft write.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function backup( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return false;
		}
		$pack = array(
			'at'      => time(),
			'title'   => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'content' => $post->post_content,
			'status'  => $post->post_status,
		);
		update_post_meta( $post_id, '_luxe_bmc_backup_' . time(), wp_json_encode( $pack ) );
		update_post_meta( $post_id, '_luxe_bmc_last_backup', $pack );
		return true;
	}

	/**
	 * @param string $action Action.
	 * @param string $detail Detail, or a status token when $extra is passed.
	 * @param string $extra  Optional third-arg detail (2.9.0 admin dispatcher).
	 */
	public static function log( $action, $detail, $extra = null ) {
		if ( null !== $extra ) {
			$detail = (string) $extra;
		}
		$log = get_option( self::LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'     => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'action' => $action,
				'detail' => $detail,
			)
		);
		update_option( self::LOG, array_slice( $log, 0, 80 ), false );
	}

	/**
	 * @return array
	 */
	public static function log_rows() {
		$log = get_option( self::LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @return array
	 */
	public static function log_get() {
		return self::log_rows();
	}
}
