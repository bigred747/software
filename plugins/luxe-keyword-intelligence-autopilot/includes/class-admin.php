<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword Autopilot screen.
 */
class Luxe_KIA_Admin {

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
	 * Register the menu and actions.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'handle' ), 5 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . LUXE_KIA_BASENAME, array( $this, 'links' ) );
	}

	/**
	 * Menu item.
	 */
	public function menu() {
		add_menu_page(
			__( 'Keyword Autopilot', 'luxe-keyword-intelligence-autopilot' ),
			__( 'Keyword Autopilot', 'luxe-keyword-intelligence-autopilot' ),
			'manage_options',
			'luxe-keyword-intelligence',
			array( $this, 'render' ),
			'dashicons-search',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-keyword-intelligence' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-kia-admin',
			LUXE_KIA_URL . 'assets/admin.css',
			array(),
			LUXE_KIA_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-keyword-intelligence' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-keyword-intelligence-autopilot' ) . '</a>' );
		return $links;
	}

	/**
	 * Save, burst, restore.
	 */
	public function handle() {
		if ( empty( $_POST['luxe_kia_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_kia_action' );
		$engine = Luxe_KIA_Plugin::instance()->engine();
		if ( ! empty( $_POST['luxe_kia_save'] ) ) {
			$this->save();
			$this->note( __( 'Keyword Autopilot settings saved. Posts were not published.', 'luxe-keyword-intelligence-autopilot' ) );
			return;
		}
		if ( ! empty( $_POST['luxe_kia_burst'] ) ) {
			$result = $engine->cycle( true );
			$this->note( isset( $result['message'] ) ? (string) $result['message'] : '' );
			return;
		}
		if ( ! empty( $_POST['luxe_kia_cycle'] ) ) {
			$result = $engine->cycle( false );
			$this->note( isset( $result['message'] ) ? (string) $result['message'] : '' );
			return;
		}
		if ( ! empty( $_POST['luxe_kia_tax'] ) ) {
			$count = $engine->guard_terms();
			$this->note( sprintf( __( 'Taxonomy guard updated %d term(s) to noindex, follow.', 'luxe-keyword-intelligence-autopilot' ), (int) $count ) );
			return;
		}
		if ( ! empty( $_POST['luxe_kia_id'] ) ) {
			$engine->one( absint( $_POST['luxe_kia_post'] ) );
			$this->note( __( 'That item was scanned. Its WordPress status was not changed.', 'luxe-keyword-intelligence-autopilot' ) );
			return;
		}
		if ( ! empty( $_POST['luxe_kia_restore'] ) ) {
			$engine->restore( absint( $_POST['luxe_kia_post'] ) );
			$this->note( __( 'Rank Math fields were restored from the backup.', 'luxe-keyword-intelligence-autopilot' ) );
		}
	}

	/**
	 * Short queue notice.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_luxe-keyword-intelligence' === $screen->id ) {
			return;
		}
		$state = get_option( Luxe_KIA_Engine::STATE, array() );
		$text  = ( is_array( $state ) && ! empty( $state['message'] ) ) ? (string) $state['message'] : '';
		echo '<div class="notice notice-info"><p><strong>Luxe Keyword Intelligence Autopilot v' . esc_html( LUXE_KIA_VERSION ) . '</strong> ';
		echo esc_html( 'runs a bounded batch. It never publishes and never rewrites article bodies. ' . $text );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=luxe-keyword-intelligence' ) ) . '">' . esc_html__( 'Open Keyword Autopilot', 'luxe-keyword-intelligence-autopilot' ) . '</a></p></div>';
	}

	/**
	 * Render the board.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin   = Luxe_KIA_Plugin::instance();
		$settings = $plugin->settings();
		$engine   = $plugin->engine();
		$coverage = $engine->coverage();
		$state    = get_option( Luxe_KIA_Engine::STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$activity = get_option( Luxe_KIA_Engine::ACTIVITY, array() );
		if ( ! is_array( $activity ) ) {
			$activity = array();
		}
		$load  = Luxe_KIA_Gate::load();
		$batch = Luxe_KIA_Gate::batch_size( $load, Luxe_KIA_Gate::free_bytes(), $settings );
		$burst = Luxe_KIA_Gate::burst_size( $load, Luxe_KIA_Gate::free_bytes() );
		settings_errors( 'luxe_kia' );
		include LUXE_KIA_DIR . 'templates/admin.php';
	}

	/**
	 * Save the whitelist.
	 */
	private function save() {
		$current = Luxe_KIA_Plugin::instance()->settings();
		$next    = Luxe_KIA_Plugin::defaults();
		$checks  = array( 'enabled', 'repair_meta', 'repair_alt', 'scan_published', 'scan_scheduled', 'types_post', 'types_product', 'types_page' );
		foreach ( $checks as $key ) {
			$next[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
		}
		$numbers = array(
			'interval'   => array( 2, 60 ),
			'batch'      => array( 1, 20 ),
			'max_scans'  => array( 24, 5000 ),
			'max_repairs'=> array( 12, 2000 ),
			'phrases'    => array( 3, 5 ),
			'green'      => array( 95, 100 ),
			'confidence' => array( 60, 95 ),
			'cooldown'   => array( 1, 48 ),
			'budget'     => array( 4, 20 ),
		);
		foreach ( $numbers as $key => $range ) {
			$value = isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : (int) $current[ $key ];
			if ( $value < $range[0] ) {
				$value = $range[0];
			}
			if ( $value > $range[1] ) {
				$value = $range[1];
			}
			$next[ $key ] = $value;
		}
		$next['taxonomies'] = isset( $_POST['taxonomies'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomies'] ) ) : '';
		$next['protected']  = isset( $_POST['protected'] ) ? sanitize_text_field( wp_unslash( $_POST['protected'] ) ) : '';
		update_option( Luxe_KIA_Plugin::OPTION, $next, false );
		Luxe_KIA_Plugin::reschedule();
	}

	/**
	 * @param string $message Message.
	 */
	private function note( $message ) {
		add_settings_error( 'luxe_kia', 'luxe_kia', $message, 'updated' );
	}
}
