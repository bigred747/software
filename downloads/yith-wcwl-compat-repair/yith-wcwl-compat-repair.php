<?php
/**
 * Plugin Name: YITH Wishlist Compatibility Repair (One-Time)
 * Description: One-time fix for YITH WooCommerce Wishlist compatibility class (WPML singleton / path / WCML detection). Activate once, confirm the success notice, then delete this plugin.
 * Version: 1.0.0
 * Author: Repair Utility
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Write the repaired compatibility class and store a result for the admin notice.
 */
function yith_wcwl_compat_repair_run() {
	$target = WP_PLUGIN_DIR . '/yith-woocommerce-wishlist/includes/compatibility/class-yith-wcwl-compatibility.php';

	if ( ! file_exists( $target ) ) {
		// Premium / alternate folder names.
		$candidates = glob( WP_PLUGIN_DIR . '/*/includes/compatibility/class-yith-wcwl-compatibility.php' );
		$target     = ( is_array( $candidates ) && ! empty( $candidates[0] ) ) ? $candidates[0] : '';
	}

	if ( empty( $target ) || ! file_exists( $target ) ) {
		update_option(
			'yith_wcwl_compat_repair_result',
			array(
				'ok'      => false,
				'message' => 'Could not find YITH WooCommerce Wishlist compatibility file. Is the plugin installed?',
			),
			false
		);
		return;
	}

	$backup = $target . '.bak-' . gmdate( 'Ymd-His' );
	if ( ! @copy( $target, $backup ) ) {
		update_option(
			'yith_wcwl_compat_repair_result',
			array(
				'ok'      => false,
				'message' => 'Found the file but could not create a backup. Check file permissions.',
			),
			false
		);
		return;
	}

	$repaired = <<<'PHP'
<?php
/**
 * Main compatibility class
 *
 * @author  YITH <plugins@yithemes.com>
 * @package YITH\Wishlist\Classes
 */

defined( 'YITH_WCWL' ) || exit; // Exit if accessed directly.

if ( ! class_exists( 'YITH_WCWL_Compatibility' ) ) {
	/**
	 * Class to initialize compatibility features
	 */
	class YITH_WCWL_Compatibility {
		use YITH_WCWL_Singleton_Trait;

		/**
		 * Integrations object list.
		 *
		 * @var array
		 */
		protected $integrations = array();

		/**
		 * Construct
		 */
		public function __construct() {
			$this->load();
		}

		/**
		 * Get the compatible plugins
		 *
		 * @return array
		 */
		protected function get_plugins() {
			return array(
				'wpml' => 'WPML',
			);
		}

		/**
		 * Load classes for compatible plugins
		 */
		protected function load() {
			foreach ( $this->get_plugins() as $slug => $class_slug ) {
				$filename  = YITH_WCWL_INC . 'compatibility/class-yith-wcwl-' . $slug . '-compatibility.php';
				$classname = 'YITH_WCWL_' . $class_slug . '_Compatibility';
				$var       = str_replace( '-', '_', $slug );

				if ( ! static::has_plugin( $slug ) ) {
					continue;
				}

				if ( file_exists( $filename ) && ! class_exists( $classname ) ) {
					require_once $filename;
				}

				if ( ! class_exists( $classname ) ) {
					continue;
				}

				// Subclasses share this class's singleton $instance; use new so WPML actually boots.
				if ( is_subclass_of( $classname, __CLASS__ ) ) {
					$this->integrations[ $var ] = new $classname();
				} elseif ( method_exists( $classname, 'get_instance' ) ) {
					$this->integrations[ $var ] = $classname::get_instance();
				}
			}
		}

		/**
		 * Check if plugin exists and it's activated.
		 *
		 * @param string $plugin_slug Plugin slug.
		 *
		 * @return bool
		 */
		public static function has_plugin( $plugin_slug ) {
			$has_plugin = false;

			switch ( $plugin_slug ) {
				case 'wpml':
					global $sitepress;

					$has_plugin = ! empty( $sitepress ) || defined( 'WCML_VERSION' );

					break;

				default:
					$has_plugin = false;
			}

			return $has_plugin;
		}
	}
}
PHP;

	$written = @file_put_contents( $target, $repaired );
	if ( false === $written ) {
		update_option(
			'yith_wcwl_compat_repair_result',
			array(
				'ok'      => false,
				'message' => 'Backup created, but could not write the repaired file. Check file permissions on: ' . $target,
			),
			false
		);
		return;
	}

	update_option(
		'yith_wcwl_compat_repair_result',
		array(
			'ok'      => true,
			'message' => 'Compatibility class repaired. Backup saved as ' . basename( $backup ) . '. You can now deactivate and delete this repair plugin.',
			'target'  => $target,
			'backup'  => $backup,
		),
		false
	);
}
register_activation_hook( __FILE__, 'yith_wcwl_compat_repair_run' );

/**
 * Show one-time admin notice with the repair result.
 */
function yith_wcwl_compat_repair_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$result = get_option( 'yith_wcwl_compat_repair_result' );
	if ( empty( $result ) || ! is_array( $result ) ) {
		return;
	}

	$class = ! empty( $result['ok'] ) ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
	$msg   = isset( $result['message'] ) ? $result['message'] : 'Repair finished.';

	printf( '<div class="%1$s"><p><strong>YITH Wishlist Compatibility Repair:</strong> %2$s</p></div>', esc_attr( $class ), esc_html( $msg ) );

	// Clear after showing once on a successful repair so it doesn't linger forever.
	if ( ! empty( $result['ok'] ) ) {
		delete_option( 'yith_wcwl_compat_repair_result' );
	}
}
add_action( 'admin_notices', 'yith_wcwl_compat_repair_admin_notice' );

/**
 * Clean up option on uninstall.
 */
function yith_wcwl_compat_repair_uninstall() {
	delete_option( 'yith_wcwl_compat_repair_result' );
}
register_uninstall_hook( __FILE__, 'yith_wcwl_compat_repair_uninstall' );
