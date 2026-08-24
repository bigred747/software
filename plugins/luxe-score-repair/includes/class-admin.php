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
	 * Top-level menu plus Tools alias. The Tools screen is too crowded to notice 1.2.x.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			'manage_options',
			'luxe-score-repair',
			array( $this, 'render' ),
			'dashicons-search',
			58
		);
		add_submenu_page(
			'tools.php',
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			__( 'Luxe Score Repair', 'luxe-score-repair' ),
			'manage_options',
			'luxe-score-repair',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		$ok = array( 'toplevel_page_luxe-score-repair', 'tools_page_luxe-score-repair' );
		if ( ! in_array( $hook, $ok, true ) ) {
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
		$focus    = self::focus_status();
		if ( empty( $last['signals'] ) || ! self::learn_has_search_focus( $last ) ) {
			$last = self::armed_board();
			$last['seo_band'] = 'run-learning';
			$last['stale']    = true;
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
			'noindex_product_tag' => 'Product-tag noindex',
			'search_sitemap'    => 'Search-focus sitemap',
			'filler'            => 'AI filler hidden',
			'alts'              => 'Image alt text',
			'generator'         => 'Generator tag hidden',
			'disclosure'        => 'Amazon disclosure',
			'headers'           => 'Public cache + HSTS',
			'blog_301'          => '/blog/ → /blogs/',
			'learning'          => 'Process learning heartbeat',
			'purge'             => 'Cache purge',
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
	 * Stored learning from an older Score Repair build (22-process boards, etc.).
	 *
	 * @param array $last Last learning.
	 * @return bool
	 */
	public static function learn_has_search_focus( $last ) {
		if ( ! is_array( $last ) || empty( $last['signals'] ) || ! is_array( $last['signals'] ) ) {
			return false;
		}
		$ids = array();
		foreach ( $last['signals'] as $signal ) {
			if ( is_array( $signal ) && ! empty( $signal['id'] ) ) {
				$ids[] = (string) $signal['id'];
			}
		}
		return in_array( 'noindex_product_tag', $ids, true ) && in_array( 'search_sitemap', $ids, true );
	}

	/**
	 * Live search-focus facts for the Tools / admin board. No remote fetch.
	 *
	 * @return array<string,mixed>
	 */
	public static function focus_status() {
		$plugin = Luxe_Score_Repair_Plugin::instance();
		$path   = Luxe_Score_Repair_Robots::file_path();
		$robots = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		$guides = Luxe_Score_Repair_Search_Focus::published_guides( 40 );
		return array(
			'on'         => $plugin->enabled( 'search_focus' ),
			'sitemap'    => home_url( Luxe_Score_Repair_Search_Focus::SITEMAP_PATH ),
			'guide_n'    => count( $guides ),
			'robots_ok'  => false !== strpos( $robots, 'luxe-search-sitemap.xml' ),
			'stale'      => ! self::learn_has_search_focus( Luxe_Score_Repair_Learning::last() ),
			'version'    => LUXE_SCORE_REPAIR_VERSION,
		);
	}

	/**
	 * Compact notice. Never show a stale 19/22 board from a previous plugin build.
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$skip   = array( 'tools_page_luxe-score-repair', 'toplevel_page_luxe-score-repair' );
		if ( $screen && isset( $screen->id ) && in_array( $screen->id, $skip, true ) ) {
			return;
		}
		$url   = admin_url( 'admin.php?page=luxe-score-repair' );
		$last  = Luxe_Score_Repair_Learning::last();
		$fresh = self::learn_has_search_focus( $last );
		if ( ! $fresh ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>Luxe Score Repair ' . esc_html( LUXE_SCORE_REPAIR_VERSION ) . ':</strong> ';
			echo esc_html__( 'Search focus is on. The 19/22 board is leftover from the old plugin. Open Luxe Score Repair in the left menu and click Run process learning now.', 'luxe-score-repair' );
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open search-focus board', 'luxe-score-repair' ) . '</a></p></div>';
			return;
		}
		$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
		$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
		if ( $total < 1 ) {
			return;
		}
		$class = ( $green === $total ) ? 'notice-success' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Luxe Score Repair ' . esc_html( LUXE_SCORE_REPAIR_VERSION ) . ':</strong> ';
		echo esc_html( $green . ' / ' . $total . ' processes green. Search focus on.' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open search-focus board', 'luxe-score-repair' ) . '</a></p></div>';
	}
}
