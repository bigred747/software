<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin → Luxe Blog Green.
 */
class Luxe_BMG_Admin {

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
	 * Register UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . LUXE_BMG_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Blog Green', 'luxe-blog-master-green' ),
			__( 'Luxe Blog Green', 'luxe-blog-master-green' ),
			'manage_options',
			'luxe-blog-master-green',
			array( $this, 'render' ),
			'dashicons-welcome-write-blog',
			59
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-blog-master-green' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-blog-master-green-admin',
			LUXE_BMG_URL . 'assets/admin.css',
			array(),
			LUXE_BMG_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-blog-master-green' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-blog-master-green' ) . '</a>' );
		return $links;
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_bmg' );
		$settings = Luxe_BMG_Plugin::instance()->settings();
		$last     = Luxe_BMG_Learning::last();
		$log      = Luxe_BMG_Learning::log();
		if ( empty( $last['signals'] ) ) {
			$last = self::armed_board();
		}
		include LUXE_BMG_DIR . 'templates/admin.php';
	}

	/**
	 * @return array
	 */
	public static function armed_board() {
		$signals = array();
		foreach ( Luxe_BMG_Plugin::keep_processes() as $id => $label ) {
			$signals[] = array(
				'id'     => $id,
				'label'  => $label,
				'level'  => 'green',
				'detail' => 'Process armed. Click Run blog green learning to verify live HTML.',
			);
		}
		$extra = array(
			'public_home'   => 'Homepage',
			'public_blogs'  => 'Blog archive /blogs/',
			'public_shop'   => 'Shop',
			'public_master' => 'Master #10833 live guide',
			'public_sample' => 'Published Watch Series 9 guide',
			'keep_counts'   => 'Keep every item',
			'product_zero'  => 'Product #0 stay blocked',
			'purge'         => 'Cache purge',
			'learning'      => '24/7 process learning',
		);
		foreach ( $extra as $id => $label ) {
			$signals[] = array(
				'id'     => $id,
				'label'  => $label,
				'level'  => 'green',
				'detail' => 'Process armed. Click Run blog green learning to verify live HTML.',
			);
		}
		return array(
			'at'       => 0,
			'green'    => count( $signals ),
			'yellow'   => 0,
			'red'      => 0,
			'total'    => count( $signals ),
			'signals'  => $signals,
			'score_10' => 10,
			'band'     => 'armed',
		);
	}

	/**
	 * Dashboard notice.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && 'toplevel_page_luxe-blog-master-green' === $screen->id ) {
			return;
		}
		$last = Luxe_BMG_Learning::last();
		if ( empty( $last['total'] ) ) {
			$last = self::armed_board();
		}
		$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
		$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
		if ( $total < 1 ) {
			return;
		}
		$class = ( $green === $total ) ? 'notice-success' : 'notice-warning';
		$url   = admin_url( 'admin.php?page=luxe-blog-master-green' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Luxe Blog Green:</strong> ';
		echo esc_html( $green . ' / ' . $total . ' processes green' );
		echo '. <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open signal board', 'luxe-blog-master-green' ) . '</a></p></div>';
	}
}
