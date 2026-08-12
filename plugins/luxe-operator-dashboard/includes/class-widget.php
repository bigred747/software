<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single operator widget that explains the live Dashboard numbers.
 */
class Luxe_Operator_Widget {

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
	 * Register widget.
	 */
	public function boot() {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ), 1 );
	}

	/**
	 * Put the operator card at the top of the normal column.
	 */
	public function register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! Luxe_Operator_Dashboard::instance()->enabled( 'show_operator_widget' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'luxe_operator_dashboard',
			'Luxe Operator Dashboard',
			array( $this, 'render' )
		);
		global $wp_meta_boxes;
		if ( empty( $wp_meta_boxes['dashboard']['normal']['core']['luxe_operator_dashboard'] ) ) {
			return;
		}
		$widget = $wp_meta_boxes['dashboard']['normal']['core']['luxe_operator_dashboard'];
		unset( $wp_meta_boxes['dashboard']['normal']['core']['luxe_operator_dashboard'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
			array( 'luxe_operator_dashboard' => $widget ),
			$wp_meta_boxes['dashboard']['normal']['core']
		);
	}

	/**
	 * Dashboard widget markup.
	 */
	public function render() {
		$brief = $this->brief();
		include LUXE_OP_DIR . 'templates/widget.php';
	}

	/**
	 * Read-only status for the widget and Tools page. No writes.
	 *
	 * @return array
	 */
	public function brief() {
		$stay_audit = get_option( 'rbsmc_stay_last_audit', array() );
		$stay_dwell = get_option( 'rbsmc_stay_dwell', array() );
		$stay_red   = ( is_array( $stay_audit ) && isset( $stay_audit['red_count'] ) ) ? absint( $stay_audit['red_count'] ) : null;
		$samples    = ( is_array( $stay_dwell ) && isset( $stay_dwell['samples'] ) ) ? absint( $stay_dwell['samples'] ) : 0;
		$avg        = 0;
		if ( $samples && ! empty( $stay_dwell['seconds_sum'] ) ) {
			$avg = (int) round( absint( $stay_dwell['seconds_sum'] ) / $samples );
		}

		$posts = wp_count_posts( 'post' );
		$pages = wp_count_posts( 'page' );

		return array(
			'hard_rescue'     => $this->plugin_active_fragment( 'luxe-hard-rescue' ),
			'stay_repair'     => $this->plugin_active_fragment( 'richard-brummer-seo-mission-control-stay' ),
			'mission_control' => $this->plugin_active_fragment( 'richard-brummer-seo-mission-control/richard-brummer-seo-mission-control.php' ),
			'reader_love'     => $this->plugin_active_fragment( 'luxe-reader-love' ),
			'keyword_intel'   => $this->plugin_active_fragment( 'luxe-keyword-intelligence' ),
			'stay_red'        => $stay_red,
			'dwell_samples'   => $samples,
			'dwell_avg'       => $avg,
			'published_posts' => isset( $posts->publish ) ? absint( $posts->publish ) : 0,
			'published_pages' => isset( $pages->publish ) ? absint( $pages->publish ) : 0,
			'wp_version'      => get_bloginfo( 'version' ),
			'theme'           => wp_get_theme()->get( 'Name' ),
			'mission_url'     => admin_url( 'admin.php?page=rbsmc' ),
			'stay_url'        => admin_url( 'admin.php?page=rbsmc-stay' ),
			'settings_url'    => admin_url( 'tools.php?page=luxe-operator-dashboard' ),
			'rank_math_url'   => admin_url( 'admin.php?page=rank-math-options-general' ),
			'wordfence_url'   => admin_url( 'admin.php?page=WFLS' ),
		);
	}

	/**
	 * @param string $fragment Plugin folder or basename fragment.
	 * @return bool
	 */
	private function plugin_active_fragment( $fragment ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $active as $file ) {
			if ( false !== strpos( (string) $file, $fragment ) ) {
				return true;
			}
		}
		return false;
	}
}
