<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KEEP / LAYERED / PARK conductor.
 * Deactivates PARK duplicates only. Never deletes. Never touches KEEP.
 */
class Luxe_Stack_Harmony_Conductor {

	const OPTION_LOG = 'luxe_stack_harmony_cancel_log';

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
	 * Register cancel + mute.
	 */
	public function boot() {
		if ( Luxe_Stack_Harmony_Plugin::instance()->enabled( 'cancel_duplicates' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'cancel_now' ), 2 );
			add_action( 'init', array( __CLASS__, 'cancel_now' ), 2 );
		}
		if ( Luxe_Stack_Harmony_Plugin::instance()->enabled( 'mute_park_hooks' ) ) {
			add_action( 'plugins_loaded', array( $this, 'mute_park_hooks' ), 99 );
		}
	}

	/**
	 * Needles that must never be deactivated.
	 *
	 * @return string[]
	 */
	public static function keep_needles() {
		return array(
			'luxe-blog-master-command-center',
			'blog master command center',
			'reader-love',
			'hard rescue admin',
			'safe trash',
			'mission control stay repair',
			'richard brummer seo mission control',
			'keyword intelligence',
			'luxe seo score repair',
			'luxe theme guard',
			'luxe public finish',
			'affiliate product scout',
			'rank math seo',
			'woocommerce/woocommerce.php',
			'wzone',
			'deletion shield',
			'view on amazon bridge',
			'amazon correct tag',
			'amazon + mobile recovery',
			'amazon + mobile',
			'mirror master',
			'litespeed cache',
			'wordfence',
			'snapshot pro',
			'classic editor',
			'contact form 7',
			'site kit',
			'yith woocommerce wishlist',
			'luxe-stack-harmony',
			'luxe stack harmony',
		);
	}

	/**
	 * Duplicate / writer engines that may be deactivated (never deleted).
	 *
	 * @return string[]
	 */
	public static function park_needles() {
		return array(
			'hostinger ai',
			'hostinger-ai',
			'bad content eraser',
			'master plugin orchestrator',
			'blog master green',
			'luxe-blog-master-green',
			'luxe blog master green',
		);
	}

	/**
	 * @param string $file Plugin basename.
	 * @param string $name Plugin Name header.
	 * @return string KEEP|LAYERED|PARK
	 */
	public static function classify( $file, $name ) {
		if ( self::is_keep( $file, $name ) ) {
			return 'KEEP';
		}
		$hay = strtolower( (string) $file . ' ' . (string) $name );
		if ( self::is_park( $hay ) || self::is_second_repair_engine( $file, $name ) ) {
			return 'PARK';
		}
		return 'LAYERED';
	}

	/**
	 * @param string $file Basename.
	 * @param string $name Plugin Name.
	 * @return bool
	 */
	public static function is_keep( $file, $name ) {
		if ( self::is_second_repair_engine( $file, $name ) ) {
			return false;
		}
		$file = strtolower( (string) $file );
		$name = strtolower( (string) $name );
		if ( 'woocommerce/woocommerce.php' === $file || 'woocommerce' === $name ) {
			return true;
		}
		$hay = $file . ' ' . $name;
		foreach ( self::keep_needles() as $needle ) {
			if ( false !== strpos( $hay, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $hay Lowercase file + name.
	 * @return bool
	 */
	public static function is_park( $hay ) {
		foreach ( self::park_needles() as $needle ) {
			if ( false !== strpos( $hay, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A second Blog Master repair engine (Green Board or a Command Center copy
	 * outside the canonical folder). README: do not run two repair engines.
	 *
	 * @param string $file Basename.
	 * @param string $name Plugin Name.
	 * @return bool
	 */
	public static function is_second_repair_engine( $file, $name ) {
		$hay = strtolower( (string) $file . ' ' . (string) $name );
		if ( false !== strpos( $hay, 'blog master green' ) || false !== strpos( $hay, 'luxe-blog-master-green' ) ) {
			return true;
		}
		$is_command = ( false !== strpos( $hay, 'blog master command center' ) || false !== strpos( $hay, 'luxe-blog-master-command-center' ) );
		if ( $is_command && false === strpos( strtolower( (string) $file ), 'luxe-blog-master-command-center/' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<string,string> $catalog file => name.
	 * @param string[]             $active  Active basenames.
	 * @return string[] Basenames to deactivate.
	 */
	public static function cancel_list( array $catalog, array $active ) {
		$command_center_on = false;
		foreach ( $active as $file ) {
			if ( false !== strpos( strtolower( (string) $file ), 'luxe-blog-master-command-center/' ) ) {
				$command_center_on = true;
				break;
			}
		}

		$cancel = array();
		$self   = defined( 'LUXE_STACK_HARMONY_BASENAME' ) ? LUXE_STACK_HARMONY_BASENAME : 'luxe-stack-harmony/luxe-stack-harmony.php';
		foreach ( $active as $file ) {
			$file = (string) $file;
			if ( $file === $self ) {
				continue;
			}
			$name    = isset( $catalog[ $file ] ) ? (string) $catalog[ $file ] : '';
			$verdict = self::classify( $file, $name );
			if ( 'KEEP' === $verdict ) {
				continue;
			}
			if ( 'PARK' === $verdict ) {
				$cancel[] = $file;
			}
			if ( $command_center_on && self::is_second_repair_engine( $file, $name ) ) {
				$cancel[] = $file;
			}
		}
		return array_values( array_unique( $cancel ) );
	}

	/**
	 * Deactivate PARK / second repair engines. Never KEEP. Never delete.
	 */
	public static function cancel_now() {
		if ( ! function_exists( 'get_plugins' ) ) {
			$path = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/plugin.php' : '';
			if ( $path && is_readable( $path ) ) {
				require_once $path;
			}
		}
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'deactivate_plugins' ) ) {
			return array();
		}

		$raw     = get_plugins();
		$catalog = array();
		foreach ( $raw as $file => $data ) {
			$catalog[ $file ] = isset( $data['Name'] ) ? (string) $data['Name'] : '';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$cancel = self::cancel_list( $catalog, $active );
		$safe   = array();
		foreach ( $cancel as $file ) {
			$name = isset( $catalog[ $file ] ) ? $catalog[ $file ] : '';
			if ( 'KEEP' === self::classify( $file, $name ) ) {
				continue;
			}
			$safe[] = $file;
		}
		if ( $safe ) {
			deactivate_plugins( $safe, true );
			self::push_log( $safe, $catalog );
		}
		return $safe;
	}

	/**
	 * Soft-mute PARK writers that already loaded this request.
	 */
	public function mute_park_hooks() {
		if ( class_exists( 'Hostinger_Ai_Assistant' ) || defined( 'HOSTINGER_AI_PLUGIN_VERSION' ) || defined( 'HOSTINGER_AI_VERSION' ) ) {
			remove_all_filters( 'hostinger_ai_content' );
		}
	}

	/**
	 * @param string[]             $files   Files.
	 * @param array<string,string> $catalog Catalog.
	 */
	private static function push_log( array $files, array $catalog ) {
		$log = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'at'    => time(),
				'files' => $files,
				'names' => array_map(
					function ( $file ) use ( $catalog ) {
						return isset( $catalog[ $file ] ) ? $catalog[ $file ] : $file;
					},
					$files
				),
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, 20 ), false );
	}

	/**
	 * @return array
	 */
	public static function log() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}
}
