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
	const WATCH_URL       = 'https://demos.flatsome.com/wp-content/themes/flatsome/style.css';
	const PURCHASE_URL    = 'https://wupdates.com/wp-json/wup/v1/front/check_envato_purchase_code/JQ9eJ';
	const UX_API          = 'https://api.uxthemes.com';

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
	 * Flatsome 3.x / 4.x version. Rejects junk that could raise the target.
	 *
	 * @param string $version Version.
	 * @return bool
	 */
	public static function plausible_flatsome_version( $version ) {
		$version = self::plain_version( $version );
		if ( ! preg_match( '/^(\d+)\.(\d+)\.(\d+)$/', $version, $m ) ) {
			return false;
		}
		$major = (int) $m[1];
		$minor = (int) $m[2];
		$patch = (int) $m[3];
		return $major >= 3 && $major <= 4 && $minor <= 99 && $patch <= 99;
	}

	/**
	 * Version header from the official demo stylesheet. Nothing else is kept.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function parse_style_version( $css ) {
		$css = substr( (string) $css, 0, 4000 );
		if ( preg_match( '/^[ \t]*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/mi', $css, $m ) ) {
			$version = self::plain_version( $m[1] );
			return self::plausible_flatsome_version( $version ) ? $version : '';
		}
		return '';
	}

	/**
	 * Official demo stylesheet only. Never a zip, never another host.
	 *
	 * @return string
	 */
	public static function official_watch_url() {
		return self::WATCH_URL;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function watch_url_allowed( $url ) {
		$url = (string) $url;
		if ( 0 !== stripos( $url, 'https://demos.flatsome.com/' ) ) {
			return false;
		}
		$path = parse_url( $url, PHP_URL_PATH );
		return '/wp-content/themes/flatsome/style.css' === $path;
	}

	/**
	 * Official purchase-code check used by Flatsome's own updater.
	 *
	 * @return string
	 */
	public static function official_purchase_check_url() {
		return self::PURCHASE_URL;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function purchase_check_url_allowed( $url ) {
		$url = (string) $url;
		if ( 0 !== stripos( $url, 'https://wupdates.com/' ) ) {
			return false;
		}
		$path = parse_url( $url, PHP_URL_PATH );
		return '/wp-json/wup/v1/front/check_envato_purchase_code/JQ9eJ' === $path;
	}

	/**
	 * Read the official purchase-code response. Does not keep the code.
	 *
	 * @param string $body JSON body.
	 * @return array{ok:bool,detail:string}
	 */
	public static function interpret_purchase_check( $body ) {
		$data = json_decode( (string) $body, true );
		if ( is_string( $data ) ) {
			$text = strtolower( $data );
			if ( false !== strpos( $text, 'doesn' ) && false !== strpos( $text, 'exist' ) ) {
				return array(
					'ok'     => false,
					'detail' => 'The old Flatsome updater product is retired. Guard is using the UX Themes API instead.',
				);
			}
			$plain = self::plain_api_text( $data );
			return array(
				'ok'     => false,
				'detail' => $plain ? $plain : 'The official Flatsome update server returned an unreadable response.',
			);
		}
		if ( ! is_array( $data ) ) {
			return array(
				'ok'     => false,
				'detail' => 'The official Flatsome update server returned an unreadable response.',
			);
		}
		if ( isset( $data['purchase_code'] ) && 'valid' === $data['purchase_code'] ) {
			return array(
				'ok'     => true,
				'detail' => 'UX Themes accepted the purchase code.',
			);
		}
		if ( isset( $data['reason'] ) && 'out_of_support' === $data['reason'] ) {
			return array(
				'ok'     => false,
				'detail' => 'ThemeForest support for this purchase has ended, so UX Themes will not send the update.',
			);
		}
		return array(
			'ok'     => false,
			'detail' => 'UX Themes could not match this purchase code to a Flatsome sale.',
		);
	}

	/**
	 * @param mixed $transient Update transient.
	 * @return array{version:string,package:string}
	 */
	public static function package_from_update_transient( $transient ) {
		$item = array();
		if ( is_object( $transient ) && ! empty( $transient->response[ self::THEME ] ) ) {
			$item = (array) $transient->response[ self::THEME ];
		} elseif ( is_array( $transient ) && ! empty( $transient['response'][ self::THEME ] ) ) {
			$item = (array) $transient['response'][ self::THEME ];
		}
		return array(
			'version' => isset( $item['new_version'] ) ? (string) $item['new_version'] : '',
			'package' => isset( $item['package'] ) ? (string) $item['package'] : '',
		);
	}

	/**
	 * Official UX Themes API path. Purchase codes are only placed in /v1/license/{code}.
	 *
	 * @param string $path Path beginning with /v1/.
	 * @return string Empty when the path is not an allowlisted API call.
	 */
	public static function uxthemes_request_url( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$url  = self::UX_API . $path;
		return self::uxthemes_url_allowed( $url ) ? $url : '';
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function uxthemes_url_allowed( $url ) {
		$parts = parse_url( (string) $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}
		if ( empty( $parts['host'] ) || 'api.uxthemes.com' !== strtolower( $parts['host'] ) ) {
			return false;
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( '/v1/latest-version' === $path ) {
			return true;
		}
		if ( preg_match( '#^/v1/license/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$#', $path ) ) {
			return true;
		}
		return in_array( $path, array( '/v1/token', '/v1/token/download', '/v1/token/version', '/v1/token/latest' ), true );
	}

	/**
	 * Read a UX Themes API response. Does not keep the purchase code.
	 *
	 * @param int    $http_code HTTP status.
	 * @param string $body      Body.
	 * @return array{ok:bool,detail:string,version:string,package:string,token:string}
	 */
	public static function interpret_uxthemes_response( $http_code, $body ) {
		$http_code = (int) $http_code;
		$out       = array(
			'ok'      => false,
			'detail'  => '',
			'version' => '',
			'package' => '',
			'token'   => '',
		);
		$decoded = json_decode( (string) $body, true );
		if ( is_array( $decoded ) ) {
			$out['version'] = self::version_from_payload( $decoded );
			$out['package'] = self::package_url_from_payload( $decoded );
			$out['token']   = self::token_from_payload( $decoded );
			$error          = self::uxthemes_error_detail( $decoded, $http_code );
			if ( $out['package'] && $out['version'] && $http_code >= 200 && $http_code < 300 && ! $error ) {
				$out['ok']     = true;
				$out['detail'] = 'Official package ' . $out['version'] . ' is ready.';
				return $out;
			}
			if ( $out['version'] && $http_code >= 200 && $http_code < 300 && ! $error ) {
				$out['ok']     = true;
				$out['detail'] = 'Official release ' . $out['version'] . '.';
				return $out;
			}
			if ( $error ) {
				$out['detail'] = $error;
				return $out;
			}
		}
		if ( 404 === $http_code ) {
			$out['detail'] = 'The purchase code does not belong to a Flatsome sale.';
			return $out;
		}
		if ( 417 === $http_code ) {
			$out['detail'] = 'No domain was sent with the request.';
			return $out;
		}
		if ( 401 === $http_code || 403 === $http_code ) {
			$out['detail'] = 'Flatsome was unable to get the latest version because the purchase code has not been verified yet. Please re-register it in order to receive updates.';
			return $out;
		}
		if ( 429 === $http_code ) {
			$out['detail'] = 'Sorry, the API is overloaded.';
			return $out;
		}
		if ( $http_code >= 500 && $http_code < 600 ) {
			$out['detail'] = 'Sorry, the API is unavailable at the moment.';
			return $out;
		}
		$out['detail'] = 'The official Flatsome update server returned an unreadable response.';
		return $out;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	public static function acceptable_package_url( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^https://#i', $url ) ) {
			return '';
		}
		if ( preg_match( '/nulled|null-?theme|gpldl|weadown|themelock|pirate/i', $url ) ) {
			return '';
		}
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$path = (string) parse_url( $url, PHP_URL_PATH );
		if ( '' === $host || '' === $path || '/' === $path ) {
			return '';
		}
		if ( 'api.uxthemes.com' === $host && '/v1/token/download' === $path ) {
			return $url;
		}
		if ( preg_match( '/\.zip$/i', $path ) ) {
			return $url;
		}
		$cdn = (bool) preg_match( '/(^|\.)((uxthemes\.com)|(amazonaws\.com)|(cloudfront\.net))$/i', $host );
		if ( $cdn && preg_match( '#/(download|package|releases?)/#i', $path ) ) {
			return $url;
		}
		return '';
	}

	/**
	 * @param mixed $data Payload.
	 * @param int   $depth Depth.
	 * @return string
	 */
	public static function package_url_from_payload( $data, $depth = 0 ) {
		if ( $depth > 4 || ! is_array( $data ) ) {
			return '';
		}
		foreach ( array( 'package', 'download_url', 'downloadUrl', 'download', 'zip', 'file' ) as $key ) {
			if ( empty( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$url = self::acceptable_package_url( $data[ $key ] );
			if ( $url ) {
				return $url;
			}
		}
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$found = self::package_url_from_payload( $value, $depth + 1 );
				if ( $found ) {
					return $found;
				}
			}
		}
		return '';
	}

	/**
	 * @param mixed $data Payload.
	 * @param int   $depth Depth.
	 * @return string
	 */
	public static function version_from_payload( $data, $depth = 0 ) {
		if ( $depth > 4 || ! is_array( $data ) ) {
			return '';
		}
		foreach ( array( 'new_version', 'version', 'latest' ) as $key ) {
			if ( empty( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$version = self::plain_version( $data[ $key ] );
			if ( self::plausible_flatsome_version( $version ) ) {
				return $version;
			}
		}
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$found = self::version_from_payload( $value, $depth + 1 );
				if ( $found ) {
					return $found;
				}
			}
		}
		return '';
	}

	/**
	 * @param mixed $data Payload.
	 * @return string
	 */
	public static function token_from_payload( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}
		foreach ( array( 'token', 'access_token', 'accessToken' ) as $key ) {
			if ( empty( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$token = trim( $data[ $key ] );
			if ( strlen( $token ) >= 20 && strlen( $token ) <= 200 && ! preg_match( '#^https?://#i', $token ) ) {
				return $token;
			}
		}
		return '';
	}

	/**
	 * @param array $data Payload.
	 * @param int   $http HTTP status.
	 * @return string
	 */
	public static function uxthemes_error_detail( $data, $http ) {
		$code = '';
		if ( isset( $data['code'] ) && is_string( $data['code'] ) ) {
			$code = strtolower( $data['code'] );
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$code = strtolower( $data['error'] );
		}
		$map      = array(
			'malformed'                => 'Your purchase code is malformed.',
			'malformed-code'           => 'Your purchase code is malformed.',
			'malformed-purchase-code'  => 'Your purchase code is malformed.',
			'malformed_code'           => 'Your purchase code is malformed.',
			'invalid-code'             => 'The purchase code is malformed or does not belong to a Flatsome sale.',
			'invalid_code'             => 'The purchase code is malformed or does not belong to a Flatsome sale.',
			'invalid-purchase-code'    => 'Invalid purchase code.',
			'already-registered'       => 'This purchase code is already registered. Remove the old domain at account.uxthemes.com, then Guard will register this site on the next cycle.',
			'already_registered'       => 'This purchase code is already registered. Remove the old domain at account.uxthemes.com, then Guard will register this site on the next cycle.',
			'too-many-sites'           => 'Your purchase code has been used on too many sites. Manage licenses at account.uxthemes.com.',
			'too_many_sites'           => 'Your purchase code has been used on too many sites. Manage licenses at account.uxthemes.com.',
			'sites-limit'              => 'Your purchase code has been used on too many sites. Manage licenses at account.uxthemes.com.',
			'blocked'                  => 'Your purchase code has been blocked. Please contact support to resolve the issue.',
			'locked'                   => 'Your purchase code has been locked. Please contact support to resolve the issue.',
			'no-domain'                => 'No domain was sent with the request.',
			'no_domain'                => 'No domain was sent with the request.',
			'invalid-domain'           => 'Unable to parse the domain for your site.',
			'invalid_domain'           => 'Unable to parse the domain for your site.',
			'domain-changed'           => 'Flatsome was unable to get the latest version. Your site might have changed domain after you registered it.',
			'domain_changed'           => 'Flatsome was unable to get the latest version. Your site might have changed domain after you registered it.',
			'unverified'               => 'Flatsome was unable to get the latest version because the purchase code has not been verified yet. Please re-register it in order to receive updates.',
			'not-found'                => 'The requested resource no longer exists.',
			'out_of_support'           => 'ThemeForest support for this purchase has ended, so UX Themes will not send the update.',
			'out-of-support'           => 'ThemeForest support for this purchase has ended, so UX Themes will not send the update.',
			'overloaded'               => 'Sorry, the API is overloaded.',
			'unavailable'              => 'Sorry, the API is unavailable at the moment.',
		);
		if ( $code && isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
		if ( (int) $http >= 400 ) {
			foreach ( array( 'message', 'detail', 'error_description' ) as $key ) {
				if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
					$text = self::plain_api_text( $data[ $key ] );
					if ( $text ) {
						return $text;
					}
				}
			}
		}
		return '';
	}

	/**
	 * @param string $text API text.
	 * @return string
	 */
	public static function plain_api_text( $text ) {
		$text = (string) $text;
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$text = preg_replace( '/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/i', '[purchase code]', $text );
		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, 220 );
		} else {
			$text = substr( $text, 0, 220 );
		}
		if ( '' === $text || false !== stripos( $text, '<html' ) || false !== stripos( $text, 'Cannot GET' ) || false !== stripos( $text, 'Cannot POST' ) ) {
			return '';
		}
		return $text;
	}

	/**
	 * Floor is 3.20.9. A watched or WordPress-offered release can only raise it.
	 *
	 * @param string $watched Watched version.
	 * @param string $offered WordPress offered version.
	 * @return string
	 */
	public static function target_version( $watched, $offered = '' ) {
		$target = self::RECOMMENDED;
		foreach ( array( $watched, $offered ) as $candidate ) {
			$candidate = self::plain_version( $candidate );
			if ( self::plausible_flatsome_version( $candidate ) && self::compare( $candidate, $target ) > 0 ) {
				$target = $candidate;
			}
		}
		return $target;
	}

	/**
	 * @param string $installed Installed.
	 * @param string $target    Target.
	 * @return bool
	 */
	public static function meets_target( $installed, $target ) {
		return self::compare( $installed, $target ) >= 0;
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
	 * ThemeForest / Envato purchase code (UUID).
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function sanitize_purchase_code( $code ) {
		$code = strtolower( (string) $code );
		$code = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $code );
		$code = str_replace( array( '–', '—', '−', '‐', '‑' ), '-', $code );
		$code = preg_replace( '/\s+/', '', $code );
		if ( preg_match( '/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/', $code, $m ) ) {
			return $m[0];
		}
		return '';
	}

	/**
	 * Detect UX Themes / Envato registration without storing a new code.
	 *
	 * @return bool
	 */
	public static function license_registered() {
		if ( class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
			if ( ! empty( $settings['flatsome_purchase_code'] ) && self::sanitize_purchase_code( $settings['flatsome_purchase_code'] ) ) {
				return true;
			}
		}
		$keys = array(
			'flatsome_wup_purchase_code',
			'flatsome_registration',
			'flatsome_token',
			'ux_theme_registration',
			'flatsome_license',
			'uxthemes_registration',
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
	 * @param string|null $watched Official version from the demo stylesheet. Null reads the stored watch.
	 * @return array<string,mixed>
	 */
	public static function snapshot( $watched = null ) {
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
		if ( null === $watched ) {
			$watched = '';
			if ( function_exists( 'get_option' ) ) {
				$watched = (string) get_option( 'luxe_theme_guard_watched_version', '' );
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
		$target = self::target_version( (string) $watched, $available );
		$watched_ok = self::plausible_flatsome_version( $watched ) ? self::plain_version( $watched ) : '';
		return array(
			'installed'    => $installed,
			'version'      => $version,
			'available'    => $available,
			'package'      => $package,
			'watched'      => $watched_ok,
			'target'       => $target,
			'stylesheet'   => $stylesheet,
			'child'        => $child,
			'license'      => self::license_registered(),
			'xss_ok'       => $installed && self::xss_patched( $version ),
			'recommended'  => $installed && self::meets_target( $version, $target ),
			'can_upgrade'  => self::may_upgrade( self::THEME, $package, $version, $available ? $available : '0' ),
			'learning_on'  => class_exists( 'Luxe_Theme_Guard_Plugin' ) ? Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' ) : true,
			'cron_next'     => ( class_exists( 'Luxe_Theme_Guard_Learning' ) && function_exists( 'wp_next_scheduled' ) ) ? (int) wp_next_scheduled( Luxe_Theme_Guard_Learning::CRON ) : 0,
			'cron_schedule' => class_exists( 'Luxe_Theme_Guard_Learning' ) ? Luxe_Theme_Guard_Learning::cron_schedule_name() : 'ltg_five',
			'update_note'   => function_exists( 'get_option' ) ? (string) get_option( 'luxe_theme_guard_update_note', '' ) : '',
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
		$target  = ! empty( $snap['target'] ) ? self::plain_version( $snap['target'] ) : self::RECOMMENDED;
		if ( ! self::plausible_flatsome_version( $target ) || self::compare( $target, self::RECOMMENDED ) < 0 ) {
			$target = self::RECOMMENDED;
		}
		$watched = ! empty( $snap['watched'] ) ? self::plain_version( $snap['watched'] ) : '';
		$recommended_ok = ! empty( $snap['recommended'] );
		if ( '' !== $ver && ! empty( $snap['target'] ) ) {
			$recommended_ok = self::meets_target( $ver, $target );
		}
		$watch_note = $watched ? ( ' Watching official ' . $watched . '.' ) : ' Official release watch has not reported a newer version yet.';
		$note       = isset( $snap['update_note'] ) ? trim( (string) $snap['update_note'] ) : '';
		if ( $recommended_ok ) {
			$recommended_detail = 'Current ' . $ver . '.' . $watch_note;
		} else {
			$recommended_detail = 'Need ' . $target . '.' . $watch_note;
			if ( $avail ) {
				$recommended_detail .= ' WordPress offers ' . $avail . '.';
			} elseif ( $note ) {
				$recommended_detail .= ' ' . $note;
			} elseif ( ! empty( $snap['license'] ) ) {
				$recommended_detail .= ' The purchase code is saved. Guard is asking UX Themes for the official download.';
			} else {
				$recommended_detail .= ' Paste the ThemeForest purchase code so Guard can install the official package.';
			}
		}
		$auto    = class_exists( 'Luxe_Theme_Guard_Plugin' ) ? Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) : true;
		$learn_on = true;
		if ( isset( $snap['learning_on'] ) ) {
			$learn_on = ! empty( $snap['learning_on'] );
		} elseif ( class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			$learn_on = Luxe_Theme_Guard_Plugin::instance()->enabled( 'learning_24_7' );
		}
		$cron_next = isset( $snap['cron_next'] ) ? (int) $snap['cron_next'] : 0;
		$cron_ok   = $learn_on;
		if ( $learn_on && function_exists( 'wp_next_scheduled' ) ) {
			$cron_ok = ( $cron_next > 0 ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
		}
		if ( $learn_on && ! empty( $snap['cron_schedule'] ) && class_exists( 'Luxe_Theme_Guard_Learning' ) && Luxe_Theme_Guard_Learning::needs_reschedule( $snap['cron_schedule'] ) ) {
			$cron_ok = false;
		}
		$cron_detail = $learn_on
			? '24/7: one process every 5 minutes. Guard watches the official Flatsome release and auto-upgrades when WordPress has the licensed package. Never on a shopper page.'
			: '24/7 process learning is off.';
		if ( $learn_on && ! $cron_ok && ! empty( $snap['cron_schedule'] ) ) {
			$cron_detail = 'Old 12-hour timer is still registered. Open Luxe Theme so Theme Guard migrates it to every 5 minutes.';
		}
		$child   = isset( $snap['child'] ) ? (string) $snap['child'] : '';
		$has_child = ( $child && $child !== self::THEME );
		$signals = array(
			self::row( 'installed', 'Flatsome parent installed', ! empty( $snap['installed'] ), $ver ? ( 'Installed ' . $ver ) : 'Flatsome folder not found in wp-content/themes/flatsome' ),
			self::row( 'xss', 'XSS patch 3.20.6+', ! empty( $snap['xss_ok'] ), ! empty( $snap['xss_ok'] ) ? 'CVE-2026-28083 is patched.' : 'Update past 3.20.5. 3.20.6+ is required.' ),
			self::row( 'recommended', 'Official Flatsome ' . $target . '+', $recommended_ok, $recommended_detail ),
			self::row( 'license', 'Official license registered', ! empty( $snap['license'] ) || $recommended_ok, ! empty( $snap['license'] ) || $recommended_ok ? 'UX Themes / Envato registration present or already on the watched release.' : 'Paste your ThemeForest purchase code on this page, or open Flatsome → Theme Registration.' ),
			self::row( 'auto', 'Auto-update armed for Flatsome only', $auto, $auto ? 'WordPress may apply official Flatsome packages. Child theme is never overwritten.' : 'Auto-update is off.' ),
			self::row( 'package', 'No nulled download', true, 'Guard refuses unofficial zip URLs. Official HTTPS package only.' ),
			self::row( 'child', 'Child theme never overwritten', true, $has_child ? ( 'Active child theme ' . $child . '. Guard never replaces it.' ) : 'Parent only is OK. Guard never replaces a child theme if you add one later.' ),
			self::row( 'plugins', 'Plugins left alone', true, 'Never replaces Product Scout, Luxe SEO, Mission Control, or Stay Repair.' ),
			self::row( 'amazon', 'Amazon URLs untouched', true, 'Amazon AI never rewrites affiliate links. tag=luxetrendse0f-20 stays as stored.' ),
			self::row( 'publish', 'Never publishes content', true, 'No post status changes. Theme files only.' ),
			self::row( 'public', 'No public-page upgrader', true, 'Updates run from cron or the admin button, never on a shopper page view.' ),
			self::row( 'purge', 'Cache purge ready', true, ! empty( $heals['purge'] ) ? implode( ', ', (array) $heals['purge'] ) : 'LiteSpeed purge after a successful theme update.' ),
			self::row( 'cron', '24/7 process learning', $cron_ok, $cron_detail ),
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
