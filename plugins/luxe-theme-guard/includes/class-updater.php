<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Official Flatsome parent updates only. Never runs on public HTML.
 */
class Luxe_Theme_Guard_Updater {

	const LOCK           = 'luxe_theme_guard_lock';
	const AUTO_SITE      = 'auto_update_themes';
	const OPTION_WATCHED = 'luxe_theme_guard_watched_version';
	const OPTION_WATCHED_AT = 'luxe_theme_guard_watched_at';
	const OPTION_NOTE    = 'luxe_theme_guard_update_note';
	const OPTION_TOKEN   = 'luxe_theme_guard_ux_token';
	const WATCH_EVERY    = 3600;

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
	 * Register the Envato purchase code the way Flatsome 3.14+ expects.
	 * Clears outdated wupdates leftovers first, then POST /v1/license/{code}.
	 *
	 * @param string $code Purchase code.
	 * @return array{ok:bool,detail:string}
	 */
	public static function apply_purchase_code( $code ) {
		$code = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $code );
		if ( ! $code ) {
			return array(
				'ok'     => false,
				'detail' => 'No purchase code.',
			);
		}
		// Writing flatsome_wup_purchase_code triggers Flatsome's
		// "unsupported or outdated method" notice. Always clear it.
		self::clear_outdated_registration();

		$result = self::instance()->register_with_uxthemes( $code );
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( 'update_themes' );
		}
		return $result;
	}

	/**
	 * Remove pre-3.14 wupdates / Envato leftovers that block modern registration.
	 *
	 * @return string[] Deleted option names.
	 */
	public static function clear_outdated_registration() {
		$deleted = array();
		$keys    = array(
			'flatsome_wup_purchase_code',
			'flatsome_wup_buyer',
			'flatsome_wup_sold_at',
			'flatsome_wup_supported_until',
			'flatsome_wup_errors',
			'flatsome_wup_attempts',
			'flatsome_wupdates',
			'flatsome_envato',
		);
		foreach ( $keys as $key ) {
			if ( false !== get_option( $key, false ) ) {
				delete_option( $key );
				$deleted[] = $key;
			}
		}
		$reg = get_option( 'flatsome_registration', array() );
		if ( is_array( $reg ) && isset( $reg['purchase_code'] ) ) {
			unset( $reg['purchase_code'] );
			update_option( 'flatsome_registration', $reg, false );
			$deleted[] = 'flatsome_registration.purchase_code';
		}
		return $deleted;
	}

	/**
	 * True when Flatsome would show the outdated-method warning.
	 *
	 * @return bool
	 */
	public static function has_outdated_registration() {
		if ( get_option( 'flatsome_wup_purchase_code', '' ) || get_option( 'flatsome_envato', '' ) ) {
			return true;
		}
		$reg = get_option( 'flatsome_registration', array() );
		if ( ! is_array( $reg ) ) {
			return false;
		}
		$verified = ! empty( $reg['licenseType'] ) && is_string( $reg['licenseType'] );
		$code     = ! empty( $reg['purchaseCode'] ) ? Luxe_Theme_Guard_Signals::sanitize_purchase_code( $reg['purchaseCode'] ) : '';
		// Snake_case only, or code without verified licenseType + leftover wupdates path.
		if ( ! $verified && ! empty( $reg['purchase_code'] ) && ! $code ) {
			return true;
		}
		return false;
	}

	/**
	 * Write a saved purchase code into Flatsome's updater before a version check.
	 */
	public static function sync_saved_license() {
		if ( ! class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			return;
		}
		$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
		if ( empty( $settings['flatsome_purchase_code'] ) ) {
			return;
		}
		$code = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $settings['flatsome_purchase_code'] );
		if ( ! $code ) {
			return;
		}
		$reg = get_option( 'flatsome_registration', array() );
		if ( ! is_array( $reg ) ) {
			$reg = array();
		}
		$have_code = ! empty( $reg['purchaseCode'] ) && Luxe_Theme_Guard_Signals::sanitize_purchase_code( $reg['purchaseCode'] ) === $code;
		$verified  = $have_code && ! empty( $reg['licenseType'] ) && is_string( $reg['licenseType'] );
		if ( $verified ) {
			return;
		}
		self::apply_purchase_code( $code );
	}

	/**
	 * Register (or re-verify) this site with the UX Themes license API.
	 *
	 * @param string $code Purchase code.
	 * @return array{ok:bool,detail:string}
	 */
	public function register_with_uxthemes( $code ) {
		$code = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $code );
		if ( ! $code ) {
			return array(
				'ok'     => false,
				'detail' => 'No purchase code.',
			);
		}

		$instance = $this->theme_registration();
		if ( is_object( $instance ) && is_callable( array( $instance, 'register' ) ) ) {
			$raw  = $this->call_theme( array( $instance, 'register' ), array( $code ) );
			$norm = $this->normalize_theme_result( $raw );
			$reg  = get_option( 'flatsome_registration', array() );
			$ok   = is_array( $reg ) && ! empty( $reg['purchaseCode'] ) && ! empty( $reg['id'] );
			// 429 with an id still counts as registered (needs later verify).
			if ( is_wp_error( $raw ) && 429 === (int) $raw->get_error_code() ) {
				$data = $raw->get_error_data();
				if ( is_array( $data ) && ! empty( $data['id'] ) ) {
					$ok = true;
				}
			}
			if ( is_array( $raw ) && ! empty( $raw['licenseType'] ) ) {
				$ok = true;
			}
			$detail = self::registration_detail_message( $raw, $norm, $ok );
			return array(
				'ok'     => (bool) $ok,
				'detail' => $detail,
			);
		}

		return $this->register_via_api( $code );
	}

	/**
	 * Human-readable registration result, including already-registered steps.
	 *
	 * @param mixed $raw  Raw theme/API result.
	 * @param array $norm Normalized result.
	 * @param bool  $ok   Success.
	 * @return string
	 */
	public static function registration_detail_message( $raw, $norm, $ok ) {
		$detail = ! empty( $norm['detail'] ) ? (string) $norm['detail'] : '';
		if ( $ok && ! $detail ) {
			return 'UX Themes registered this site for Flatsome updates.';
		}
		if ( $ok ) {
			return $detail;
		}
		$status = 0;
		if ( is_wp_error( $raw ) ) {
			$status = (int) $raw->get_error_code();
			if ( ! $detail ) {
				$detail = Luxe_Theme_Guard_Signals::plain_api_text( $raw->get_error_message() );
			}
		}
		$already = ( 409 === $status )
			|| false !== stripos( $detail, 'already registered' )
			|| false !== stripos( $detail, 'already-registered' );
		if ( $already ) {
			return 'This purchase code is already registered on another site. Open https://account.uxthemes.com (Envato purchaser login), click the trash icon next to the old domain, then paste the code here again and Save. Guide: https://docs.uxthemes.com/article/417-the-purchase-code-is-already-registered-on-another-site';
		}
		return $detail ? $detail : 'UX Themes did not confirm Flatsome registration.';
	}

	/**
	 * Direct UX Themes registration when Flatsome's classes are not loaded.
	 *
	 * @param string $code Purchase code.
	 * @return array{ok:bool,detail:string}
	 */
	private function register_via_api( $code ) {
		$reg = get_option( 'flatsome_registration', array() );
		if ( ! is_array( $reg ) ) {
			$reg = array();
		}
		$id     = ! empty( $reg['id'] ) ? (string) $reg['id'] : '';
		$method = $id ? 'PATCH' : 'POST';
		$path   = $id ? '/v1/license/' . $code . '/' . rawurlencode( $id ) : '/v1/license/' . $code;
		$res    = $this->uxthemes_request( $method, $path );
		if ( ! is_array( $res ) ) {
			return array(
				'ok'     => false,
				'detail' => 'Could not reach the official Flatsome update server.',
			);
		}
		$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( $res['code'], $res['body'] );
		$data   = json_decode( $res['body'], true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		// Match Flatsome: keep a 429 payload that already has an id.
		if ( 429 === (int) $res['code'] && ! empty( $data['id'] ) ) {
			unset( $data['retry-after'] );
			$data['purchaseCode'] = isset( $data['purchaseCode'] ) ? $data['purchaseCode'] : $code;
			update_option( 'flatsome_registration', $data, false );
			return array(
				'ok'     => true,
				'detail' => $parsed['detail'] ? $parsed['detail'] : 'Your site is registered. Purchase code verification is pending.',
			);
		}
		if ( (int) $res['code'] >= 200 && (int) $res['code'] < 300 && ( ! empty( $data['id'] ) || ! empty( $data['purchaseCode'] ) ) ) {
			unset( $data['retry-after'] );
			if ( empty( $data['purchaseCode'] ) ) {
				$data['purchaseCode'] = $code;
			}
			update_option( 'flatsome_registration', $data, false );
			return array(
				'ok'     => true,
				'detail' => 'UX Themes registered this site for Flatsome updates.',
			);
		}
		$fake_err = null;
		if ( function_exists( 'is_wp_error' ) ) {
			$fake_err = new WP_Error( (int) $res['code'], $parsed['detail'] ? $parsed['detail'] : '' );
		}
		return array(
			'ok'     => false,
			'detail' => self::registration_detail_message( $fake_err ? $fake_err : null, $parsed, false ),
		);
	}

	/**
	 * Read the official demo stylesheet version. Cached for one hour.
	 * Does not download a theme zip.
	 *
	 * @param bool $force Skip the cache.
	 * @return string
	 */
	public function watch_official_version( $force = false ) {
		$cached = function_exists( 'get_option' ) ? (string) get_option( self::OPTION_WATCHED, '' ) : '';
		$at     = function_exists( 'get_option' ) ? (int) get_option( self::OPTION_WATCHED_AT, 0 ) : 0;
		if ( Luxe_Theme_Guard_Signals::plausible_flatsome_version( $cached ) ) {
			$cached = Luxe_Theme_Guard_Signals::plain_version( $cached );
		} else {
			$cached = '';
		}
		if ( ! $force && $cached && ( time() - $at ) < self::WATCH_EVERY ) {
			return $cached;
		}
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return $cached;
		}
		$url = Luxe_Theme_Guard_Signals::official_watch_url();
		if ( ! Luxe_Theme_Guard_Signals::watch_url_allowed( $url ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'user-agent'  => 'LuxeThemeGuard/' . ( defined( 'LUXE_THEME_GUARD_VERSION' ) ? LUXE_THEME_GUARD_VERSION : '1.4.0' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $cached;
		}
		$body = substr( (string) wp_remote_retrieve_body( $response ), 0, 4000 );
		$ver  = Luxe_Theme_Guard_Signals::parse_style_version( $body );
		if ( ! $ver || Luxe_Theme_Guard_Signals::compare( $ver, Luxe_Theme_Guard_Signals::RECOMMENDED ) < 0 ) {
			return $cached;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_WATCHED, $ver, false );
			update_option( self::OPTION_WATCHED_AT, time(), false );
		}
		return $ver;
	}

	/**
	 * Ask the UX Themes API for the official package and store why if it does not send one.
	 *
	 * @return string
	 */
	public function fetch_official_package() {
		self::sync_saved_license();
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( 'update_themes' );
		}
		$code = '';
		if ( class_exists( 'Luxe_Theme_Guard_Plugin' ) ) {
			$settings = Luxe_Theme_Guard_Plugin::instance()->settings();
			$code     = isset( $settings['flatsome_purchase_code'] ) ? Luxe_Theme_Guard_Signals::sanitize_purchase_code( $settings['flatsome_purchase_code'] ) : '';
		}
		if ( ! $code ) {
			$reg = get_option( 'flatsome_registration', array() );
			if ( is_array( $reg ) && ! empty( $reg['purchaseCode'] ) ) {
				$code = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $reg['purchaseCode'] );
			}
		}
		$installed = '';
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme     = wp_get_theme( Luxe_Theme_Guard_Signals::THEME );
			$installed = ( $theme && ! is_wp_error( $theme ) && $theme->exists() ) ? (string) $theme->get( 'Version' ) : '';
		}
		$note      = $code ? '' : 'Paste the ThemeForest purchase code and save.';
		$available = '';
		$package   = '';
		$token     = '';

		$latest = $this->uxthemes_request( 'GET', '/v1/latest-version' );
		if ( is_array( $latest ) ) {
			$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( $latest['code'], $latest['body'] );
			if ( $parsed['version'] ) {
				$available = $parsed['version'];
				$this->remember_watched( $available );
			}
		} elseif ( '' === $note ) {
			$note = 'Could not reach the official Flatsome update server.';
		}

		// Primary path: Flatsome 3.14+ registration client (purchaseCode + license id).
		if ( $code ) {
			$from_theme = $this->package_from_theme_client( $code );
			if ( ! empty( $from_theme['package'] ) ) {
				$package = $from_theme['package'];
			}
			if ( ! empty( $from_theme['version'] ) && ( '' === $available || Luxe_Theme_Guard_Signals::compare( $from_theme['version'], $available ) > 0 ) ) {
				$available = $from_theme['version'];
				$this->remember_watched( $available );
			}
			if ( ! $package && ! empty( $from_theme['detail'] ) ) {
				$note = $from_theme['detail'];
			}
		}

		// Fallback: same UX Themes license endpoints Flatsome uses, without the theme classes.
		if ( $code && ! $package ) {
			$license_note = '';
			$reg          = get_option( 'flatsome_registration', array() );
			$id           = ( is_array( $reg ) && ! empty( $reg['id'] ) ) ? (string) $reg['id'] : '';
			if ( ! $id ) {
				$registered = $this->register_via_api( $code );
				if ( ! empty( $registered['detail'] ) && empty( $registered['ok'] ) ) {
					$license_note = $registered['detail'];
				}
				$reg = get_option( 'flatsome_registration', array() );
				$id  = ( is_array( $reg ) && ! empty( $reg['id'] ) ) ? (string) $reg['id'] : '';
			}
			if ( $id ) {
				$ver_path = '/v1/license/' . $code . '/' . rawurlencode( $id ) . '/latest-version';
				$res      = $this->uxthemes_request( 'GET', $ver_path );
				if ( is_array( $res ) ) {
					$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( $res['code'], $res['body'] );
					if ( $parsed['version'] && ( '' === $available || Luxe_Theme_Guard_Signals::compare( $parsed['version'], $available ) > 0 ) ) {
						$available = $parsed['version'];
						$this->remember_watched( $available );
					}
					if ( ! $parsed['ok'] && $parsed['detail'] ) {
						$license_note = $parsed['detail'];
					}
				} else {
					$license_note = 'Could not reach the official Flatsome update server.';
				}
				if ( $available ) {
					$dl_path = '/v1/license/' . $code . '/' . rawurlencode( $id ) . '/download-url/' . rawurlencode( $available );
					$res     = $this->uxthemes_request( 'GET', $dl_path );
					if ( is_array( $res ) ) {
						$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( $res['code'], $res['body'] );
						if ( $parsed['package'] ) {
							$package      = $parsed['package'];
							$license_note = '';
						} elseif ( ! $parsed['ok'] && $parsed['detail'] ) {
							$license_note = $parsed['detail'];
						}
					}
				}
			}
			if ( ! $package && $license_note && ( '' === $note || false !== strpos( $note, 'No purchase code' ) || false !== strpos( $note, 'unreadable' ) || false !== strpos( $note, 'without a Flatsome download' ) ) ) {
				$note = $license_note;
			}
		}

		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
		}
		if ( function_exists( 'wupdates_check_JQ9eJ' ) && ! $package ) {
			$transient           = new stdClass();
			$transient->checked  = array( Luxe_Theme_Guard_Signals::THEME => $installed );
			$transient->response = array();
			$checked             = wupdates_check_JQ9eJ( $transient );
			$pkg                 = Luxe_Theme_Guard_Signals::package_from_update_transient( $checked );
			if ( $pkg['package'] && Luxe_Theme_Guard_Signals::may_upgrade( Luxe_Theme_Guard_Signals::THEME, $pkg['package'], $installed, $pkg['version'] ? $pkg['version'] : '0' ) ) {
				$package   = $pkg['package'];
				$available = $pkg['version'] ? $pkg['version'] : $available;
			}
		}

		$ready = $package && $available && Luxe_Theme_Guard_Signals::may_upgrade( Luxe_Theme_Guard_Signals::THEME, $package, $installed, $available );
		if ( $ready ) {
			$checked             = new stdClass();
			$checked->response   = array(
				Luxe_Theme_Guard_Signals::THEME => array(
					'theme'       => Luxe_Theme_Guard_Signals::THEME,
					'new_version' => $available,
					'package'     => $package,
					'url'         => 'https://themeforest.net/item/flatsome-multipurpose-responsive-woocommerce-theme/5484319',
				),
			);
			$this->store_theme_package( $installed, $checked );
			$note = 'Official package ' . $available . ' is ready.';
			$this->store_download_token( $token, $package );
		} else {
			$this->store_download_token( '', '' );
			if ( '' === $note ) {
				$note = 'UX Themes did not return a Flatsome download. Guard will ask again on the next cycle.';
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NOTE, $note, false );
		}
		return $note;
	}

	/**
	 * The higher of the demo watch and the UX Themes API version.
	 *
	 * @param string $watched Watched version.
	 * @return string
	 */
	public function highest_watched( $watched ) {
		$watched = Luxe_Theme_Guard_Signals::plain_version( (string) $watched );
		$best    = Luxe_Theme_Guard_Signals::plausible_flatsome_version( $watched ) ? $watched : '';
		$stored  = function_exists( 'get_option' ) ? Luxe_Theme_Guard_Signals::plain_version( (string) get_option( self::OPTION_WATCHED, '' ) ) : '';
		if ( Luxe_Theme_Guard_Signals::plausible_flatsome_version( $stored ) && ( '' === $best || Luxe_Theme_Guard_Signals::compare( $stored, $best ) > 0 ) ) {
			$best = $stored;
		}
		return $best;
	}

	/**
	 * @param string $version Version.
	 */
	private function remember_watched( $version ) {
		$version = Luxe_Theme_Guard_Signals::plain_version( $version );
		if ( ! Luxe_Theme_Guard_Signals::plausible_flatsome_version( $version ) ) {
			return;
		}
		if ( Luxe_Theme_Guard_Signals::compare( $version, Luxe_Theme_Guard_Signals::RECOMMENDED ) < 0 ) {
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_WATCHED, $version, false );
			update_option( self::OPTION_WATCHED_AT, time(), false );
		}
	}

	/**
	 * @param string $token   Download token.
	 * @param string $package Package URL.
	 */
	private function store_download_token( $token, $package ) {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$path = (string) parse_url( (string) $package, PHP_URL_PATH );
		if ( $token && '/v1/token/download' === $path ) {
			update_option( self::OPTION_TOKEN, $token, false );
			return;
		}
		delete_option( self::OPTION_TOKEN );
	}

	/**
	 * Site URL Flatsome sends as Referer. Prefer the raw siteurl option.
	 *
	 * @return string
	 */
	private function uxthemes_referer() {
		if ( function_exists( 'get_option' ) ) {
			$siteurl = (string) get_option( 'siteurl', '' );
			if ( $siteurl ) {
				return $siteurl;
			}
		}
		return function_exists( 'home_url' ) ? home_url( '/' ) : '';
	}

	/**
	 * @param string $method Method.
	 * @param string $path   Allowlisted path.
	 * @param array  $body   Form body for POST/PATCH.
	 * @param string $token  Bearer token for a legacy download call.
	 * @return array{code:int,body:string}|null
	 */
	private function uxthemes_request( $method, $path, $body = array(), $token = '' ) {
		$url = Luxe_Theme_Guard_Signals::uxthemes_request_url( $path );
		if ( ! $url ) {
			return null;
		}
		$referer = $this->uxthemes_referer();
		if ( ! $referer ) {
			return null;
		}
		$headers = array(
			'Accept'  => 'application/json',
			'Referer' => $referer,
		);
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$theme_ver = '';
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme( Luxe_Theme_Guard_Signals::THEME );
			if ( $theme && ! is_wp_error( $theme ) && $theme->exists() ) {
				$theme_ver = (string) $theme->get( 'Version' );
			}
		}
		$wp_ver = isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '6.8';
		$ua     = $theme_ver
			? ( 'Flatsome/' . $theme_ver . '; WordPress/' . $wp_ver . '; ' . $referer )
			: ( 'WordPress/' . $wp_ver . '; ' . $referer );
		$args   = array(
			'method'      => $method,
			'timeout'     => 60,
			'redirection' => 2,
			'headers'     => $headers,
			'user-agent'  => $ua,
		);
		if ( in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) && ! empty( $body ) ) {
			$args['body'] = $body;
		}
		if ( function_exists( 'wp_remote_request' ) ) {
			$response = wp_remote_request( $url, $args );
		} elseif ( 'POST' === $method && function_exists( 'wp_remote_post' ) ) {
			$response = wp_remote_post( $url, $args );
		} elseif ( function_exists( 'wp_remote_get' ) ) {
			$response = wp_remote_get( $url, $args );
		} else {
			return null;
		}
		if ( is_wp_error( $response ) ) {
			return null;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Ask the Flatsome copy already installed on this site for the temporary download URL.
	 * Registers first (Flatsome 3.14+ method), then latest-version, then download-url.
	 *
	 * @param string $code Purchase code.
	 * @return array{version?:string,package?:string,detail?:string}
	 */
	private function package_from_theme_client( $code ) {
		$instance = $this->theme_registration();
		if ( ! is_object( $instance ) ) {
			return array();
		}
		$out      = array();
		$code     = Luxe_Theme_Guard_Signals::sanitize_purchase_code( $code );
		$have     = is_callable( array( $instance, 'get_code' ) ) ? Luxe_Theme_Guard_Signals::sanitize_purchase_code( (string) $instance->get_code() ) : '';
		$verified = is_callable( array( $instance, 'is_verified' ) ) ? (bool) $instance->is_verified() : false;

		// Always register (or re-verify) before asking for updates. get_latest_version
		// reads purchaseCode from flatsome_registration — the old snake_case key is ignored.
		if ( $code && ( $have !== $code || ! $verified ) ) {
			$registered = $this->register_with_uxthemes( $code );
			if ( empty( $registered['ok'] ) ) {
				return array(
					'detail' => ! empty( $registered['detail'] ) ? $registered['detail'] : 'UX Themes did not confirm Flatsome registration.',
				);
			}
			// Refresh the instance options after register wrote flatsome_registration.
			$instance = $this->theme_registration();
			if ( ! is_object( $instance ) ) {
				return array(
					'detail' => ! empty( $registered['detail'] ) ? $registered['detail'] : 'Registered, but Flatsome registration class is unavailable.',
				);
			}
		}

		$version_result = $this->call_theme( array( $instance, 'get_latest_version' ), array() );
		$version_norm   = $this->normalize_theme_result( $version_result );
		if ( ! empty( $version_norm['version'] ) ) {
			$out['version'] = $version_norm['version'];
		} elseif ( ! empty( $version_norm['detail'] ) ) {
			$out['detail'] = $version_norm['detail'];
			return $out;
		} else {
			$out['detail'] = 'No version received.';
			return $out;
		}

		$url_norm = $this->normalize_theme_result( $this->call_theme( array( $instance, 'get_download_url' ), array( $out['version'] ) ) );
		if ( ! empty( $url_norm['package'] ) ) {
			$out['package'] = $url_norm['package'];
		} elseif ( ! empty( $url_norm['detail'] ) ) {
			$out['detail'] = $url_norm['detail'];
		} else {
			$out['detail'] = 'No URL received.';
		}
		return $out;
	}

	/**
	 * @return object|null
	 */
	private function theme_registration() {
		if ( ! class_exists( 'Flatsome_Registration' ) || ! class_exists( 'UxThemes_API' ) ) {
			return null;
		}
		try {
			return new Flatsome_Registration( new UxThemes_API() );
		} catch ( Exception $e ) {
			return null;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * @param callable $callback Callback.
	 * @param array    $args     Arguments.
	 * @return mixed
	 */
	private function call_theme( $callback, $args ) {
		try {
			return call_user_func_array( $callback, $args );
		} catch ( Exception $e ) {
			return null;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * @param mixed $result Theme client result.
	 * @return array{version?:string,package?:string,detail?:string}
	 */
	private function normalize_theme_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return array(
				'detail' => Luxe_Theme_Guard_Signals::plain_api_text( $result->get_error_message() ),
			);
		}
		if ( is_string( $result ) ) {
			$trim    = trim( $result );
			$version = Luxe_Theme_Guard_Signals::plain_version( $trim );
			if ( strlen( $trim ) <= 20 && Luxe_Theme_Guard_Signals::plausible_flatsome_version( $version ) ) {
				return array( 'version' => $version );
			}
			$url = Luxe_Theme_Guard_Signals::trusted_download_url( $trim );
			return $url ? array( 'package' => $url ) : array();
		}
		if ( is_array( $result ) ) {
			$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( 200, wp_json_encode( $result ) );
			$out    = array();
			if ( $parsed['version'] ) {
				$out['version'] = $parsed['version'];
			}
			if ( $parsed['package'] ) {
				$out['package'] = $parsed['package'];
			}
			if ( ! $parsed['ok'] && $parsed['detail'] ) {
				$out['detail'] = $parsed['detail'];
			}
			return $out;
		}
		return array();
	}

	/**
	 * @param string $version   Installed version.
	 * @param object $checked   Transient returned by Flatsome's updater.
	 */
	private function store_theme_package( $version, $checked ) {
		if ( ! function_exists( 'get_site_transient' ) || ! function_exists( 'set_site_transient' ) ) {
			return;
		}
		$stored = get_site_transient( 'update_themes' );
		if ( ! is_object( $stored ) ) {
			$stored = new stdClass();
		}
		if ( empty( $stored->checked ) || ! is_array( $stored->checked ) ) {
			$stored->checked = array();
		}
		if ( empty( $stored->response ) || ! is_array( $stored->response ) ) {
			$stored->response = array();
		}
		$stored->checked[ Luxe_Theme_Guard_Signals::THEME ]  = $version;
		$stored->response[ Luxe_Theme_Guard_Signals::THEME ] = (array) $checked->response[ Luxe_Theme_Guard_Signals::THEME ];
		$stored->last_checked                                = time();
		set_site_transient( 'update_themes', $stored );
	}

	/**
	 * Cron upgrades need a writable themes directory.
	 *
	 * @param string $method Method.
	 * @return string
	 */
	public function force_direct( $method ) {
		return 'direct';
	}

	/**
	 * WordPress auto-update for Flatsome parent only. 24/7 learning owns cron.
	 */
	public function boot() {
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_manual' ) );
		add_action( 'admin_init', array( $this, 'maybe_auto_clear_outdated' ), 5 );
	}

	/**
	 * Drop wupdates leftovers as soon as an admin loads Theme Guard / Flatsome screens.
	 * Those leftovers alone cause Flatsome's "unsupported or outdated method" banner.
	 */
	public function maybe_auto_clear_outdated() {
		if ( ! self::has_outdated_registration() ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::clear_outdated_registration();
	}

	/**
	 * Arm official Flatsome auto-update. Do not upgrade on activate.
	 */
	public static function activate() {
		self::arm_auto();
		Luxe_Theme_Guard_Learning::activate();
	}

	/**
	 * Allow WordPress to apply official Flatsome packages.
	 */
	public static function arm_auto() {
		$auto = get_site_option( self::AUTO_SITE, array() );
		if ( ! is_array( $auto ) ) {
			$auto = array();
		}
		if ( ! in_array( Luxe_Theme_Guard_Signals::THEME, $auto, true ) ) {
			$auto[] = Luxe_Theme_Guard_Signals::THEME;
			update_site_option( self::AUTO_SITE, $auto );
		}
	}

	/**
	 * Stop asking WordPress to auto-update Flatsome. Other themes are left alone.
	 */
	public static function disarm_auto() {
		$auto = get_site_option( self::AUTO_SITE, array() );
		if ( ! is_array( $auto ) ) {
			return;
		}
		$auto = array_values( array_diff( $auto, array( Luxe_Theme_Guard_Signals::THEME ) ) );
		update_site_option( self::AUTO_SITE, $auto );
	}

	/**
	 * Clear 24/7 cron. Leave other plugins' events alone.
	 */
	public static function deactivate() {
		Luxe_Theme_Guard_Learning::deactivate();
	}

	/**
	 * WordPress automatic updates: Flatsome parent only.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme item.
	 * @return bool
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) ) {
			return $update;
		}
		$slug = '';
		if ( is_object( $item ) && ! empty( $item->theme ) ) {
			$slug = (string) $item->theme;
		} elseif ( is_array( $item ) && ! empty( $item['theme'] ) ) {
			$slug = (string) $item['theme'];
		}
		if ( Luxe_Theme_Guard_Signals::THEME === $slug ) {
			return true;
		}
		return $update;
	}

	/**
	 * Admin button.
	 */
	public function maybe_manual() {
		if ( empty( $_POST['luxe_theme_guard_run'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'luxe_theme_guard_run' );
		delete_transient( self::LOCK );
		$result = $this->run( true );
		$green  = isset( $result['green'] ) ? (int) $result['green'] : 0;
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$note   = ( ! empty( $result['snap']['update_note'] ) && $green !== $total ) ? ' ' . $result['snap']['update_note'] : '';
		add_settings_error(
			'luxe_theme_guard',
			'ran',
			sprintf(
				/* translators: 1: green, 2: total, 3: update note */
				__( 'Theme Guard finished. %1$d / %2$d signals green.%3$s', 'luxe-theme-guard' ),
				$green,
				$total,
				$note
			),
			$green === $total && $total > 0 ? 'updated' : 'notice-warning'
		);
	}

	/**
	 * Refresh updates, maybe upgrade Flatsome, rebuild signals.
	 *
	 * @param bool $manual Manual.
	 * @return array
	 */
	public function run( $manual = false ) {
		if ( get_transient( self::LOCK ) ) {
			$last = Luxe_Theme_Guard_Signals::last();
			return $last ? $last : array();
		}
		set_transient( self::LOCK, 1, 120 );

		$heals = array();
		self::sync_saved_license();
		$watched        = $this->watch_official_version( true );
		$heals['watch'] = $watched ? $watched : 'cached-or-unavailable';
		$heals['fetch'] = $this->fetch_official_package();
		$watched        = $this->highest_watched( $watched );

		$snap = Luxe_Theme_Guard_Signals::snapshot( $watched );
		if ( Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_update' ) && ! empty( $snap['can_upgrade'] ) ) {
			$heals['upgrade'] = $this->upgrade_flatsome();
			$snap             = Luxe_Theme_Guard_Signals::snapshot( $watched );
			if ( ! empty( $heals['upgrade']['ok'] ) && Luxe_Theme_Guard_Plugin::instance()->enabled( 'auto_purge' ) ) {
				$heals['purge'] = $this->purge();
			}
		}

		$board = Luxe_Theme_Guard_Signals::build( $snap, $heals );
		$board['source']      = 'manual';
		$board['focus']       = 'upgrade';
		$board['focus_label'] = 'Check and update Flatsome now';
		update_option( Luxe_Theme_Guard_Signals::OPTION_LAST, $board, false );
		$this->push_log( $board );
		delete_transient( self::LOCK );
		return $board;
	}

	/**
	 * @return array
	 */
	public function attempt_upgrade() {
		return $this->upgrade_flatsome();
	}

	/**
	 * @return string[]
	 */
	public function purge_caches() {
		return $this->purge();
	}

	/**
	 * @return array
	 */
	private function upgrade_flatsome() {
		if ( ! current_user_can( 'update_themes' ) && ! wp_doing_cron() ) {
			return array( 'ok' => false, 'detail' => 'not-allowed' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			return array( 'ok' => false, 'detail' => 'upgrader-missing' );
		}
		add_filter( 'filesystem_method', array( $this, 'force_direct' ), 99 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_official_package' ), 10, 4 );
		$skin     = class_exists( 'Automatic_Upgrader_Skin' ) ? new Automatic_Upgrader_Skin() : new WP_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( Luxe_Theme_Guard_Signals::THEME );
		remove_filter( 'upgrader_pre_download', array( $this, 'download_official_package' ), 10 );
		remove_filter( 'filesystem_method', array( $this, 'force_direct' ), 99 );
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'     => false,
				'detail' => $result->get_error_message(),
			);
		}
		return array(
			'ok'     => ( false !== $result ),
			'detail' => $result ? 'flatsome-updated' : 'no-package-or-already-current',
		);
	}

	/**
	 * Download the UX Themes package with the saved bearer token.
	 * WordPress core would request that URL without the token.
	 *
	 * @param mixed  $reply      Existing reply.
	 * @param string $package    Package URL.
	 * @param object $upgrader   Upgrader.
	 * @param array  $hook_extra Extra.
	 * @return mixed
	 */
	public function download_official_package( $reply, $package, $upgrader = null, $hook_extra = array() ) {
		unset( $upgrader, $hook_extra );
		if ( false !== $reply ) {
			return $reply;
		}
		$path = (string) parse_url( (string) $package, PHP_URL_PATH );
		if ( '/v1/token/download' !== $path || ! Luxe_Theme_Guard_Signals::uxthemes_url_allowed( $package ) ) {
			return $reply;
		}
		$token = function_exists( 'get_option' ) ? (string) get_option( self::OPTION_TOKEN, '' ) : '';
		if ( strlen( $token ) < 20 || ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_tempnam' ) ) {
			return new WP_Error( 'ltg_token', 'UX Themes did not return a download token.' );
		}
		$referer  = $this->uxthemes_referer();
		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 60,
				'redirection' => 3,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Referer'       => $referer,
					'Accept'        => 'application/zip',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code || strlen( $body ) < 100000 || 'PK' !== substr( $body, 0, 2 ) ) {
			$parsed = Luxe_Theme_Guard_Signals::interpret_uxthemes_response( $code, $body );
			return new WP_Error( 'ltg_download', $parsed['detail'] ? $parsed['detail'] : 'UX Themes did not return a Flatsome zip.' );
		}
		$tmp = wp_tempnam( 'flatsome.zip' );
		if ( ! $tmp || false === file_put_contents( $tmp, $body ) ) {
			return new WP_Error( 'ltg_download', 'Could not store the official Flatsome zip.' );
		}
		if ( ! $this->zip_is_flatsome( $tmp ) ) {
			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $tmp );
			} else {
				unlink( $tmp );
			}
			return new WP_Error( 'ltg_download', 'UX Themes returned a zip that is not the Flatsome parent theme.' );
		}
		return $tmp;
	}

	/**
	 * @param string $file Zip path.
	 * @return bool
	 */
	private function zip_is_flatsome( $file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return true;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return false;
		}
		$css = $zip->getFromName( 'flatsome/style.css' );
		if ( false === $css ) {
			$css = $zip->getFromName( 'style.css' );
		}
		$zip->close();
		if ( ! is_string( $css ) || '' === $css ) {
			return false;
		}
		$version = Luxe_Theme_Guard_Signals::parse_style_version( $css );
		return '' !== $version && false !== stripos( substr( $css, 0, 800 ), 'Flatsome' );
	}

	/**
	 * @return string[]
	 */
	private function purge() {
		$did = array();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$did[] = 'wp_cache_flush';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$did[] = 'litespeed_purge_all';
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
			$did[] = 'LiteSpeed\\Purge::purge_all';
		}
		return $did;
	}

	/**
	 * @param array $board Board.
	 */
	private function push_log( $board ) {
		$log = get_option( Luxe_Theme_Guard_Signals::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'     => isset( $board['at'] ) ? $board['at'] : time(),
				'green'  => isset( $board['green'] ) ? $board['green'] : 0,
				'total'  => isset( $board['total'] ) ? $board['total'] : 0,
				'band'   => isset( $board['band'] ) ? $board['band'] : '',
				'source' => 'manual',
				'focus'  => 'Check and update Flatsome now',
				'level'  => ( ! empty( $board['green'] ) && isset( $board['total'] ) && (int) $board['green'] === (int) $board['total'] ) ? 'green' : 'red',
			)
		);
		update_option( Luxe_Theme_Guard_Signals::OPTION_LOG, array_slice( $log, 0, 30 ), false );
	}
}
