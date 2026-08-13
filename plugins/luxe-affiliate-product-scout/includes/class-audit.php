<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAPS_Audit' ) ) {
	return;
}

/**
 * Repair ALL + Full Catalog Audit. Bounded batches. Never publishes.
 */
class LAPS_Audit {

	const OPTION_LAST    = 'laps_last_audit';
	const OPTION_REPAIR  = 'laps_last_repair';
	const OPTION_DASH    = 'laps_dashboard';
	const OPTION_LOG     = 'laps_log';
	const OPTION_CURSOR  = 'laps_cursor';
	const CRON           = 'laps_heal';
	const LOCK           = 'laps_lock';

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
	 * Register cron and admin actions.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( self::CRON, array( $this, 'cron_batch' ) );
		add_action( 'admin_init', array( $this, 'ensure_cron' ) );
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
		$schedules['laps_six'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours', 'luxe-affiliate-product-scout' ),
		);
		return $schedules;
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Keep cron alive.
	 */
	public function ensure_cron() {
		if ( LAPS_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			wp_clear_scheduled_hook( self::CRON );
			return;
		}
		if ( ! LAPS_Plugin::instance()->enabled( 'auto_repair' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 180, 'laps_six', self::CRON );
		}
	}

	/**
	 * Admin buttons.
	 */
	public function maybe_manual() {
		$repair_all = ! empty( $_POST['laps_repair_all'] );
		$audit_all  = ! empty( $_POST['laps_audit_all'] );
		$batch      = ! empty( $_POST['laps_repair_batch'] );
		if ( ! $repair_all && ! $audit_all && ! $batch ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'laps_run' );
		delete_transient( self::LOCK );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}
		if ( $repair_all ) {
			update_option( self::OPTION_CURSOR, 0, false );
			$result = $this->run_catalog( true, 400, true );
			if ( class_exists( 'LAPS_Learning' ) ) {
				update_option( LAPS_Learning::OPTION_SNAP, time(), false );
			}
			LAPS_Cache::flush();
			add_settings_error(
				'laps',
				'repair',
				sprintf(
					/* translators: 1: scanned, 2: repaired, 3: held, 4: ready */
					__( 'Repair ALL + Recalculate finished. Scanned %1$d, repaired %2$d, held %3$d, homepage-ready %4$d. Products with supported brand + ASIN + price + image and no hard risk flags now score 95–100. Unknown-brand and renewed items stay below 95.', 'luxe-affiliate-product-scout' ),
					(int) $result['scanned'],
					(int) $result['repaired'],
					(int) $result['held'],
					(int) $result['ready']
				),
				'updated'
			);
			return;
		}
		if ( $audit_all ) {
			update_option( self::OPTION_CURSOR, 0, false );
			$result = $this->run_catalog( false, 400, true );
			if ( class_exists( 'LAPS_Learning' ) ) {
				update_option( LAPS_Learning::OPTION_SNAP, time(), false );
			}
			LAPS_Cache::flush();
			add_settings_error(
				'laps',
				'audit',
				sprintf(
					/* translators: 1: scanned, 2: ready, 3: review, 4: hold */
					__( 'Full Catalog Audit finished. Scanned %1$d. Homepage-ready %2$d, REVIEW %3$d, HOLD %4$d.', 'luxe-affiliate-product-scout' ),
					(int) $result['scanned'],
					(int) $result['ready'],
					(int) $result['review'],
					(int) $result['held']
				),
				'updated'
			);
			return;
		}
		$result = $this->run_catalog( true, LAPS_Plugin::instance()->batch_size(), false );
		add_settings_error(
			'laps',
			'batch',
			sprintf(
				/* translators: 1: scanned, 2: repaired */
				__( 'Batch finished. Scanned %1$d, repaired %2$d.', 'luxe-affiliate-product-scout' ),
				(int) $result['scanned'],
				(int) $result['repaired']
			),
			'updated'
		);
	}

	/**
	 * Cron batch.
	 */
	public function cron_batch() {
		if ( LAPS_Plugin::instance()->enabled( 'learning_24_7' ) ) {
			return;
		}
		try {
			$this->run_catalog( true, LAPS_Plugin::instance()->batch_size() );
		} catch ( Exception $e ) {
			error_log( 'Luxe Product Scout cron: ' . $e->getMessage() );
		} catch ( Throwable $e ) {
			error_log( 'Luxe Product Scout cron: ' . $e->getMessage() );
		}
	}

	/**
	 * @param bool $repair        Whether to repair before scoring.
	 * @param int  $limit         Batch size. 400 = full catalog pass.
	 * @param bool $replace_board Whether this run is the full-catalog scoreboard.
	 * @return array
	 */
	public function run_catalog( $repair, $limit = 25, $replace_board = false ) {
		if ( get_transient( self::LOCK ) ) {
			$last = get_option( self::OPTION_DASH, array() );
			return is_array( $last ) ? $last : array();
		}
		set_transient( self::LOCK, 1, 90 );

		if ( ! function_exists( 'wc_get_products' ) ) {
			delete_transient( self::LOCK );
			return array(
				'scanned'  => 0,
				'repaired' => 0,
				'held'     => 0,
				'review'   => 0,
				'ready'    => 0,
				'error'    => 'woocommerce-missing',
				'at'       => time(),
			);
		}

		$limit  = (int) $limit;
		$cap    = ( $limit < 1 ) ? 400 : min( 400, $limit );
		$cursor = (int) get_option( self::OPTION_CURSOR, 0 );
		$ids    = wc_get_products(
			array(
				'status'  => array( 'publish', 'draft', 'pending', 'private' ),
				'limit'   => $cap,
				'offset'  => $cursor,
				'return'  => 'ids',
				'orderby' => 'ID',
				'order'   => 'DESC',
				'type'    => array( 'simple', 'variable', 'external', 'grouped' ),
			)
		);

		$fixer    = new LAPS_Repair();
		$scanned  = 0;
		$repaired = 0;
		$held     = 0;
		$review   = 0;
		$ready    = 0;
		$clean    = 0;
		$sum_r    = 0;
		$sum_i    = 0;
		$unknown  = 0;
		$tax_c    = 0;
		$attr_c   = 0;
		$below    = array();
		$changes  = array();

		foreach ( $ids as $id ) {
			$scanned++;
			try {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				$fix = array( 'flags' => array() );
				if ( $repair ) {
					$fixer = new LAPS_Repair();
					$fix   = $fixer->repair_product( $product );
					$product = wc_get_product( $id );
					if ( 'repaired' === $fix['status'] ) {
						$repaired++;
						$changes[] = $fix;
					} elseif ( 'held' !== $fix['status'] ) {
						$clean++;
					}
				}
				$score = LAPS_Score::evaluate( $product, isset( $fix['flags'] ) ? $fix['flags'] : array() );
				LAPS_Score::write( (int) $id, $score );
			} catch ( Exception $e ) {
				continue;
			} catch ( Throwable $e ) {
				continue;
			}
			$sum_r += (int) $score['readiness'];
			$sum_i += (int) $score['integrity'];
			if ( 'HOLD' === $score['status'] ) {
				$held++;
			} elseif ( 'READY' === $score['status'] ) {
				$ready++;
			} else {
				$review++;
			}
			if ( 'Unknown' === $score['brand'] ) {
				$unknown++;
			}
			if ( in_array( 'taxonomy-conflict', $score['reasons'], true ) ) {
				$tax_c++;
			}
			if ( in_array( 'brand-attribute-conflict', $score['reasons'], true ) ) {
				$attr_c++;
			}
			if ( 'yes' !== $score['ready'] ) {
				$below[] = array(
					'id'        => (int) $id,
					'title'     => $score['title'],
					'brand'     => $score['brand'],
					'readiness' => $score['readiness'],
					'integrity' => $score['integrity'],
					'status'    => $score['status'],
					'reasons'   => $score['reasons'],
				);
			}
		}

		if ( $repair && 0 === $cursor ) {
			try {
				$site  = $fixer->repair_homepage_shell();
				$about = $fixer->maybe_about_draft();
				if ( $site || $about ) {
					$changes[] = array(
						'id'     => 0,
						'title'  => 'Site shell',
						'brand'  => 'LuxeTrendsetters',
						'status' => 'repaired',
						'did'    => array_merge( $site, $about ),
					);
				}
			} catch ( Exception $e ) {
				$site = array();
			} catch ( Throwable $e ) {
				$site = array();
			}
		}

		$next = $cursor + $scanned;
		if ( $scanned < $cap ) {
			$next = 0;
		}
		update_option( self::OPTION_CURSOR, $next, false );

		$avg_r = $scanned ? (int) round( $sum_r / $scanned ) : 0;
		$avg_i = $scanned ? (int) round( $sum_i / $scanned ) : 0;

		$out = array(
			'at'         => time(),
			'repair'     => $repair ? 1 : 0,
			'scanned'    => $scanned,
			'repaired'   => $repaired,
			'held'       => $held,
			'review'     => $review,
			'ready'      => $ready,
			'clean'      => $clean,
			'cursor'     => $next,
			'avg_readiness' => $avg_r,
			'avg_integrity' => $avg_i,
			'unknown'    => $unknown,
			'taxonomy_conflicts' => $tax_c,
			'attribute_conflicts' => $attr_c,
			'need_work'  => count( $below ),
			'below'      => array_slice( $below, 0, 80 ),
			'changes'    => array_slice( $changes, 0, 40 ),
		);
		if ( $replace_board ) {
			update_option( self::OPTION_DASH, $out, false );
			if ( class_exists( 'LAPS_Learning' ) ) {
				update_option( LAPS_Learning::OPTION_CATALOG, $out, false );
			}
		}
		if ( $repair ) {
			update_option( self::OPTION_REPAIR, $out, false );
		} else {
			update_option( self::OPTION_LAST, $out, false );
		}
		$this->push_log( $out );
		delete_transient( self::LOCK );
		return $out;
	}

	/**
	 * @param array $result Result.
	 */
	private function push_log( $result ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'       => $result['at'],
				'repair'   => $result['repair'],
				'scanned'  => $result['scanned'],
				'repaired' => $result['repaired'],
				'held'     => $result['held'],
				'review'   => $result['review'],
				'ready'    => $result['ready'],
				'avg_readiness' => $result['avg_readiness'],
				'avg_integrity' => $result['avg_integrity'],
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 20 ), false );
	}

	/**
	 * @return array
	 */
	public static function dashboard() {
		if ( class_exists( 'LAPS_Learning' ) ) {
			$cat = LAPS_Learning::catalog();
			if ( ! empty( $cat['scanned'] ) ) {
				return $cat;
			}
		}
		$dash = get_option( self::OPTION_DASH, array() );
		return is_array( $dash ) ? $dash : array();
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}
}
