<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Learning' ) ) {
	return;
}

/**
 * 24/7 catalog learning. One product per cycle. Hourly read-only snapshot.
 * Never publishes, never deletes, never rewrites Amazon URLs.
 */
class LAPS_Learning {

	const CRON           = 'laps_learn';
	const LOCK           = 'laps_learn_lock';
	const OPTION_CURSOR  = 'laps_learn_cursor';
	const OPTION_LAST    = 'laps_learn_last';
	const OPTION_LOG     = 'laps_learn_log';
	const OPTION_CATALOG = 'laps_catalog';
	const OPTION_SNAP    = 'laps_learn_snapshot_at';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register 24/7 loop.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'cron_cycle' ) );
		add_action( 'init', array( $this, 'ensure_cron' ), 40 );
		add_action( 'shutdown', array( $this, 'maybe_reschedule' ), 40 );
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['laps_five'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'luxe-affiliate-product-scout' ),
		);
		return $schedules;
	}

	/**
	 * Activation: schedule 24/7, do not repair immediately.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 120, 'laps_five', self::CRON );
		}
	}

	/**
	 * Clear cron.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Admin: run one cycle or a read-only snapshot.
	 */
	public function maybe_manual() {
		$one  = ! empty( $_POST['laps_learn_one'] );
		$snap = ! empty( $_POST['laps_learn_snapshot'] );
		if ( ! $one && ! $snap ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'laps_run' );
		delete_transient( self::LOCK );
		try {
			if ( $snap ) {
				$out = $this->snapshot_catalog( 'manual' );
				add_settings_error(
					'laps',
					'snap',
					sprintf(
						/* translators: 1: total, 2: readiness, 3: integrity */
						__( 'Learning snapshot stored. Catalog %1$d products, readiness %2$d, integrity %3$d.', 'luxe-affiliate-product-scout' ),
						(int) $out['scanned'],
						(int) $out['avg_readiness'],
						(int) $out['avg_integrity']
					),
					'updated'
				);
				return;
			}
			$out = $this->cycle_one( 'manual' );
			add_settings_error(
				'laps',
				'learn',
				sprintf(
					/* translators: 1: id, 2: readiness, 3: integrity */
					__( '24/7 cycle finished. Product %1$d scored %2$d / %3$d. Publication status preserved.', 'luxe-affiliate-product-scout' ),
					(int) $out['id'],
					(int) $out['readiness'],
					(int) $out['integrity']
				),
				'updated'
			);
		} catch ( Exception $e ) {
			add_settings_error( 'laps', 'learn-err', $e->getMessage(), 'error' );
		} catch ( Throwable $e ) {
			add_settings_error( 'laps', 'learn-err', $e->getMessage(), 'error' );
		}
	}

	/**
	 * Keep WP-Cron alive without running repair on the public request.
	 */
	public function ensure_cron() {
		if ( ! LAPS_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'laps_five', self::CRON );
		}
	}

	/**
	 * If the recurring event vanished, queue one more cycle.
	 */
	public function maybe_reschedule() {
		if ( ! LAPS_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON ) ) {
			return;
		}
		wp_schedule_single_event( time() + 90, self::CRON );
	}

	/**
	 * One product + occasional read-only snapshot.
	 */
	public function cron_cycle() {
		if ( ! LAPS_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		if ( ! function_exists( 'laps_woocommerce_ready' ) || ! laps_woocommerce_ready() ) {
			return;
		}
		try {
			$this->cycle_one();
			$this->maybe_snapshot();
		} catch ( Exception $e ) {
			error_log( 'Luxe Product Scout 24/7: ' . $e->getMessage() );
		} catch ( Throwable $e ) {
			error_log( 'Luxe Product Scout 24/7: ' . $e->getMessage() );
		}
	}

	/**
	 * Repair and rescore exactly one product.
	 *
	 * @param string $source Source label.
	 * @return array
	 */
	public function cycle_one( $source = 'cron' ) {
		if ( get_transient( self::LOCK ) ) {
			$last = get_option( self::OPTION_LAST, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::LOCK, 1, 40 );

		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		$ids    = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'   => 1,
				'offset'  => $cursor,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'DESC',
				'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
			)
		);

		$out = array(
			'at'        => time(),
			'source'    => $source,
			'id'        => 0,
			'brand'     => '',
			'title'     => '',
			'readiness' => 0,
			'integrity' => 0,
			'status'    => '',
			'repaired'  => 0,
			'cursor'    => $cursor,
			'did'       => array(),
		);

		if ( empty( $ids ) && $cursor > 0 ) {
			update_option( self::OPTION_CURSOR, 0, false );
			$cursor = 0;
			$ids    = wc_get_products(
				array(
					'status'  => array( 'publish', 'draft', 'pending', 'private' ),
					'limit'   => 1,
					'offset'  => 0,
					'return'  => 'ids',
					'orderby' => 'ID',
					'order'   => 'DESC',
					'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
				)
			);
			$out['wrapped'] = 1;
		}

		if ( empty( $ids ) ) {
			update_option( self::OPTION_CURSOR, 0, false );
			$out['cursor'] = 0;
			update_option( self::OPTION_LAST, $out, false );
			delete_transient( self::LOCK );
			return $out;
		}

		$id      = (int) $ids[0];
		$product = wc_get_product( $id );
		$did     = array();
		$score   = array(
			'readiness' => 0,
			'integrity' => 0,
			'status'    => '',
			'brand'     => '',
			'title'     => '',
		);
		if ( $product ) {
			$fixer = new LAPS_Repair();
			$fix   = $fixer->repair_product( $product );
			$fresh = wc_get_product( $id );
			if ( $fresh ) {
				$product = $fresh;
			}
			$score = LAPS_Score::evaluate( $product, isset( $fix['flags'] ) ? $fix['flags'] : array() );
			LAPS_Score::write( $id, $score );
			$did = isset( $fix['did'] ) ? $fix['did'] : array();
			$out['repaired'] = ( ! empty( $fix['status'] ) && 'repaired' === $fix['status'] ) ? 1 : 0;
		}

		$next = $cursor + 1;
		update_option( self::OPTION_CURSOR, $next, false );

		$out['id']        = $id;
		$out['brand']     = isset( $score['brand'] ) ? $score['brand'] : '';
		$out['title']     = isset( $score['title'] ) ? $score['title'] : '';
		$out['readiness'] = isset( $score['readiness'] ) ? (int) $score['readiness'] : 0;
		$out['integrity'] = isset( $score['integrity'] ) ? (int) $score['integrity'] : 0;
		$out['status']    = isset( $score['status'] ) ? $score['status'] : '';
		$out['cursor']    = $next;
		$out['did']       = $did;
		update_option( self::OPTION_LAST, $out, false );
		$this->push_log( $out );
		delete_transient( self::LOCK );
		return $out;
	}

	/**
	 * Hourly read-only average of stored scores. Does not publish.
	 */
	public function maybe_snapshot() {
		$last = (int) get_option( self::OPTION_SNAP, 0 );
		if ( $last && ( time() - $last ) < HOUR_IN_SECONDS ) {
			return;
		}
		$this->snapshot_catalog( 'cron' );
	}

	/**
	 * @param string $source Source label.
	 * @return array
	 */
	public function snapshot_catalog( $source = 'cron' ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$ids = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'   => 400,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'DESC',
				'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
			)
		);
		$prev    = get_option( self::OPTION_CATALOG, array() );
		$changes = ( is_array( $prev ) && isset( $prev['changes'] ) && is_array( $prev['changes'] ) ) ? $prev['changes'] : array();
		$total   = 0;
		$ready   = 0;
		$review  = 0;
		$held    = 0;
		$unknown = 0;
		$sum_r   = 0;
		$sum_i   = 0;
		$tax_c   = 0;
		$attr_c  = 0;
		$below   = array();
		foreach ( (array) $ids as $id ) {
			$id  = (int) $id;
			$row = LAPS_Score::read( $id );
			$r   = (int) $row['readiness'];
			$i   = (int) $row['integrity'];
			if ( $r < 1 && $i < 1 ) {
				continue;
			}
			$total++;
			$sum_r += $r;
			$sum_i += $i;
			$reasons = ( isset( $row['reasons'] ) && is_array( $row['reasons'] ) ) ? $row['reasons'] : array();
			if ( in_array( 'taxonomy-conflict', $reasons, true ) ) {
				$tax_c++;
			}
			if ( in_array( 'brand-attribute-conflict', $reasons, true ) ) {
				$attr_c++;
			}
			if ( 'HOLD' === $row['status'] ) {
				$held++;
			} elseif ( $r >= 95 && $i >= 95 ) {
				$ready++;
			} else {
				$review++;
			}
			if ( 'Unknown' === $row['brand'] || '' === $row['brand'] ) {
				$unknown++;
			}
			if ( 'HOLD' === $row['status'] || $r < 95 || $i < 95 ) {
				$below[] = array(
					'id'        => $id,
					'title'     => function_exists( 'get_the_title' ) ? get_the_title( $id ) : '',
					'brand'     => $row['brand'],
					'readiness' => $r,
					'integrity' => $i,
					'status'    => $row['status'],
					'reasons'   => $reasons,
				);
			}
		}
		$out = array(
			'at'                   => time(),
			'source'               => $source,
			'scanned'              => $total,
			'repaired'             => 0,
			'ready'                => $ready,
			'review'               => $review,
			'held'                 => $held,
			'unknown'              => $unknown,
			'avg_readiness'        => $total ? (int) round( $sum_r / $total ) : 0,
			'avg_integrity'        => $total ? (int) round( $sum_i / $total ) : 0,
			'taxonomy_conflicts'   => $tax_c,
			'attribute_conflicts'  => $attr_c,
			'need_work'            => count( $below ),
			'repair'               => 0,
			'below'                => array_slice( $below, 0, 80 ),
			'changes'              => array_slice( $changes, 0, 40 ),
		);
		update_option( self::OPTION_CATALOG, $out, false );
		update_option( self::OPTION_SNAP, time(), false );
		$this->push_log(
			array(
				'at'        => $out['at'],
				'source'    => 'snapshot-' . $source,
				'id'        => 0,
				'title'     => 'Learning snapshot stored',
				'readiness' => $out['avg_readiness'],
				'integrity' => $out['avg_integrity'],
				'status'    => 'snapshot',
				'repaired'  => 0,
				'cursor'    => (int) get_option( self::OPTION_CURSOR, 0 ),
				'did'       => array( 'total=' . $total ),
			)
		);
		return $out;
	}

	/**
	 * @param array $row Cycle row.
	 */
	private function push_log( $row ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $row );
		update_option( self::OPTION_LOG, array_slice( $log, 0, 30 ), false );
	}

	/**
	 * Full-catalog board. Tiny batches must not replace this.
	 *
	 * @return array
	 */
	public static function catalog() {
		$cat = get_option( self::OPTION_CATALOG, array() );
		return is_array( $cat ) ? $cat : array();
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION_LAST, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @return int
	 */
	public static function next_ts() {
		$next = wp_next_scheduled( self::CRON );
		return $next ? (int) $next : 0;
	}
}
