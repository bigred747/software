<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Admin' ) ) {
	return;
}

/**
 * Associates Hub admin screen.
 */
class LAH_Admin {

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
		add_filter( 'plugin_action_links_' . LAH_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Associates Hub', 'luxe-associates-hub' ),
			__( 'Associates Hub', 'luxe-associates-hub' ),
			'manage_options',
			'luxe-associates-hub',
			array( $this, 'render' ),
			'dashicons-admin-links',
			57
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-associates-hub' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'lah-admin',
			LAH_URL . 'assets/associates-admin.css',
			array(),
			LAH_VERSION
		);
		wp_enqueue_script(
			'lah-admin',
			LAH_URL . 'assets/associates-admin.js',
			array(),
			LAH_VERSION,
			true
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-associates-hub' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Associates', 'luxe-score-repair' ) . '</a>' );
		return $links;
	}

	/**
	 * @param string $code Code.
	 * @return string
	 */
	public static function notice( $code ) {
		$messages = array(
			'saved'        => 'Saved. Your Amazon password was not part of this form.',
			'bad-tag'      => 'That tracking ID was not saved. Use the ID from Associates Central, like storename-20.',
			'note-blocked' => 'The reminder was not saved because it looked like an account number or tax ID.',
			'need-tag'     => 'Save your tracking ID first. The link builder will not guess one.',
			'bad-asin'     => 'Paste a 10-character ASIN or an amazon.com/dp/ product URL.',
			'built'        => 'Link built. It was not added to the first-week log.',
			'logged'       => 'Link built and added to your log.',
		);
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin   = LAH_Plugin::instance();
		$settings = $plugin->settings();
		$log      = $plugin->log();
		$last     = $plugin->last_link();
		$week     = LAH_Links::week_status( $settings['started_at'], $log, time() );
		$signals  = LAH_Suite::signals();
		$catalog  = LAH_Suite::catalog( 24 );
		$steps    = LAH_Guide::steps();
		$sections = LAH_Guide::sections();
		$code     = isset( $_GET['lah_notice'] ) ? sanitize_key( wp_unslash( $_GET['lah_notice'] ) ) : '';
		$notice   = self::notice( $code );
		$prefill  = isset( $_GET['lah_asin'] ) ? LAH_Links::asin( wp_unslash( $_GET['lah_asin'] ) ) : '';
		$title_in = isset( $_GET['lah_title'] ) ? sanitize_text_field( wp_unslash( $_GET['lah_title'] ) ) : '';
		if ( strlen( $title_in ) > 180 ) {
			$title_in = substr( $title_in, 0, 180 );
		}
		$bestsellers = LAH_Links::tagged_page( '/gp/bestsellers', $settings['tracking_id'] );
		include LAH_DIR . 'templates/associates-admin.php';
	}
}
