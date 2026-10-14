<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools → Ultimate Authority, plus the guide editor box.
 */
class LUAE_Admin {

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
	 * Register admin hooks.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_guide' ), 20, 2 );
		add_filter( 'plugin_action_links_' . LUAE_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_post_luae_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_luae_sample', array( $this, 'handle_sample' ) );
		add_action( 'admin_post_luae_noindex', array( $this, 'handle_noindex' ) );
		add_action( 'admin_post_luae_settings', array( $this, 'handle_settings' ) );
		add_action( 'admin_post_luae_retire', array( $this, 'handle_retire' ) );
	}

	/**
	 * Settings page under Tools.
	 */
	public function menu() {
		add_management_page(
			__( 'Ultimate Authority', 'luxe-ultimate-authority' ),
			__( 'Ultimate Authority', 'luxe-ultimate-authority' ),
			'manage_options',
			'luxe-ultimate-authority',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		$load = ( 'tools_page_luxe-ultimate-authority' === $hook || 'post.php' === $hook || 'post-new.php' === $hook );
		if ( ! $load ) {
			return;
		}
		wp_enqueue_style( 'luae-admin', LUAE_URL . 'assets/admin.css', array(), LUAE_VERSION );
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=luxe-ultimate-authority' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-ultimate-authority' ) . '</a>' );
		return $links;
	}

	/**
	 * Guide editor box.
	 */
	public function meta_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_meta_box(
			'luae_authority',
			__( 'Luxe Authority', 'luxe-ultimate-authority' ),
			array( $this, 'render_meta_box' ),
			'post',
			'normal',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$modules = get_post_meta( $post->ID, LUAE_Plugin::MODULES, true );
		if ( ! is_array( $modules ) ) {
			$modules = LUAE_Post::empty_modules();
		}
		$report = get_post_meta( $post->ID, LUAE_Plugin::REPORT, true );
		if ( ! is_array( $report ) ) {
			$report = array();
		}
		wp_nonce_field( 'luae_guide_' . $post->ID, 'luae_guide_nonce' );
		include LUAE_DIR . 'templates/meta-box.php';
	}

	/**
	 * Save draft fields and approval flags. Does not touch post status or content.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 */
	public function save_guide( $post_id, $post ) {
		unset( $post );
		if ( ! isset( $_POST['luae_guide_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['luae_guide_nonce'] ) ), 'luae_guide_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$modules = LUAE_Post::empty_modules();
		$modules['verdict'] = isset( $_POST['luae_verdict'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_verdict'] ) ) : '';
		$modules['pros']    = isset( $_POST['luae_pros'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_pros'] ) ) : '';
		$modules['cons']    = isset( $_POST['luae_cons'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_cons'] ) ) : '';
		$modules['buy']     = isset( $_POST['luae_buy'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_buy'] ) ) : '';
		$modules['skip']    = isset( $_POST['luae_skip'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_skip'] ) ) : '';
		$modules['faq']     = isset( $_POST['luae_faq'] ) ? sanitize_textarea_field( wp_unslash( $_POST['luae_faq'] ) ) : '';
		$keys = array( 'design', 'performance', 'battery', 'value', 'overall' );
		foreach ( $keys as $key ) {
			$field = 'luae_score_' . $key;
			$modules['scores'][ $key ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		if ( ! empty( $_POST['luae_use_siblings'] ) ) {
			$report = get_post_meta( $post_id, LUAE_Plugin::REPORT, true );
			$siblings = is_array( $report ) && isset( $report['siblings'] ) && is_array( $report['siblings'] ) ? $report['siblings'] : array();
			$modules['compare'] = array(
				array( 'title' => '', 'url' => '' ),
				array( 'title' => '', 'url' => '' ),
				array( 'title' => '', 'url' => '' ),
			);
			$index = 0;
			foreach ( $siblings as $sibling ) {
				if ( $index > 2 || ! is_array( $sibling ) ) {
					break;
				}
				$modules['compare'][ $index ] = array(
					'title' => sanitize_text_field( isset( $sibling['title'] ) ? $sibling['title'] : '' ),
					'url'   => esc_url_raw( isset( $sibling['url'] ) ? $sibling['url'] : '' ),
				);
				$index++;
			}
		} else {
			for ( $i = 0; $i < 3; $i++ ) {
				$title_key = 'luae_compare_title_' . $i;
				$url_key   = 'luae_compare_url_' . $i;
				$modules['compare'][ $i ] = array(
					'title' => isset( $_POST[ $title_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $title_key ] ) ) : '',
					'url'   => isset( $_POST[ $url_key ] ) ? esc_url_raw( wp_unslash( $_POST[ $url_key ] ) ) : '',
				);
			}
		}
		$want = isset( $_POST['luae_approve'] ) && is_array( $_POST['luae_approve'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['luae_approve'] ) ) : array();
		$home = home_url( '/' );
		$checks = array(
			'verdict'   => LUAE_Approve::verdict( $modules['verdict'] ),
			'pros_cons' => array_merge( LUAE_Approve::lines( $modules['pros'], 2, 'Pros' ), LUAE_Approve::lines( $modules['cons'], 1, 'Cons' ) ),
			'buyers'    => array_merge( LUAE_Approve::lines( $modules['buy'], 1, 'Buyers' ), LUAE_Approve::lines( $modules['skip'], 1, 'Skip' ) ),
			'scorecard' => LUAE_Approve::scorecard( $modules['scores'] ),
			'compare'   => LUAE_Approve::compare( $modules['compare'], $home ),
			'faq'       => LUAE_Approve::faq( $modules['faq'] ),
		);
		$errors = array();
		foreach ( $checks as $key => $check_errors ) {
			if ( in_array( $key, $want, true ) && $check_errors ) {
				$errors = array_merge( $errors, $check_errors );
				continue;
			}
			$modules['approved'][ $key ] = in_array( $key, $want, true ) ? 1 : 0;
		}
		update_post_meta( $post_id, LUAE_Plugin::MODULES, $modules );
		set_transient( 'luae_errors_' . $post_id, $errors, 60 );
		LUAE_Plugin::instance()->scan_post( $post_id );
		LUAE_Plugin::instance()->rebuild();
	}

	/**
	 * Render the tools page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin   = LUAE_Plugin::instance();
		$board    = get_option( LUAE_Plugin::BOARD, array() );
		$signals  = is_array( $board ) && isset( $board['signals'] ) && is_array( $board['signals'] ) ? $board['signals'] : LUAE_Board::armed();
		$tally    = LUAE_Board::tally( $signals );
		$scanned  = is_array( $board ) && ! empty( $board['at'] );
		$settings = $plugin->settings();
		$paths    = get_option( LUAE_Plugin::PATHS, array() );
		$approved = get_option( LUAE_Plugin::NOINDEX, array() );
		$reports  = $this->recent_reports();
		$names    = array();
		if ( function_exists( 'get_plugins' ) ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $file => $data ) {
				if ( is_plugin_active( $file ) && isset( $data['Name'] ) ) {
					$names[] = (string) $data['Name'];
				}
			}
		}
		$split  = LUAE_Overlap::split( $names );
		$notice = isset( $_GET['luae_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['luae_notice'] ) ) : '';
		include LUAE_DIR . 'templates/admin.php';
	}

	/**
	 * @return array<int,array{id:int,title:string,report:array<string,mixed>}>
	 */
	private function recent_reports() {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'meta_key'       => LUAE_Plugin::REPORT,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$rows = array();
		foreach ( $ids as $id ) {
			$report = get_post_meta( (int) $id, LUAE_Plugin::REPORT, true );
			if ( ! is_array( $report ) ) {
				continue;
			}
			$rows[] = array(
				'id'     => (int) $id,
				'title'  => get_the_title( (int) $id ),
				'report' => $report,
			);
		}
		usort(
			$rows,
			function ( $a, $b ) {
				$rank = array( 'HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2 );
				$ar   = isset( $a['report']['opportunity'], $rank[ $a['report']['opportunity'] ] ) ? $rank[ $a['report']['opportunity'] ] : 3;
				$br   = isset( $b['report']['opportunity'], $rank[ $b['report']['opportunity'] ] ) ? $rank[ $b['report']['opportunity'] ] : 3;
				if ( $ar !== $br ) {
					return $ar - $br;
				}
				$as = isset( $a['report']['score'] ) ? (int) $a['report']['score'] : 0;
				$bs = isset( $b['report']['score'] ) ? (int) $b['report']['score'] : 0;
				return $as - $bs;
			}
		);
		return $rows;
	}

	/**
	 * Scan the next guides.
	 */
	public function handle_scan() {
		$this->guard( 'luae_scan' );
		$count = LUAE_Plugin::instance()->scan_batch( 10 );
		$this->back( $count ? 'scanned' : 'scan-wrapped' );
	}

	/**
	 * Sample the homepage for speed signals.
	 */
	public function handle_sample() {
		$this->guard( 'luae_sample' );
		LUAE_Plugin::instance()->sample_home();
		LUAE_Plugin::instance()->rebuild();
		$this->back( 'sampled' );
	}

	/**
	 * Approve or remove noindex paths.
	 */
	public function handle_noindex() {
		$this->guard( 'luae_noindex' );
		$paths = isset( $_POST['luae_paths'] ) && is_array( $_POST['luae_paths'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['luae_paths'] ) ) : array();
		$mode  = isset( $_POST['luae_noindex_mode'] ) ? sanitize_key( wp_unslash( $_POST['luae_noindex_mode'] ) ) : 'approve';
		if ( 'remove' === $mode ) {
			LUAE_Plugin::instance()->remove_noindex( $paths );
		} else {
			LUAE_Plugin::instance()->approve_noindex( $paths );
		}
		LUAE_Plugin::instance()->rebuild();
		$this->back( 'noindex' );
	}

	/**
	 * Save the tag and the two switches.
	 */
	public function handle_settings() {
		$this->guard( 'luae_settings' );
		$settings = LUAE_Plugin::instance()->settings();
		$tag      = isset( $_POST['expected_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_tag'] ) ) : '';
		$settings['expected_tag'] = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $tag );
		$settings['show_front']   = empty( $_POST['show_front'] ) ? 0 : 1;
		$settings['hourly']       = empty( $_POST['hourly'] ) ? 0 : 1;
		update_option( LUAE_Plugin::SETTINGS, $settings, true );
		LUAE_Plugin::instance()->forget_settings();
		LUAE_Plugin::instance()->maybe_schedule();
		LUAE_Plugin::instance()->rebuild();
		$this->back( 'settings' );
	}

	/**
	 * Deactivate overlapping plugins after a backup confirmation.
	 */
	public function handle_retire() {
		$this->guard( 'luae_retire' );
		if ( empty( $_POST['luae_confirm_backup'] ) ) {
			$this->back( 'backup-required' );
		}
		$result = LUAE_Plugin::instance()->retire_overlap();
		LUAE_Plugin::instance()->rebuild();
		$this->back( $result['done'] ? 'retired' : 'retire-none' );
	}

	/**
	 * @param string $action Action.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot run this.', 'luxe-ultimate-authority' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * @param string $notice Notice key.
	 */
	private function back( $notice ) {
		wp_safe_redirect( admin_url( 'tools.php?page=luxe-ultimate-authority&luae_notice=' . rawurlencode( $notice ) ) );
		exit;
	}
}
