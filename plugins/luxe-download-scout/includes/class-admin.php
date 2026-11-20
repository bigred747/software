<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LDS_Admin' ) ) {
	return;
}

/**
 * Paste a WZone list. Read the catalog. Show what is safe to download.
 */
class LDS_Admin {

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
		add_filter( 'plugin_action_links_' . LDS_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Download Scout', 'luxe-download-scout' ),
			__( 'Luxe Download Scout', 'luxe-download-scout' ),
			'manage_options',
			'luxe-download-scout',
			array( $this, 'render' ),
			'dashicons-download',
			57
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-download-scout' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'lds-admin',
			LDS_URL . 'assets/admin.css',
			array(),
			LDS_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-download-scout' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-download-scout' ) . '</a>' );
		return $links;
	}

	/**
	 * Product names already stored. Read-only.
	 *
	 * @return string[]
	 */
	public static function catalog_titles() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$titles = array();
		$page   = 1;
		while ( $page <= 20 ) {
			$products = wc_get_products(
				array(
					'status'                 => array( 'publish', 'draft', 'pending', 'private' ),
					'limit'                  => 100,
					'page'                   => $page,
					'return'                 => 'objects',
					'lds_include_candidates' => true,
				)
			);
			if ( empty( $products ) || ! is_array( $products ) ) {
				break;
			}
			foreach ( $products as $product ) {
				if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
					$titles[] = (string) $product->get_name();
				}
			}
			if ( count( $products ) < 100 ) {
				break;
			}
			$page++;
		}
		return $titles;
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$catalog_ready = function_exists( 'lds_require_catalog' ) ? lds_require_catalog() : class_exists( 'LAPS_Catalog' );
		$paste         = '';
		$extra         = '';
		$result        = null;
		$notice        = '';
		$best          = array();
		$placed        = null;
		if ( isset( $_POST['lds_pick'] ) || isset( $_POST['lds_place'] ) ) {
			check_admin_referer( 'lds_pick' );
			$paste = isset( $_POST['lds_paste'] ) ? wp_unslash( $_POST['lds_paste'] ) : '';
			$extra = isset( $_POST['lds_catalog'] ) ? wp_unslash( $_POST['lds_catalog'] ) : '';
			$paste = is_string( $paste ) ? $paste : '';
			$extra = is_string( $extra ) ? $extra : '';
			if ( strlen( $paste ) > 200000 ) {
				$paste   = substr( $paste, 0, 200000 );
				$notice  = __( 'The paste was trimmed to 200,000 characters.', 'luxe-download-scout' );
			}
			update_option( 'lds_last_paste', $paste, false );
			update_option( 'lds_last_catalog', $extra, false );
			$titles = self::catalog_titles();
			foreach ( preg_split( '/\r\n|\r|\n/', $extra ) as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line ) {
					$titles[] = $line;
				}
			}
			if ( $catalog_ready && class_exists( 'LDS_Picker' ) ) {
				$result = LDS_Picker::evaluate( $paste, $titles );
			}
			if ( is_array( $result ) && class_exists( 'LDS_Placer' ) ) {
				$best = LDS_Placer::very_best( $result['downloads'] );
			}
			if ( isset( $_POST['lds_place'] ) && class_exists( 'LDS_Placer' ) ) {
				$placed = LDS_Placer::place( $best );
				$titles = self::catalog_titles();
				foreach ( preg_split( '/\r\n|\r|\n/', $extra ) as $line ) {
					$line = trim( (string) $line );
					if ( '' !== $line ) {
						$titles[] = $line;
					}
				}
				if ( $catalog_ready && class_exists( 'LDS_Picker' ) ) {
					$result = LDS_Picker::evaluate( $paste, $titles );
					$best   = is_array( $result ) ? LDS_Placer::very_best( $result['downloads'] ) : array();
				}
			}
		} else {
			$stored_paste = get_option( 'lds_last_paste', '' );
			$stored_extra = get_option( 'lds_last_catalog', '' );
			$paste        = is_string( $stored_paste ) ? $stored_paste : '';
			$extra        = is_string( $stored_extra ) ? $stored_extra : '';
		}
		$live_count = count( self::catalog_titles() );
		include LDS_DIR . 'templates/admin.php';
	}
}
