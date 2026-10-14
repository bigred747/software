<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Overlap Remover.
 */
class LUOR_Admin {

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
	 * Register the screen and the two actions.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . LUOR_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_post_luor_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_luor_delete', array( $this, 'handle_delete' ) );
	}

	/**
	 * Tools submenu.
	 */
	public function menu() {
		add_management_page(
			__( 'Overlap Remover', 'luxe-overlap-remover' ),
			__( 'Overlap Remover', 'luxe-overlap-remover' ),
			'delete_plugins',
			'luxe-overlap-remover',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'tools_page_luxe-overlap-remover' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'luor-admin', LUOR_URL . 'assets/admin.css', array(), LUOR_VERSION );
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=luxe-overlap-remover' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-overlap-remover' ) . '</a>' );
		return $links;
	}

	/**
	 * Render the signal board.
	 */
	public function render() {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}
		$report  = LUOR_Plugin::instance()->report();
		$signals = LUOR_Board::build( $report );
		$tally   = LUOR_Board::tally( $signals );
		$notice  = isset( $_GET['luor_notice'] ) ? sanitize_key( wp_unslash( $_GET['luor_notice'] ) ) : '';
		include LUOR_DIR . 'templates/admin.php';
	}

	/**
	 * Scan only.
	 */
	public function handle_scan() {
		$this->guard( 'luor_scan' );
		LUOR_Plugin::instance()->scan();
		$this->back( 'scanned' );
	}

	/**
	 * Delete only after the backup box is checked.
	 */
	public function handle_delete() {
		$this->guard( 'luor_delete' );
		if ( empty( $_POST['luor_confirm_backup'] ) ) {
			$this->back( 'backup-required' );
		}
		$report = LUOR_Plugin::instance()->delete_installed();
		$failed = isset( $report['failed'] ) && is_array( $report['failed'] ) ? $report['failed'] : array();
		$this->back( $failed ? 'delete-partial' : 'deleted' );
	}

	/**
	 * @param string $action Action.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			wp_die( esc_html__( 'You cannot delete plugins.', 'luxe-overlap-remover' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * @param string $notice Notice key.
	 */
	private function back( $notice ) {
		wp_safe_redirect( admin_url( 'tools.php?page=luxe-overlap-remover&luor_notice=' . rawurlencode( $notice ) ) );
		exit;
	}
}
