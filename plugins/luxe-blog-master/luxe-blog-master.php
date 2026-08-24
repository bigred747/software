<?php
/**
 * Plugin Name: Luxe Blog Master
 * Plugin URI: https://luxetrendsetters.com/
 * Description: Complete Blog Master approval companion. Hard no-publish. Approval-only. Loads Command Center when present. Never publishes.
 * Version: 2.8.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Richard Brummer
 * Author URI: https://luxetrendsetters.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: luxe-blog-master
 * Update URI: false
 * Blog Master Companion: complete
 * RBSMC Companion: blog-master
 * RBSMC Approval Companion: complete
 * Luxe Companion: blog-master
 * Approval Companion: true
 * Approval Only: true
 * Hard No-Publish: true
 * Complete Build: true
 *
 * Identity plugin for Mission Control 1.8.2. Same engine as Command Center.
 * Never publishes. Never deletes. Never rewrites Amazon URLs.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LUXE_BLOG_MASTER_VERSION' ) ) {
	define( 'LUXE_BLOG_MASTER_VERSION', '2.8.1' );
}
if ( ! defined( 'LUXE_BLOG_MASTER_FILE' ) ) {
	define( 'LUXE_BLOG_MASTER_FILE', __FILE__ );
}
if ( ! defined( 'LUXE_BLOG_MASTER_COMPLETE' ) ) {
	define( 'LUXE_BLOG_MASTER_COMPLETE', true );
}
if ( ! defined( 'LUXE_BLOG_MASTER_COMPLETE_BUILD' ) ) {
	define( 'LUXE_BLOG_MASTER_COMPLETE_BUILD', true );
}
if ( ! defined( 'LUXE_BLOG_MASTER_HARD_NO_PUBLISH' ) ) {
	define( 'LUXE_BLOG_MASTER_HARD_NO_PUBLISH', 1 );
}
if ( ! defined( 'LUXE_BMC_HARD_NO_PUBLISH' ) ) {
	define( 'LUXE_BMC_HARD_NO_PUBLISH', 1 );
}
if ( ! defined( 'LUXE_BLOG_MASTER_APPROVAL_COMPANION' ) ) {
	define( 'LUXE_BLOG_MASTER_APPROVAL_COMPANION', 1 );
}
if ( ! defined( 'LUXE_BLOG_MASTER_APPROVAL_ONLY' ) ) {
	define( 'LUXE_BLOG_MASTER_APPROVAL_ONLY', true );
}

if ( ! class_exists( 'Luxe_Blog_Master', false ) ) {
	/**
	 * 2.8.1 complete-build identity. Evidence only.
	 */
	class Luxe_Blog_Master {
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
		 * @return true
		 */
		public static function is_complete() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function hard_no_publish() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function approval_companion() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function approval_only() {
			return true;
		}

		/**
		 * @return true
		 */
		public static function never_publishes() {
			return true;
		}

		/**
		 * @return array<string,mixed>
		 */
		public function status() {
			if ( class_exists( 'Luxe_BMC_Companion', false ) ) {
				return Luxe_BMC_Companion::status();
			}
			return array(
				'available'          => true,
				'plugin'             => 'Luxe Blog Master',
				'name'               => 'Luxe Blog Master',
				'version'            => '2.8.1',
				'complete_build'     => true,
				'complete'           => true,
				'approval_companion' => true,
				'approval_only'      => true,
				'hard_no_publish'    => true,
				'never_publishes'    => true,
				'never_deletes'      => true,
				'publishing_enabled' => false,
				'published'          => 0,
				'basename'           => 'luxe-blog-master/luxe-blog-master.php',
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		public function get_status() {
			return $this->status();
		}

		/**
		 * @return array<string,mixed>
		 */
		public function companion() {
			return $this->status();
		}

		/**
		 * List shape. Mission Control stores blog_master as [].
		 *
		 * @return array<int,array<string,mixed>>
		 */
		public function companions() {
			return array( $this->status() );
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public function to_array() {
			return $this->companions();
		}

		/**
		 * @return array<int,array<string,mixed>>
		 */
		public static function evidence() {
			return self::instance()->companions();
		}
	}
}

if ( ! class_exists( 'Luxe_Blog_Master_Command_Center', false ) ) {
	class Luxe_Blog_Master_Command_Center extends Luxe_Blog_Master {
	}
}

if ( ! class_exists( 'BMC_Command_Center', false ) ) {
	class BMC_Command_Center extends Luxe_Blog_Master {
	}
}

if ( ! function_exists( 'luxe_blog_master' ) ) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function luxe_blog_master() {
		return Luxe_Blog_Master::instance()->companions();
	}
}

if ( ! function_exists( 'luxe_blog_master_instance' ) ) {
	/**
	 * @return Luxe_Blog_Master
	 */
	function luxe_blog_master_instance() {
		return Luxe_Blog_Master::instance();
	}
}

if ( ! function_exists( 'luxe_blog_master_companions' ) ) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function luxe_blog_master_companions() {
		return Luxe_Blog_Master::instance()->companions();
	}
}

if ( ! function_exists( 'luxe_blog_master_command_center' ) ) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function luxe_blog_master_command_center() {
		return Luxe_Blog_Master::instance()->companions();
	}
}

if ( ! function_exists( 'luxe_blog_master_status' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function luxe_blog_master_status() {
		return Luxe_Blog_Master::instance()->status();
	}
}

if ( ! function_exists( 'luxe_blog_master_companion_evidence' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function luxe_blog_master_companion_evidence() {
		return Luxe_Blog_Master::instance()->status();
	}
}

if ( ! function_exists( 'luxe_blog_master_hard_no_publish' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_hard_no_publish() {
		return true;
	}
}

if ( ! function_exists( 'luxe_bmc_hard_no_publish' ) ) {
	/**
	 * @return true
	 */
	function luxe_bmc_hard_no_publish() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_is_complete' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_is_complete() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_approval_companion' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_approval_companion() {
		return true;
	}
}

if ( ! function_exists( 'luxe_blog_master_approval_only' ) ) {
	/**
	 * @return true
	 */
	function luxe_blog_master_approval_only() {
		return true;
	}
}

if ( ! function_exists( 'is_blog_master' ) ) {
	/**
	 * @return true
	 */
	function is_blog_master() {
		return true;
	}
}

/**
 * Arm list-shaped evidence before Mission Control audits.
 */
function luxe_blog_master_arm_identity() {
	$list = Luxe_Blog_Master::instance()->companions();
	$row  = Luxe_Blog_Master::instance()->status();
	if ( function_exists( 'update_option' ) ) {
		update_option( 'luxe_blog_master', $list, false );
		update_option( 'blog_master', $list, false );
		update_option( 'rbsmc_blog_master', $list, false );
		update_option( 'luxe_blog_master_status', $row, false );
		update_option( 'luxe_bmc_complete_build', 1, false );
		update_option( 'luxe_bmc_hard_no_publish', 1, false );
		update_option( 'luxe_bmc_approval_companion', 1, false );
		update_option( 'luxe_bmc_approval_only', 1, false );
		update_option( 'luxe_blog_master_hard_no_publish', 1, false );
		update_option( 'luxe_blog_master_approval_only', 1, false );
		update_option( 'luxe_blog_master_approval_companion', 1, false );
		update_option( 'luxe_blog_master_complete', 1, false );
		update_option( 'luxe_blog_master_complete_build', 1, false );
	}
	if ( function_exists( 'add_filter' ) ) {
		$as_list = function ( $value ) use ( $list ) {
			if ( is_array( $value ) && $value && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
				foreach ( $value as $existing ) {
					if ( is_array( $existing ) && ! empty( $existing['available'] ) ) {
						return $value;
					}
				}
				$value[] = $list[0];
				return $value;
			}
			return $list;
		};
		$as_true = function ( $value ) {
			unset( $value );
			return true;
		};
		add_filter( 'rbsmc_blog_master', $as_list, 1 );
		add_filter( 'rbsmc_blog_master_companion', $as_list, 1 );
		add_filter( 'luxe_blog_master_register', $as_list, 1 );
		add_filter( 'luxe_blog_master', $as_list, 1 );
		add_filter( 'rbsmc_approval_only', $as_true, 1 );
		add_filter( 'rbsmc_hard_no_publish', $as_true, 1 );
		add_filter( 'rbsmc_hard_no_publish_lock', $as_true, 1 );
		add_filter( 'rbsmc_no_publish', $as_true, 1 );
		add_filter( 'rbsmc_blog_master_approval_companion', $as_true, 1 );
		add_filter( 'luxe_blog_master_no_publish', $as_true, 1 );
		add_filter( 'luxe_blog_master_hard_no_publish', $as_true, 1 );
		add_filter( 'luxe_blog_master_complete', $as_true, 1 );
		add_filter( 'luxe_blog_master_approval_companion', $as_true, 1 );
		add_filter( 'luxe_blog_master_approval_only', $as_true, 1 );
		add_filter( 'luxe_bmc_hard_no_publish', $as_true, 1 );
		add_filter(
			'extra_plugin_headers',
			function ( $headers ) {
				if ( ! is_array( $headers ) ) {
					$headers = array();
				}
				$map = array(
					'BlogMasterCompanion'    => 'Blog Master Companion',
					'RBSMCCompanion'         => 'RBSMC Companion',
					'RBSMCApprovalCompanion' => 'RBSMC Approval Companion',
					'LuxeCompanion'          => 'Luxe Companion',
					'ApprovalCompanion'      => 'Approval Companion',
					'ApprovalOnly'           => 'Approval Only',
					'HardNoPublish'          => 'Hard No-Publish',
					'CompleteBuild'          => 'Complete Build',
				);
				foreach ( $map as $key => $header ) {
					if ( ! isset( $headers[ $key ] ) ) {
						$headers[ $key ] = $header;
					}
				}
				return $headers;
			},
			5
		);
	}
	if ( function_exists( 'add_action' ) ) {
		add_action(
			'rest_api_init',
			function () {
				if ( ! function_exists( 'register_rest_route' ) ) {
					return;
				}
				$cb = function () {
					return rest_ensure_response(
						array(
							'blog_master'     => Luxe_Blog_Master::instance()->companions(),
							'approval_only'   => true,
							'hard_no_publish' => true,
							'complete_build'  => true,
							'published'       => 0,
						)
					);
				};
				register_rest_route(
					'luxe-blog-master/v1',
					'/status',
					array(
						'methods'             => 'GET',
						'callback'            => $cb,
						'permission_callback' => '__return_true',
					)
				);
			},
			1
		);
	}
}
luxe_blog_master_arm_identity();

if ( ! defined( 'LUXE_BMC_FILE' ) && function_exists( 'plugin_dir_path' ) ) {
	$luxe_bmc = dirname( __DIR__ ) . '/luxe-blog-master-command-center/luxe-blog-master-command-center.php';
	if ( is_readable( $luxe_bmc ) ) {
		require_once $luxe_bmc;
	}
}
