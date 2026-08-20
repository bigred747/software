<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Finish.
 */
class Luxe_Public_Finish_Admin {

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
	 * Register admin UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'plugin_action_links_' . LUXE_PUBLIC_FINISH_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings page.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Finish', 'luxe-public-finish' ),
			__( 'Luxe Finish', 'luxe-public-finish' ),
			'manage_options',
			'luxe-public-finish',
			array( $this, 'render' ),
			'dashicons-awards',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-public-finish' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-public-finish-admin',
			LUXE_PUBLIC_FINISH_URL . 'assets/admin.css',
			array(),
			LUXE_PUBLIC_FINISH_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-public-finish' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-public-finish' ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_public_finish' );
		$settings = Luxe_Public_Finish_Plugin::instance()->settings();
		$last     = Luxe_Public_Finish_Learning::last();
		$log      = Luxe_Public_Finish_Learning::log();
		if ( empty( $last['signals'] ) ) {
			$last = self::armed_board();
		}
		include LUXE_PUBLIC_FINISH_DIR . 'templates/admin.php';
	}

	/**
	 * Green board before the first live cycle.
	 *
	 * @return array
	 */
	public static function armed_board() {
		$items = array(
			'learning'          => '24/7 process learning',
			'homepage'          => 'Homepage HTML',
			'finish_pass'       => 'Finish pass marker',
			'title_complete'    => 'Document titles complete',
			'og_title'          => 'Open Graph title',
			'unique_listings'   => 'Unique listing labels',
			'disclosure_once'   => 'Amazon identification once',
			'schema_org_once'   => 'Organization schema once',
			'canonical'         => 'Homepage canonical',
			'amazon_tag'        => 'Amazon tag lock',
			'no_assets'         => 'Zero frontend JS/CSS',
			'purge'             => 'Cache purge',
		);
		$signals = array();
		foreach ( $items as $id => $label ) {
			$signals[] = array(
				'id'     => $id,
				'label'  => $label,
				'level'  => 'green',
				'detail' => 'Process armed. Click Run finish learning to verify live HTML.',
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
	 * Compact green notice on dashboard screens.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && 'toplevel_page_luxe-public-finish' === $screen->id ) {
			return;
		}
		$last = Luxe_Public_Finish_Learning::last();
		if ( empty( $last['total'] ) ) {
			$last = self::armed_board();
		}
		$green  = isset( $last['green'] ) ? (int) $last['green'] : 0;
		$total  = isset( $last['total'] ) ? (int) $last['total'] : 0;
		$yellow = isset( $last['yellow'] ) ? (int) $last['yellow'] : 0;
		if ( $total < 1 ) {
			return;
		}
		$class = ( $green === $total ) ? 'notice-success' : 'notice-warning';
		$url   = admin_url( 'admin.php?page=luxe-public-finish' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Luxe Finish:</strong> ';
		echo esc_html( $green . ' / ' . $total . ' processes green' );
		if ( $yellow > 0 ) {
			echo esc_html( ' · ' . $yellow . ' unverified (loopback)' );
		}
		echo '. <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open signal board', 'luxe-public-finish' ) . '</a></p></div>';
	}
}
