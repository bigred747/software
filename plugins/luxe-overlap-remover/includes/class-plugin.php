<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the 18 folders and deletes only those. No public hooks.
 */
class LUOR_Plugin {

	const REPORT = 'luor_report';

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
	 * Store an empty board. Do not scan and do not delete.
	 */
	public static function activate() {
		if ( ! get_option( self::REPORT ) ) {
			add_option( self::REPORT, self::empty_report(), '', false );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function empty_report() {
		return array(
			'scanned' => false,
			'present' => array(),
			'deleted' => array(),
			'failed'  => array(),
		);
	}

	/**
	 * Admin only.
	 */
	public function boot() {
		if ( is_admin() ) {
			LUOR_Admin::instance()->boot();
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function report() {
		$report = get_option( self::REPORT, array() );
		if ( ! is_array( $report ) ) {
			$report = self::empty_report();
		}
		return array_merge( self::empty_report(), $report );
	}

	/**
	 * @return array<string,string> folder => basename
	 */
	public function find_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$found = array();
		foreach ( get_plugins() as $basename => $data ) {
			unset( $data );
			if ( 'delete' !== LUOR_Targets::decision( $basename ) ) {
				continue;
			}
			$folder = LUOR_Targets::normalize( LUOR_Targets::folder_of( $basename ) );
			if ( isset( $found[ $folder ] ) ) {
				continue;
			}
			$found[ $folder ] = (string) $basename;
		}
		return $found;
	}

	/**
	 * Record which of the 18 folders are installed. Does not delete.
	 *
	 * @return array<string,mixed>
	 */
	public function scan() {
		$report  = $this->report();
		$present = $this->find_installed();
		$deleted = isset( $report['deleted'] ) && is_array( $report['deleted'] ) ? $report['deleted'] : array();
		foreach ( array_keys( $present ) as $folder ) {
			unset( $deleted[ $folder ] );
		}
		$report['scanned'] = true;
		$report['present'] = $present;
		$report['deleted'] = $deleted;
		$report['failed']  = array();
		update_option( self::REPORT, $report, false );
		return $report;
	}

	/**
	 * Deactivate and delete the installed matches. The list is read again from disk.
	 *
	 * @return array<string,mixed>
	 */
	public function delete_installed() {
		$report  = $this->scan();
		$present = $report['present'];
		$deleted = isset( $report['deleted'] ) && is_array( $report['deleted'] ) ? $report['deleted'] : array();
		$failed  = array();
		if ( ! $present ) {
			$report['deleted'] = $deleted;
			$report['failed']  = $failed;
			update_option( self::REPORT, $report, false );
			return $report;
		}
		$this->load_admin_includes();
		foreach ( $present as $folder => $basename ) {
			$result = $this->delete_one( $basename );
			if ( true === $result ) {
				$deleted[ $folder ] = 'Deactivated and deleted.';
				unset( $present[ $folder ] );
				continue;
			}
			$failed[ $folder ] = $result;
		}
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		$report['present'] = $this->find_installed();
		$report['deleted'] = $deleted;
		$report['failed']  = $failed;
		$report['scanned'] = true;
		update_option( self::REPORT, $report, false );
		return $report;
	}

	/**
	 * @param string $basename Plugin basename.
	 * @return true|string
	 */
	private function delete_one( $basename ) {
		if ( 'delete' !== LUOR_Targets::decision( $basename ) ) {
			return 'Skipped. This file is not one of the 18 folders.';
		}
		$folder = LUOR_Targets::folder_of( $basename );
		if ( ! LUOR_Targets::is_inside_plugins( WP_PLUGIN_DIR, $folder ) ) {
			return 'Skipped. The folder is not inside the plugins directory.';
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! WP_Filesystem() ) {
			return 'WordPress could not open the plugins directory.';
		}
		try {
			deactivate_plugins( $basename, false );
			if ( is_plugin_active( $basename ) ) {
				return 'Deactivation failed, so the folder was left in place.';
			}
			$result = delete_plugins( array( $basename ) );
		} catch ( Exception $e ) {
			return $e->getMessage();
		} catch ( Throwable $e ) {
			return $e->getMessage();
		}
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( true !== $result ) {
			return 'Delete did not finish.';
		}
		if ( LUOR_Targets::is_inside_plugins( WP_PLUGIN_DIR, $folder ) ) {
			return 'The folder is still on disk.';
		}
		return true;
	}

	/**
	 * Load the WordPress delete API.
	 */
	private function load_admin_includes() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
}
