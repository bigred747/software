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
	 * Scan only. Delete stays off after the live site returned HTTP 500.
	 *
	 * @return array<string,mixed>
	 */
	public function delete_installed() {
		$report = $this->scan();
		$report['failed']  = array();
		$report['scanned'] = true;
		update_option( self::REPORT, $report, false );
		return $report;
	}
}
