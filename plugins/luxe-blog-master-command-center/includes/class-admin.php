<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin → Blog Master Command Center.
 */
class Luxe_BMC_Admin {

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

	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_post_luxe_bmc_action', array( $this, 'handle' ) );
		add_action( 'wp_ajax_luxe_bmc_action', array( $this, 'ajax' ) );
		add_filter( 'plugin_action_links_' . LUXE_BMC_BASENAME, array( $this, 'action_links' ) );
	}

	public function menu() {
		add_menu_page(
			'ADAPTIVE UNIQUENESS + AUTO DEPTH MASTER SCANNER',
			'Blog Master',
			'manage_options',
			'luxe-blog-master',
			array( $this, 'render' ),
			'dashicons-welcome-write-blog',
			56
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'luxe-blog-master' ) ) {
			return;
		}
		wp_enqueue_style(
			'luxe-bmc-admin',
			LUXE_BMC_URL . 'assets/admin.css',
			array(),
			LUXE_BMC_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-blog-master' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Open Command Center</a>' );
		return $links;
	}

	public function maybe_save_settings() {
		if ( empty( $_POST['luxe_bmc_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_bmc_save' );
		$settings = Luxe_BMC_Plugin::get_settings();
		$settings['process_learning'] = empty( $_POST['process_learning'] ) ? 0 : 1;
		$settings['safe_autopilot']   = empty( $_POST['safe_autopilot'] ) ? 0 : 1;
		$settings['date_2026']        = empty( $_POST['date_2026'] ) ? 0 : 1;
		$settings['auto_purge']       = empty( $_POST['auto_purge'] ) ? 0 : 1;
		$settings['mobile_guard']     = 0;
		$settings['display_polish']   = 0;
		Luxe_BMC_Plugin::save_settings( $settings );
		add_settings_error( 'luxe_bmc', 'saved', 'Settings saved. Mobile hiders stay OFF. Nothing published.', 'updated' );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$settings = Luxe_BMC_Plugin::get_settings();
		$audit    = Luxe_BMC_Audit::run();
		$log      = Luxe_BMC_Safety::log_get();
		$last     = Luxe_BMC_Learning::last();
		$process  = array();
		if ( ! empty( $last['process'] ) && is_array( $last['process'] ) ) {
			$process = $last['process'];
		} elseif ( ! empty( $settings['process_greens'] ) && is_array( $settings['process_greens'] ) ) {
			$process = $settings['process_greens'];
		}
		if ( ! $process ) {
			foreach ( Luxe_BMC_Plugin::keep_processes() as $id => $label ) {
				$process[ $id ] = array(
					'id'    => $id,
					'label' => $label,
					'state' => 'green',
					'note'  => 'Armed. Click RUN LEARNING SNAPSHOT NOW to verify live HTML.',
				);
			}
		}
		$categories = Luxe_BMC_Audit::categories();
		$public     = isset( $settings['public_scan'] ) && is_array( $settings['public_scan'] ) ? $settings['public_scan'] : array();
		include LUXE_BMC_DIR . 'templates/admin.php';
	}

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luxe_bmc_action', 'luxe_bmc_nonce' );
		$action = sanitize_key( (string) wp_unslash( $_POST['bmc_action'] ?? '' ) );
		$result = self::dispatch( $action );
		$msg    = rawurlencode( (string) ( $result['message'] ?? 'done' ) );
		$ok     = ! empty( $result['ok'] ) ? '1' : '0';
		wp_safe_redirect( admin_url( 'admin.php?page=luxe-blog-master&bmc_ok=' . $ok . '&bmc_msg=' . $msg ) );
		exit;
	}

	public function ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'luxe_bmc_action', 'luxe_bmc_nonce' );
		$action = sanitize_key( (string) wp_unslash( $_POST['bmc_action'] ?? '' ) );
		wp_send_json( self::dispatch( $action ) );
	}

	/**
	 * @param string $action Action.
	 * @return array
	 */
	public static function dispatch( $action ) {
		switch ( $action ) {
			case 'scan_build':
				return self::scan_build();
			case 'repair_95':
				return self::repair_selected();
			case 'approval_ready':
				return self::approval_ready();
			case 'audit':
				return self::audit_only();
			case 'relink':
				return self::relink_rebuild();
			case 'autopilot':
				return self::toggle_autopilot();
			case 'harden':
				return self::harden();
			case 'daily_draft':
				return self::daily_draft();
			case 'learning':
				return self::learning();
			case 'public_scan':
				return self::public_scan();
			case 'daily_cycle':
				return self::daily_cycle();
			case 'cat_fix':
				return self::cat_fix();
			case 'cat_verify':
				return self::cat_verify();
			default:
				return array(
					'ok'      => false,
					'message' => 'Unknown action.',
				);
		}
	}

	/**
	 * @return array<int,string>
	 */
	public static function routed_actions() {
		return array_keys( Luxe_BMC_Plugin::action_buttons() );
	}

	private static function scan_build() {
		$audit    = Luxe_BMC_Audit::run();
		$settings = Luxe_BMC_Plugin::get_settings();
		$settings['last_scan'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		Luxe_BMC_Plugin::save_settings( $settings );
		$fixed   = 0;
		$skipped = 0;
		$errors  = 0;
		$ids     = isset( $audit['safe_drafts'] ) ? array_slice( $audit['safe_drafts'], 0, 8 ) : array();
		foreach ( $ids as $id ) {
			if ( ! Luxe_BMC_Queue::lock( (int) $id ) ) {
				$skipped++;
				continue;
			}
			$r = Luxe_BMC_Repair::repair_draft( (int) $id );
			Luxe_BMC_Queue::unlock();
			if ( ! empty( $r['ok'] ) ) {
				$fixed++;
			} else {
				$errors++;
			}
		}
		Luxe_BMC_Safety::log( 'scan_build', 'SCAN + BUILD repaired ' . $fixed . ' drafts. Skipped ' . $skipped . '. Errors ' . $errors . '. Status unchanged.' );
		return array(
			'ok'      => true,
			'message' => 'SCAN + BUILD complete. Repaired ' . $fixed . ' drafts. WordPress status unchanged.',
		);
	}

	private static function repair_selected() {
		$ids = isset( $_POST['draft_ids'] ) ? (array) $_POST['draft_ids'] : array();
		$ids = array_map( 'intval', $ids );
		$fixed = 0;
		if ( ! $ids ) {
			$audit = Luxe_BMC_Audit::last();
			if ( empty( $audit['safe_drafts'] ) ) {
				$audit = Luxe_BMC_Audit::run();
			}
			$ids = array_slice( isset( $audit['safe_drafts'] ) ? $audit['safe_drafts'] : array(), 0, 5 );
		}
		foreach ( $ids as $id ) {
			if ( ! Luxe_BMC_Queue::lock( $id ) ) {
				continue;
			}
			$r = Luxe_BMC_Repair::repair_draft( $id );
			Luxe_BMC_Queue::unlock();
			if ( ! empty( $r['ok'] ) ) {
				$fixed++;
			}
		}
		return array(
			'ok'      => true,
			'message' => 'Repair 95+ complete on ' . $fixed . ' drafts. Published posts untouched.',
		);
	}

	private static function approval_ready() {
		$audit = Luxe_BMC_Audit::last();
		if ( empty( $audit['safe_drafts'] ) ) {
			$audit = Luxe_BMC_Audit::run();
		}
		$n   = 0;
		$ids = isset( $audit['safe_drafts'] ) ? array_slice( $audit['safe_drafts'], 0, 10 ) : array();
		foreach ( $ids as $id ) {
			$r = Luxe_BMC_Repair::approval_ready( (int) $id );
			if ( ! empty( $r['ok'] ) ) {
				$n++;
			}
		}
		return array(
			'ok'      => true,
			'message' => $n . ' drafts stamped APPROVAL READY. Still drafts. Never published.',
		);
	}

	private static function audit_only() {
		$audit = Luxe_BMC_Audit::run();
		$settings = Luxe_BMC_Plugin::get_settings();
		$settings['last_audit'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		Luxe_BMC_Plugin::save_settings( $settings );
		return array(
			'ok'      => true,
			'message' => 'Read-only audit stored. Keep ' . (int) $audit['keep_count'] . ' / drafts ' . (int) $audit['draft_count'] . ' / mismatch ' . (int) $audit['mismatch_count'] . '. No posts rewritten.',
		);
	}

	private static function relink_rebuild() {
		$post_id    = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		if ( $post_id && $product_id ) {
			if ( ! Luxe_BMC_Safety::can_write_body( $post_id ) ) {
				return array(
					'ok'      => false,
					'message' => 'Protected live body or master #10833. Nothing rewritten.',
				);
			}
			Luxe_BMC_Plugin::lock_product( $post_id, $product_id );
			$r = Luxe_BMC_Repair::repair_draft( $post_id, $product_id );
			return array(
				'ok'      => ! empty( $r['ok'] ),
				'message' => 'Re-link + rebuild draft #' . $post_id . ' to product #' . $product_id . '. ' . ( $r['reason'] ?? '' ),
			);
		}
		$audit = Luxe_BMC_Audit::last();
		if ( empty( $audit['safe_drafts'] ) ) {
			$audit = Luxe_BMC_Audit::run();
		}
		$n   = 0;
		$ids = isset( $audit['safe_drafts'] ) ? array_slice( $audit['safe_drafts'], 0, 5 ) : array();
		foreach ( $ids as $id ) {
			$r = Luxe_BMC_Repair::repair_draft( (int) $id );
			if ( ! empty( $r['ok'] ) ) {
				$n++;
			}
		}
		return array(
			'ok'      => true,
			'message' => 'Re-link + rebuild ran on ' . $n . ' drafts only. Product #0 holds were not fake-greened.',
		);
	}

	private static function toggle_autopilot() {
		$settings = Luxe_BMC_Plugin::get_settings();
		$settings['safe_autopilot'] = empty( $settings['safe_autopilot'] ) ? 1 : 0;
		Luxe_BMC_Plugin::save_settings( $settings );
		$state = $settings['safe_autopilot'] ? 'ON (drafts only)' : 'OFF';
		if ( $settings['safe_autopilot'] ) {
			self::repair_selected();
		}
		Luxe_BMC_Safety::log( 'autopilot', 'Safe autopilot ' . $state );
		return array(
			'ok'      => true,
			'message' => 'Safe autopilot ' . $state . '. Never publishes.',
		);
	}

	private static function harden() {
		$n = 0;
		foreach ( Luxe_BMC_Audit::published_guides() as $id ) {
			$r = Luxe_BMC_Repair::harden_published( (int) $id );
			if ( ! empty( $r['ok'] ) ) {
				$n++;
			}
		}
		return array(
			'ok'      => true,
			'message' => 'Harden published complete on ' . $n . ' live posts. Meta only. Bodies untouched. Master protected.',
		);
	}

	private static function daily_draft() {
		if ( ! Luxe_BMC_Queue::claim( 'daily_draft' ) ) {
			return array(
				'ok'      => false,
				'message' => 'Heavy lock busy. Try again.',
			);
		}
		$product_id = self::pick_product_without_guide();
		if ( ! $product_id ) {
			Luxe_BMC_Queue::release();
			return array(
				'ok'      => true,
				'message' => 'No unused WooCommerce product found for a new draft.',
			);
		}
		$title   = get_the_title( $product_id ) . ' Buyer Guide 2026';
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => '<!-- luxe-bmc-pending -->',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			Luxe_BMC_Queue::release();
			return array(
				'ok'      => false,
				'message' => 'Could not create draft.',
			);
		}
		Luxe_BMC_Plugin::lock_product( (int) $post_id, $product_id );
		$r = Luxe_BMC_Repair::repair_draft( (int) $post_id );
		Luxe_BMC_Queue::release();
		if ( empty( $r['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => 'Draft created but rebuild failed: ' . ( $r['reason'] ?? '' ),
			);
		}
		Luxe_BMC_Safety::log( 'daily_draft', 'Created draft #' . $post_id . ' for product #' . $product_id . '. Status=draft.' );
		return array(
			'ok'      => true,
			'message' => 'Daily homepage product draft #' . $post_id . ' created. Status=draft. Never published.',
		);
	}

	private static function pick_product_without_guide() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return 0;
		}
		$used  = array();
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 400,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $pid ) {
			$used[ Luxe_BMC_Plugin::product_id( (int) $pid ) ] = true;
		}
		$products = wc_get_products(
			array(
				'limit'   => 80,
				'status'  => 'publish',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		foreach ( $products as $p ) {
			$id = (int) $p->get_id();
			if ( $id && empty( $used[ $id ] ) ) {
				return $id;
			}
		}
		return 0;
	}

	private static function learning() {
		$snap = Luxe_BMC_Learning::run_cycle();
		return array(
			'ok'      => true,
			'message' => 'Learning snapshot stored. Score ' . (int) $snap['score'] . '. Nothing published.',
		);
	}

	private static function public_scan() {
		$public   = Luxe_BMC_Learning::public_scan();
		$settings = Luxe_BMC_Plugin::get_settings();
		$settings['public_scan']       = $public;
		$settings['last_public_scan']  = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		Luxe_BMC_Plugin::save_settings( $settings );
		Luxe_BMC_Safety::log( 'public_scan', 'Public green scan stored. Hiders OFF scored as the safe green.' );
		return array(
			'ok'      => true,
			'message' => 'Public green scan complete. Mobile hiders OFF = green.',
		);
	}

	private static function daily_cycle() {
		self::audit_only();
		self::scan_build();
		self::public_scan();
		self::learning();
		return array(
			'ok'      => true,
			'message' => 'Daily quality cycle complete (audit + scan + public + learning). No publishes.',
		);
	}

	private static function cat_fix() {
		$cat   = isset( $_POST['cat'] ) ? sanitize_text_field( wp_unslash( $_POST['cat'] ) ) : '';
		$cats  = Luxe_BMC_Audit::categories();
		$audit = Luxe_BMC_Audit::last();
		$pool  = isset( $audit['safe_drafts'] ) ? $audit['safe_drafts'] : array();
		if ( $cat && isset( $cats[ $cat ]['draft_ids'] ) ) {
			$pool = $cats[ $cat ]['draft_ids'];
		}
		$n = 0;
		foreach ( array_slice( $pool, 0, 6 ) as $id ) {
			$r = Luxe_BMC_Repair::repair_draft( (int) $id );
			if ( ! empty( $r['ok'] ) ) {
				$n++;
			}
		}
		return array(
			'ok'      => true,
			'message' => 'Category FIX ran on ' . $n . ' drafts' . ( $cat ? ' (' . $cat . ')' : '' ) . '.',
		);
	}

	private static function cat_verify() {
		$cat   = isset( $_POST['cat'] ) ? sanitize_text_field( wp_unslash( $_POST['cat'] ) ) : '';
		$cats  = Luxe_BMC_Audit::categories();
		$pool  = array();
		if ( $cat && isset( $cats[ $cat ]['draft_ids'] ) ) {
			$pool = $cats[ $cat ]['draft_ids'];
		} else {
			$audit = Luxe_BMC_Audit::last();
			$pool  = isset( $audit['safe_drafts'] ) ? $audit['safe_drafts'] : array();
		}
		$n = 0;
		foreach ( array_slice( $pool, 0, 8 ) as $id ) {
			$r = Luxe_BMC_Repair::approval_ready( (int) $id );
			if ( ! empty( $r['ok'] ) ) {
				$n++;
			}
		}
		Luxe_BMC_Safety::log( 'cat_verify', 'FINAL VERIFY ' . $cat . ' stamped ' . $n . ' approval-ready. Read-only for published.' );
		return array(
			'ok'      => true,
			'message' => 'FINAL VERIFY complete for ' . ( $cat ? $cat : 'all' ) . '. ' . $n . ' drafts approval-ready. Never published.',
		);
	}
}
