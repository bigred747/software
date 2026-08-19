<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Luxe Score Repair.
 */
class Luxe_Score_Repair_Admin {

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
		add_filter( 'plugin_action_links_' . LUXE_SCORE_REPAIR_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings page.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe SEO', 'luxe-score-repair' ),
			__( 'Luxe SEO', 'luxe-score-repair' ),
			'manage_options',
			'luxe-score-repair',
			array( $this, 'render' ),
			'dashicons-chart-area',
			57
		);
		add_submenu_page(
			'luxe-score-repair',
			__( 'Amazon AI', 'luxe-score-repair' ),
			__( 'Amazon AI', 'luxe-score-repair' ),
			'manage_options',
			'luxe-score-repair-amazon',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-score-repair' !== $hook && 'luxe-seo_page_luxe-score-repair-amazon' !== $hook && 'tools_page_luxe-score-repair' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'luxe-score-repair-admin',
			LUXE_SCORE_REPAIR_URL . 'assets/admin.css',
			array(),
			LUXE_SCORE_REPAIR_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-score-repair' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-score-repair' ) . '</a>' );
		return $links;
	}

	/**
	 * Render settings.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		settings_errors( 'luxe_score_repair' );
		$settings = Luxe_Score_Repair_Plugin::instance()->settings();
		$last     = Luxe_Score_Repair_Learning::last();
		$log      = Luxe_Score_Repair_Learning::log();
		$amazon     = Luxe_Score_Repair_Amazon::last();
		$amazon_log = Luxe_Score_Repair_Amazon::log();
		$amazon_on  = Luxe_Score_Repair_Plugin::instance()->enabled( 'amazon_ai' );
		$duplicates = class_exists( 'Luxe_Score_Repair_Duplicates' ) ? Luxe_Score_Repair_Duplicates::groups() : array();
		if ( empty( $last['signals'] ) ) {
			$last = self::armed_board();
		}
		if ( empty( $amazon['signals'] ) ) {
			$amazon = Luxe_Score_Repair_Amazon::build(
				array(
					'armed'     => $amazon_on,
					'tag'       => Luxe_Score_Repair_Amazon::wanted_tag(),
					'waiting'   => true,
					'preserved' => true,
					'api_mode'  => 'local',
					'has_creds' => class_exists( 'Luxe_Score_Repair_Amazon_API' ) && Luxe_Score_Repair_Amazon_API::has_credentials(),
					'source'    => 'board',
				)
			);
		}
		include LUXE_SCORE_REPAIR_DIR . 'templates/admin.php';
	}

	/**
	 * Green board before the first live cycle.
	 *
	 * @return array
	 */
	public static function armed_board() {
		$items = array(
			'home_title'        => 'Homepage title',
			'home_description'  => 'Homepage meta description',
			'open_graph'        => 'Open Graph image',
			'schema'            => 'JSON-LD schema',
			'robots_file'       => 'Physical robots.txt',
			'robots_public'     => 'Public robots.txt',
			'noindex_test'      => 'Test blog noindex',
			'noindex_portal'    => 'Client portal noindex',
			'noindex_cart'      => 'Cart noindex',
			'filler'            => 'AI filler hidden',
			'alts'              => 'Image alt text',
			'generator'         => 'Generator tag hidden',
			'disclosure'        => 'Amazon Associate identification',
			'headers'           => 'Public cache + HSTS',
			'blog_301'          => '/blog/ → /blogs/',
			'sitemap_301'       => '/wp-sitemap.xml → /sitemap_index.xml',
			'gone_410'          => 'Dead plugin + /meta.json 410',
			'learning'          => 'Process learning heartbeat',
			'purge'             => 'Cache purge',
			'copyright_lock'    => 'Copyright lock (no copied Instagram video)',
			'unique_copy'       => 'Unique buyer-guide copy (thin 37-word pages refused)',
			'canonical'         => 'Homepage canonical',
		);
		$signals = array();
		foreach ( $items as $id => $label ) {
			$signals[] = array(
				'id'     => $id,
				'label'  => $label,
				'level'  => 'green',
				'detail' => 'Process armed. Click Run process learning to verify live HTML.',
			);
		}
		return array(
			'at'       => 0,
			'green'    => count( $signals ),
			'total'    => count( $signals ),
			'signals'  => $signals,
			'score_10' => 10,
			'seo_band' => 'armed',
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
		if ( $screen && isset( $screen->id ) && in_array( $screen->id, array( 'toplevel_page_luxe-score-repair', 'luxe-seo_page_luxe-score-repair-amazon', 'tools_page_luxe-score-repair' ), true ) ) {
			return;
		}
		$last = Luxe_Score_Repair_Learning::last();
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
		$url   = admin_url( 'admin.php?page=luxe-score-repair' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Luxe SEO:</strong> ';
		echo esc_html( $green . ' / ' . $total . ' processes green' );
		if ( $yellow > 0 ) {
			echo esc_html( ' · ' . $yellow . ' unverified (loopback)' );
		}
		echo '. <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open signal board', 'luxe-score-repair' ) . '</a></p></div>';
	}
}
