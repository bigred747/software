<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivates redundant plugins from the live LuxeTrendsetters inventory.
 * Never deletes files. Never touches the keep list.
 */
class Luxe_Plugin_Consolidator {

	const OPTION = 'luxe_consolidator_last_run';

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
	 * Plugin titles that must stay active. Matched after punctuation is stripped.
	 *
	 * @return array
	 */
	public static function keep_titles() {
		return array(
			'WooCommerce',
			'Rank Math SEO',
			'Site Kit by Google',
			'Wordfence Security',
			'LiteSpeed Cache',
			'Richard Brummer SEO Mission Control',
			'Richard Brummer SEO Mission Control Stay Repair',
			'Contact Form 7',
			'Classic Editor',
			'WZone - WooCommerce Amazon Affiliates',
			'Luxe Amazon View on Amazon Bridge',
			'Amazon Affiliate Autopilot for WooCommerce',
			'YITH WooCommerce Wishlist',
			'Snapshot Pro',
			'Luxe Hard Rescue Admin Cleaner',
			'Luxe Hard Rescue Safe Trash Extension',
			'Luxe WZone Deletion Shield',
			'Luxe Site Hardening Guard',
			'Luxe Rank Math Schema Guard',
			'Luxe Simple Homepage Mirror Master',
			'Luxe WOW Homepage Rotator Ultra Premium Edition',
			'Luxe Performance Link Guardian Suite',
			'Luxe Unified Site Guardian Green Signals Safe Rebuild',
			'Luxe Category Authority Lock Safe Activation',
			'Link Whisper Premium',
			'24/7 Smart 90-Day Commission Tracker ULTIMATE',
			'Luxe Plugin Consolidator',
		);
	}

	/**
	 * Plugin titles to deactivate. 24/7 overlap scanners, one-time cleaners, Hostinger AI.
	 *
	 * @return array
	 */
	public static function deactivate_titles() {
		return array(
			'Action Scheduler Healer PRO',
			'Hostinger AI',
			'Luxe Affiliate Product Scout',
			'Luxe AI Readiness Autopilot 100',
			'Luxe Amazon Mobile Recovery',
			'Luxe Amazon Correct Tag Green Signals',
			'Luxe Bad Content Eraser Safe One Time Cleanup',
			'Luxe Blog Master Command Center',
			'Luxe Comment Shield Learning Guard',
			'Luxe CRON Mobilizer Pro',
			'Luxe Duplicate Plugin Cleaner',
			'Luxe Index Recovery Guard',
			'Luxe Keyword Intelligence Autopilot',
			'Luxe Master Plugin Orchestrator',
			'Luxe Performance Core Fusion',
			'Luxe Reader Love Unified',
			'Transients Manager',
		);
	}

	/**
	 * Folder names that must never be deactivated even if a title match fails.
	 *
	 * @return array
	 */
	public static function keep_folders() {
		return array(
			'woocommerce',
			'seo-by-rank-math',
			'google-site-kit',
			'wordfence',
			'litespeed-cache',
			'richard-brummer-seo-mission-control',
			'richard-brummer-seo-mission-control-stay',
			'luxe-plugin-consolidator',
			'contact-form-7',
			'classic-editor',
			'yith-woocommerce-wishlist',
			'woocommerce-amazon-affiliates',
			'woozone',
			'wzone',
			'snapshot',
			'snapshot-pro',
			'link-whisper',
			'link-whisper-premium',
		);
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public static function normalize_title( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );
		$title = strtolower( $title );
		$title = str_replace( array( '–', '—', '‑', '-', '+', '/' ), ' ', $title );
		$title = preg_replace( '/[^a-z0-9]+/', ' ', $title );
		return trim( preg_replace( '/\s+/', ' ', (string) $title ) );
	}

	/**
	 * Exact normalized title match. Mission Control must not match Stay Repair.
	 *
	 * @param string $installed Installed plugin Name.
	 * @param array  $titles    Candidate titles.
	 * @return bool
	 */
	public static function title_in_list( $installed, $titles ) {
		$have = self::normalize_title( $installed );
		if ( '' === $have ) {
			return false;
		}
		foreach ( $titles as $title ) {
			$want = self::normalize_title( $title );
			if ( $want === $have ) {
				return true;
			}
			if ( '' !== $want && 0 === strpos( $have, $want . ' ' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run on plugin activate.
	 */
	public static function activate() {
		self::instance()->run( 'activation' );
	}

	/**
	 * Admin UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_luxe_consol_run', array( $this, 'handle_run' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_management_page(
			'Luxe Plugin Consolidator',
			'Luxe Consolidator',
			'activate_plugins',
			'luxe-consolidator',
			array( $this, 'render' )
		);
	}

	/**
	 * Manual run.
	 */
	public function handle_run() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'luxe_consol_run' );
		$this->run( 'manual' );
		wp_safe_redirect( admin_url( 'tools.php?page=luxe-consolidator&done=1' ) );
		exit;
	}

	/**
	 * Notice after activation.
	 */
	public function notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$run = get_option( self::OPTION );
		if ( ! is_array( $run ) || empty( $run['deactivated'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ( 'plugins' !== $screen->id && false === strpos( (string) $screen->id, 'luxe-consolidator' ) ) ) {
			return;
		}
		$count = count( $run['deactivated'] );
		echo '<div class="notice notice-success"><p><strong>Luxe Consolidator:</strong> deactivated ' . esc_html( (string) $count ) . ' unused plugin(s). Files were not deleted. <a href="' . esc_url( admin_url( 'tools.php?page=luxe-consolidator' ) ) . '">Review list</a>.</p></div>';
	}

	/**
	 * Admin page.
	 */
	public function render() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$plan = $this->plan();
		$run  = get_option( self::OPTION, array() );
		echo '<div class="wrap"><h1>Luxe Plugin Consolidator 1.1.0</h1>';
		echo '<p>Built from the live 43-plugin list. Keeps WooCommerce, WZone, Rank Math, Site Kit, Wordfence, LiteSpeed, Mission Control 1.8.2, Stay Repair, Amazon Bridge, and backups. Turns off overlapping 24/7 Luxe scanners. Does not delete files and does not publish content.</p>';

		echo '<h2>Will deactivate now (' . esc_html( (string) count( $plan['deactivate'] ) ) . ')</h2>';
		if ( $plan['deactivate'] ) {
			echo '<ol>';
			foreach ( $plan['deactivate'] as $row ) {
				echo '<li>' . esc_html( $row['name'] ) . ' <code>' . esc_html( $row['file'] ) . '</code></li>';
			}
			echo '</ol>';
		} else {
			echo '<p>None of the overlap scanners are active.</p>';
		}

		echo '<h2>Keep active (' . esc_html( (string) count( $plan['keep'] ) ) . ')</h2><ol>';
		foreach ( $plan['keep'] as $row ) {
			echo '<li>' . esc_html( $row['name'] ) . '</li>';
		}
		echo '</ol>';

		if ( $plan['unknown'] ) {
			echo '<h2>Leave alone (not in keep or deactivate lists)</h2><ol>';
			foreach ( $plan['unknown'] as $row ) {
				echo '<li>' . esc_html( $row['name'] ) . ' <code>' . esc_html( $row['file'] ) . '</code></li>';
			}
			echo '</ol>';
		}

		if ( ! empty( $run['deactivated'] ) ) {
			echo '<h2>Last run</h2><p>' . esc_html( gmdate( 'Y-m-d H:i:s', absint( $run['at'] ) ) ) . ' UTC via ' . esc_html( (string) $run['via'] ) . '</p><ol>';
			foreach ( $run['deactivated'] as $file ) {
				$label = is_array( $file ) ? $file['name'] . ' (' . $file['file'] . ')' : $file;
				echo '<li><code>' . esc_html( (string) $label ) . '</code></li>';
			}
			echo '</ol>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'luxe_consol_run' );
		echo '<input type="hidden" name="action" value="luxe_consol_run" />';
		echo '<p><button class="button button-primary" type="submit">Deactivate the overlap list now</button></p></form></div>';
	}

	/**
	 * Classify active plugins.
	 *
	 * @return array
	 */
	public function plan() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );
		$keep   = array();
		$off    = array();
		$unk    = array();
		foreach ( $active as $file ) {
			$name   = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] : $file;
			$parts  = explode( '/', (string) $file );
			$folder = strtolower( (string) $parts[0] );
			$row    = array(
				'file' => $file,
				'name' => $name,
			);
			if ( in_array( $folder, self::keep_folders(), true ) || self::title_in_list( $name, self::keep_titles() ) ) {
				$keep[] = $row;
				continue;
			}
			if ( self::title_in_list( $name, self::deactivate_titles() ) ) {
				$off[] = $row;
				continue;
			}
			$unk[] = $row;
		}
		return array(
			'keep'       => $keep,
			'deactivate' => $off,
			'unknown'    => $unk,
		);
	}

	/**
	 * Deactivate matches.
	 *
	 * @param string $via activation|manual.
	 */
	public function run( $via ) {
		$plan = $this->plan();
		$off  = array();
		foreach ( $plan['deactivate'] as $row ) {
			$off[] = $row['file'];
		}
		$off = array_values( array_unique( $off ) );
		if ( $off ) {
			deactivate_plugins( $off, true );
		}
		update_option(
			self::OPTION,
			array(
				'at'          => time(),
				'via'         => $via,
				'deactivated' => $plan['deactivate'],
				'kept'        => wp_list_pluck( $plan['keep'], 'name' ),
			),
			false
		);
	}
}
