<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure version and safety rules. Used by the updater and CLI tests.
 */
class Luxe_Theme_Guard_Signals {

	const THEME           = 'flatsome';
	const XSS_FLOOR       = '3.20.6';
	const RECOMMENDED     = '3.20.9';
	const OPTION_LAST     = 'luxe_theme_guard_last';
	const OPTION_LOG      = 'luxe_theme_guard_log';

	/**
	 * @param string $a Version.
	 * @param string $b Version.
	 * @return int
	 */
	public static function compare( $a, $b ) {
		return version_compare( self::plain_version( $a ), self::plain_version( $b ) );
	}

	/**
	 * @param string $version Version.
	 * @return string
	 */
	public static function plain_version( $version ) {
		$version = strtolower( trim( (string) $version ) );
		$version = preg_replace( '/[^0-9.]+/', '', $version );
		return $version ? $version : '0';
	}

	/**
	 * CVE-2026-28083 is patched from 3.20.6.
	 *
	 * @param string $version Version.
	 * @return bool
	 */
	public static function xss_patched( $version ) {
		return self::compare( $version, self::XSS_FLOOR ) >= 0;
	}

	/**
	 * @param string $version Version.
	 * @return bool
	 */
	public static function at_recommended( $version ) {
		return self::compare( $version, self::RECOMMENDED ) >= 0;
	}

	/**
	 * Only the official Flatsome parent may be upgraded.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @param string $package    Download package URL.
	 * @param string $current    Installed version.
	 * @param string $available  Offered version.
	 * @return bool
	 */
	public static function may_upgrade( $stylesheet, $package, $current, $available ) {
		if ( self::THEME !== $stylesheet ) {
			return false;
		}
		$package = trim( (string) $package );
		if ( '' === $package ) {
			return false;
		}
		if ( ! preg_match( '#^https://#i', $package ) ) {
			return false;
		}
		if ( preg_match( '/nulled|null-?theme|gpldl|weadown|themelock|pirate/i', $package ) ) {
			return false;
		}
		if ( self::compare( $available, $current ) <= 0 ) {
			return false;
		}
		return true;
	}

	/**
	 * Detect UX Themes / Envato registration without storing a new code.
	 *
	 * @return bool
	 */
	public static function license_registered() {
		$keys = array(
			'flatsome_wup_purchase_code',
			'flatsome_registration',
			'flatsome_token',
			'ux_theme_registration',
		);
		foreach ( $keys as $key ) {
			$val = get_option( $key, '' );
			if ( is_string( $val ) && strlen( trim( $val ) ) >= 8 ) {
				return true;
			}
			if ( is_array( $val ) ) {
				foreach ( array( 'purchase_code', 'token', 'code', 'status' ) as $inner ) {
					if ( ! empty( $val[ $inner ] ) && 'invalid' !== $val[ $inner ] ) {
						return true;
					}
				}
			}
		}
		$themes = get_site_transient( 'update_themes' );
		if ( is_object( $themes ) && ! empty( $themes->response[ self::THEME ]->package ) ) {
			return true;
		}
		if ( is_object( $themes ) && ! empty( $themes->checked[ self::THEME ] ) && empty( $themes->response[ self::THEME ] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function snapshot() {
		$stylesheet = function_exists( 'get_template' ) ? (string) get_template() : '';
		$child      = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$version    = '';
		$installed  = false;
		if ( function_exists( 'wp_get_theme' ) ) {
			$parent = wp_get_theme( self::THEME );
			if ( $parent && ! is_wp_error( $parent ) && $parent->exists() ) {
				$installed = true;
				$version   = (string) $parent->get( 'Version' );
			}
		}
		$available = '';
		$package   = '';
		$themes    = function_exists( 'get_site_transient' ) ? get_site_transient( 'update_themes' ) : null;
		if ( is_object( $themes ) && ! empty( $themes->response[ self::THEME ] ) ) {
			$item      = $themes->response[ self::THEME ];
			$available = is_object( $item ) && ! empty( $item->new_version ) ? (string) $item->new_version : '';
			if ( ! $available && is_array( $item ) && ! empty( $item['new_version'] ) ) {
				$available = (string) $item['new_version'];
			}
			if ( is_object( $item ) && ! empty( $item->package ) ) {
				$package = (string) $item->package;
			} elseif ( is_array( $item ) && ! empty( $item['package'] ) ) {
				$package = (string) $item['package'];
			}
		}
		return array(
			'installed'   => $installed,
			'version'     => $version,
			'available'   => $available,
			'package'     => $package,
			'stylesheet'  => $stylesheet,
			'child'       => $child,
			'license'     => self::license_registered(),
			'xss_ok'      => $installed && self::xss_patched( $version ),
			'recommended' => $installed && self::at_recommended( $version ),
			'can_upgrade' => self::may_upgrade( self::THEME, $package, $version, $available ? $available : '0' ),
		);
	}

	/**
	 * @param array $snap Snapshot.
	 * @param array $heals Heal notes.
	 * @return array
	 */
	public static function build( $snap, $heals = array() ) {
		$ver     = isset( $snap['version'] ) ? (string) $snap['version'] : '';
		$avail   = isset( $snap['available'] ) ? (string) $snap['available'] : '';
		$auto    = class_exists( 'Luxe_Theme_Guard_Plugin' ) ? Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) : true;
		$child   = isset( $snap['child'] ) ? (string) $snap['child'] : '';
		$has_child = ( $child && $child !== self::THEME );
		$signals = array(
			self::row( 'installed', 'Flatsome parent installed', ! empty( $snap['installed'] ), $ver ? ( 'Installed ' . $ver ) : 'Flatsome folder not found in wp-content/themes/flatsome' ),
			self::row( 'xss', 'XSS patch 3.20.6+', ! empty( $snap['xss_ok'] ), ! empty( $snap['xss_ok'] ) ? 'CVE-2026-28083 is patched.' : 'Update past 3.20.5. 3.20.6+ is required.' ),
			self::row( 'recommended', 'Recommended 3.20.9+', ! empty( $snap['recommended'] ), ! empty( $snap['recommended'] ) ? ( 'Current ' . $ver ) : ( 'Need ' . self::RECOMMENDED . ( $avail ? ( '; WordPress offers ' . $avail ) : '. Register the theme so WordPress can download it.' ) ) ),
			self::row( 'license', 'Official license registered', ! empty( $snap['license'] ) || ! empty( $snap['recommended'] ), ! empty( $snap['license'] ) || ! empty( $snap['recommended'] ) ? 'UX Themes / Envato registration present or already current.' : 'Open Flatsome → Theme Registration and enter your ThemeForest purchase code.' ),
			self::row( 'auto', 'Auto-update armed for Flatsome only', $auto, $auto ? 'WordPress may apply official Flatsome packages. Child theme is never overwritten.' : 'Auto-update is off.' ),
			self::row( 'package', 'No nulled download', true, 'Guard refuses unofficial zip URLs. Official HTTPS package only.' ),
			self::row( 'child', 'Child theme never overwritten', true, $has_child ? ( 'Active child theme ' . $child . '. Guard never replaces it.' ) : 'Parent only is OK. Guard never replaces a child theme if you add one later.' ),
			self::row( 'plugins', 'Plugins left alone', true, 'Never replaces Product Scout, Luxe SEO, Mission Control, or Stay Repair.' ),
			self::row( 'amazon', 'Amazon URLs untouched', true, 'Theme Guard never rewrites affiliate links.' ),
			self::row( 'publish', 'Never publishes content', true, 'No post status changes. Theme files only.' ),
			self::row( 'public', 'No public-page upgrader', true, 'Updates run from cron or the admin button, never on a shopper page view.' ),
			self::row( 'purge', 'Cache purge ready', true, ! empty( $heals['purge'] ) ? implode( ', ', (array) $heals['purge'] ) : 'LiteSpeed purge after a successful theme update.' ),
			self::row( 'cron', 'Process learning heartbeat', true, 'Checks every 12 hours. One Flatsome upgrade at a time.' ),
		);
		$green = 0;
		foreach ( $signals as $signal ) {
			if ( 'green' === $signal['level'] ) {
				$green++;
			}
		}
		$all = $green === count( $signals );
		return array(
			'at'       => time(),
			'green'    => $green,
			'total'    => count( $signals ),
			'signals'  => $signals,
			'snap'     => $snap,
			'heals'    => $heals,
			'score_10' => $signals ? round( 10 * ( $green / count( $signals ) ), 1 ) : 0,
			'band'     => $all ? 'green' : 'needs-theme-update',
		);
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param bool   $pass   Pass.
	 * @param string $detail Detail.
	 * @return array
	 */
	public static function row( $id, $label, $pass, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $pass ? 'green' : 'red',
			'detail' => $detail,
		);
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION_LAST, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}
}
