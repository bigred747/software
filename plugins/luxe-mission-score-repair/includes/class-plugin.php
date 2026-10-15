<?php
/**
 * Runtime hooks. Never publishes, never changes post status, never rewrites Amazon retail URLs.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Luxe_MSR_Plugin {

	const OPTION = 'luxe_msr_state';
	const CRON   = 'luxe_msr_template_sync';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $homepage = false;

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
	 * Register hooks.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_homepage_assets' ), 9999 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 99 );
		add_action( self::CRON, array( $this, 'sync_templates' ) );
		add_action( 'admin_init', array( $this, 'maybe_manual_sync' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Schedule the template sync and run it once.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'daily', self::CRON );
		}
		self::instance()->sync_templates();
		self::purge_caches();
	}

	/**
	 * Clear the sync cron. Leave repaired templates in place.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Drop homepage assets that are not used by the storefront.
	 */
	public function dequeue_homepage_assets() {
		if ( is_admin() || ! function_exists( 'is_front_page' ) || ! is_front_page() ) {
			return;
		}
		$scripts = array(
			'wflsi18njs',
			'wordfence-ls-login',
			'thickbox',
			'contact-form-7',
			'swv',
		);
		foreach ( $scripts as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
		$styles = array(
			'wp-block-library',
			'wp-block-library-theme',
			'classic-theme-styles',
			'global-styles',
			'dashicons',
			'contact-form-7',
		);
		foreach ( $styles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}

	/**
	 * Buffer after cache plugins so the stored HTML is the repaired HTML.
	 */
	public function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$this->homepage = function_exists( 'is_front_page' ) && is_front_page();
		ob_start( array( $this, 'filter_html' ) );
	}

	/**
	 * @param string $html Response body.
	 * @return string
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		return Luxe_MSR_Repair::repair_document( $html, $this->homepage );
	}

	/**
	 * Admin button.
	 */
	public function maybe_manual_sync() {
		if ( empty( $_POST['luxe_msr_sync'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_msr_sync' );
		$result = $this->sync_templates();
		self::purge_caches();
		set_transient( 'luxe_msr_notice', $result, 60 );
	}

	/**
	 * Tools menu.
	 */
	public function menu() {
		add_management_page(
			__( 'Luxe Mission Score Repair', 'luxe-mission-score-repair' ),
			__( 'Luxe Mission Score', 'luxe-mission-score-repair' ),
			'manage_options',
			'luxe-mission-score-repair',
			array( $this, 'render' )
		);
	}

	/**
	 * Settings-error style notice after a manual sync.
	 */
	public function admin_notices() {
		$notice = get_transient( 'luxe_msr_notice' );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'luxe_msr_notice' );
		echo '<div class="notice notice-success"><p>' . esc_html( self::summary_text( $notice ) ) . '</p></div>';
	}

	/**
	 * Admin screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = get_option( self::OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		echo '<div class="wrap">';
		echo '<h1>Luxe Mission Score Repair</h1>';
		echo '<p>Repairs the three scored gaps Mission Control can prove from files and rendered HTML. It does not publish, does not change post status, and does not rewrite Amazon retail URLs.</p>';
		echo '<ul>';
		echo '<li>Share buttons lose embedded Amazon image URLs. Real amazon.com and amzn.to links stay as they are.</li>';
		echo '<li>Product-gallery links to Amazon image CDN files stop being anchors. Zoom still uses the image <code>data-large_image</code>.</li>';
		echo '<li>The five WooCommerce 11.2 template behaviors are merged into the theme override, and <code>@version 11.2.0</code> is written only after that behavior is in the file.</li>';
		echo '<li>The homepage drops the Wordfence login-screen translation catalog, Thickbox, Contact Form 7, and block-library CSS that the homepage markup does not use.</li>';
		echo '</ul>';
		if ( ! empty( $state['files'] ) && is_array( $state['files'] ) ) {
			echo '<h2>Last template sync</h2><ul>';
			foreach ( $state['files'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				echo '<li>' . esc_html( ( isset( $row['file'] ) ? $row['file'] : '' ) . ' — ' . ( isset( $row['result'] ) ? $row['result'] : '' ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'luxe_msr_sync' );
		echo '<p><button class="button button-primary" name="luxe_msr_sync" value="1">Sync WooCommerce 11.2 templates and purge cache</button></p>';
		echo '</form>';
		echo '<p>After the cache purge, load the homepage once, then run the Mission Control audit. The dashboard worker goes green when the verified red and yellow checks it summarizes are gone.</p>';
		echo '</div>';
	}

	/**
	 * Merge the five WooCommerce 11.2 template updates into existing theme overrides.
	 *
	 * @return array
	 */
	public function sync_templates() {
		$rows = array();
		foreach ( self::theme_woocommerce_dirs() as $dir ) {
			foreach ( Luxe_MSR_Repair::template_files() as $relative ) {
				$rows[] = $this->sync_one( $dir, $relative );
			}
		}
		$state = array(
			'at'    => time(),
			'files' => $rows,
		);
		update_option( self::OPTION, $state, false );
		return $state;
	}

	/**
	 * @param string $dir Theme woocommerce directory.
	 * @param string $relative Template path.
	 * @return array{file:string,result:string}
	 */
	private function sync_one( $dir, $relative ) {
		$relative = str_replace( '\\', '/', $relative );
		$path     = wp_normalize_path( trailingslashit( $dir ) . $relative );
		$label    = $path;
		if ( ! file_exists( $path ) ) {
			return array(
				'file'   => $label,
				'result' => 'absent',
			);
		}
		$real_theme = realpath( dirname( $dir ) );
		$real_file  = realpath( $path );
		if ( ! $real_theme || ! $real_file || 0 !== strpos( $real_file, $real_theme ) ) {
			return array(
				'file'   => $label,
				'result' => 'skipped-path',
			);
		}
		$core_version = $this->core_template_version( $relative );
		if ( Luxe_MSR_Repair::MERGED_VERSION !== $core_version ) {
			return array(
				'file'   => $label,
				'result' => 'skipped (installed WooCommerce template is ' . ( $core_version ? $core_version : 'unreadable' ) . ', and this merge is for 11.2.0)',
			);
		}
		$code = file_get_contents( $path );
		if ( ! is_string( $code ) ) {
			return array(
				'file'   => $label,
				'result' => 'unreadable',
			);
		}
		$patched = Luxe_MSR_Repair::patch_template( $relative, $code, $core_version );
		if ( empty( $patched['ready'] ) ) {
			return array(
				'file'   => $label,
				'result' => 'left-unchanged (11.2 behavior was not present and could not be merged safely)',
			);
		}
		if ( empty( $patched['changed'] ) ) {
			return array(
				'file'   => $label,
				'result' => 'already current',
			);
		}
		if ( ! is_writable( $path ) ) {
			return array(
				'file'   => $label,
				'result' => 'not writable',
			);
		}
		$this->backup( $real_file, $code );
		$written = file_put_contents( $path, $patched['code'] );
		if ( false === $written ) {
			return array(
				'file'   => $label,
				'result' => 'write failed',
			);
		}
		return array(
			'file'   => $label,
			'result' => 'merged WooCommerce ' . $core_version . ' behavior',
		);
	}

	/**
	 * @param string $relative Template path.
	 * @return string
	 */
	private function core_template_version( $relative ) {
		$base = '';
		if ( defined( 'WC_PLUGIN_FILE' ) ) {
			$base = dirname( WC_PLUGIN_FILE ) . '/templates/' . $relative;
		} elseif ( defined( 'WP_PLUGIN_DIR' ) ) {
			$base = WP_PLUGIN_DIR . '/woocommerce/templates/' . $relative;
		}
		if ( ! $base || ! is_readable( $base ) ) {
			return '';
		}
		$head = file_get_contents( $base, false, null, 0, 800 );
		if ( ! is_string( $head ) || ! preg_match( '/@version\s+([0-9.]+)/', $head, $m ) ) {
			return '';
		}
		return $m[1];
	}

	/**
	 * @return string[]
	 */
	private static function theme_woocommerce_dirs() {
		$dirs = array();
		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$dirs[] = wp_normalize_path( get_stylesheet_directory() . '/woocommerce' );
		}
		if ( function_exists( 'get_template_directory' ) ) {
			$dirs[] = wp_normalize_path( get_template_directory() . '/woocommerce' );
		}
		return array_values( array_unique( $dirs ) );
	}

	/**
	 * @param string $path Original file.
	 * @param string $code Original contents.
	 */
	private function backup( $path, $code ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return;
		}
		$dir = wp_normalize_path( $uploads['basedir'] . '/luxe-mission-score-repair' );
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		$name = gmdate( 'Ymd-His' ) . '-' . substr( md5( $path ), 0, 8 ) . '-' . basename( $path );
		file_put_contents( $dir . '/' . $name . '.bak', $code );
	}

	/**
	 * LiteSpeed full-page cache plus the object cache.
	 */
	public static function purge_caches() {
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * @param array $state Sync state.
	 * @return string
	 */
	public static function summary_text( $state ) {
		$merged = 0;
		$total  = 0;
		if ( ! empty( $state['files'] ) && is_array( $state['files'] ) ) {
			foreach ( $state['files'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['result'] ) || 'absent' === $row['result'] ) {
					continue;
				}
				$total++;
				if ( 0 === strpos( $row['result'], 'merged' ) || 'already current' === $row['result'] ) {
					$merged++;
				}
			}
		}
		return sprintf(
			'Template sync finished. %1$d of %2$d present overrides are at the WooCommerce 11.2 behavior. Cache purge requested.',
			$merged,
			$total
		);
	}
}
