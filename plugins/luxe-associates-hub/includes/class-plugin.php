<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Plugin' ) ) {
	return;
}

/**
 * Options and admin saves. Admin only. No public hooks.
 */
class LAH_Plugin {

	const OPTION = 'lah_settings';

	const LOG = 'lah_link_log';

	const LAST = 'lah_last_link';

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
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'tracking_id'     => '',
			'payment_done'    => 0,
			'payment_done_at' => 0,
			'started_at'      => 0,
			'earnings_note'   => '',
		);
	}

	/**
	 * Store defaults. Do not call Amazon.
	 */
	public static function activate() {
		$settings = get_option( self::OPTION, null );
		if ( ! is_array( $settings ) ) {
			$settings = self::defaults();
		} else {
			$settings = wp_parse_args( $settings, self::defaults() );
		}
		if ( empty( $settings['started_at'] ) ) {
			$settings['started_at'] = time();
		}
		if ( null === get_option( self::OPTION, null ) ) {
			add_option( self::OPTION, $settings, '', false );
		} else {
			update_option( self::OPTION, $settings, false );
		}
	}

	/**
	 * Leave options in place so a re-activate keeps the first-week window.
	 */
	public static function deactivate() {
	}

	/**
	 * Register admin modules.
	 */
	public function boot() {
		if ( class_exists( 'LAH_Public' ) ) {
			LAH_Public::instance()->boot();
		}
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybe_save' ) );
			if ( class_exists( 'LAH_Admin' ) ) {
				LAH_Admin::instance()->boot();
			}
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function settings() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = wp_parse_args( $stored, self::defaults() );
		$settings['tracking_id'] = LAH_Links::tracking_id( $settings['tracking_id'] );
		$settings['payment_done'] = empty( $settings['payment_done'] ) ? 0 : 1;
		$settings['payment_done_at'] = (int) $settings['payment_done_at'];
		$settings['started_at'] = (int) $settings['started_at'];
		$settings['earnings_note'] = LAH_Links::note( $settings['earnings_note'] );
		return $settings;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function log() {
		$log = get_option( self::LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @return array<string,mixed>
	 */
	public function last_link() {
		$last = get_option( self::LAST, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * Persist a posted admin action.
	 */
	public function maybe_save() {
		if ( empty( $_POST['lah_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'lah_save' );
		$action = sanitize_key( wp_unslash( $_POST['lah_action'] ) );
		if ( 'settings' === $action ) {
			$this->save_settings();
		} elseif ( 'link' === $action ) {
			$this->save_link();
		}
	}

	/**
	 * Save the tracking ID, the payment checkbox, and a short reminder.
	 */
	private function save_settings() {
		$settings = $this->settings();
		$posted   = isset( $_POST['tracking_id'] ) ? wp_unslash( $_POST['tracking_id'] ) : '';
		$posted   = is_string( $posted ) ? $posted : '';
		$tag      = LAH_Links::tracking_id( $posted );
		if ( '' !== trim( $posted ) && '' === $tag ) {
			$this->redirect( 'bad-tag' );
		}
		$note_raw = isset( $_POST['earnings_note'] ) ? wp_unslash( $_POST['earnings_note'] ) : '';
		$note_raw = is_string( $note_raw ) ? $note_raw : '';
		$note     = LAH_Links::note( $note_raw );
		if ( '' !== trim( wp_strip_all_tags( $note_raw ) ) && '' === $note ) {
			$this->redirect( 'note-blocked' );
		}
		$done = empty( $_POST['payment_done'] ) ? 0 : 1;
		$settings['tracking_id']   = $tag;
		$settings['earnings_note'] = $note;
		if ( $done && empty( $settings['payment_done'] ) ) {
			$settings['payment_done_at'] = time();
		}
		if ( ! $done ) {
			$settings['payment_done']    = 0;
			$settings['payment_done_at'] = 0;
		} else {
			$settings['payment_done'] = 1;
		}
		update_option( self::OPTION, $settings, false );
		$this->redirect( 'saved' );
	}

	/**
	 * Build one product link and optionally log it.
	 */
	private function save_link() {
		$settings = $this->settings();
		$tag      = $settings['tracking_id'];
		$asin_raw = isset( $_POST['asin'] ) ? wp_unslash( $_POST['asin'] ) : '';
		$asin_raw = is_string( $asin_raw ) ? $asin_raw : '';
		$asin     = LAH_Links::asin( $asin_raw );
		$title    = isset( $_POST['link_title'] ) ? sanitize_text_field( wp_unslash( $_POST['link_title'] ) ) : '';
		if ( strlen( $title ) > 180 ) {
			$title = substr( $title, 0, 180 );
		}
		if ( '' === $tag ) {
			$this->redirect( 'need-tag' );
		}
		$url = LAH_Links::product_url( $asin, $tag );
		if ( '' === $url ) {
			$this->redirect( 'bad-asin' );
		}
		$row = array(
			'asin'  => $asin,
			'url'   => $url,
			'title' => $title,
			'at'    => time(),
		);
		$this->write_option( self::LAST, $row );
		if ( ! empty( $_POST['log_share'] ) ) {
			$this->write_option( self::LOG, LAH_Links::push_log( $this->log(), $row ) );
			$this->redirect( 'logged' );
		}
		$this->redirect( 'built' );
	}

	/**
	 * @param string $key   Option.
	 * @param mixed  $value Value.
	 */
	private function write_option( $key, $value ) {
		if ( null === get_option( $key, null ) ) {
			add_option( $key, $value, '', false );
			return;
		}
		update_option( $key, $value, false );
	}

	/**
	 * @param string $code Notice code.
	 */
	private function redirect( $code ) {
		$url = add_query_arg(
			array(
				'page'       => 'luxe-associates-hub',
				'lah_notice' => $code,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
