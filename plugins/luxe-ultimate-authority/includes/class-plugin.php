<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage, scan, and public output. Never writes post content or status.
 */
class LUAE_Plugin {

	const SETTINGS = 'luae_settings';
	const BOARD    = 'luae_board';
	const STATS    = 'luae_stats';
	const NOINDEX  = 'luae_noindex_paths';
	const PATHS    = 'luae_paths';
	const CURSOR   = 'luae_scan_cursor';
	const SAMPLE   = 'luae_sample';
	const MODULES  = 'luae_modules';
	const REPORT   = 'luae_report';
	const CRON     = 'luae_hourly_scan';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string,mixed>|null
	 */
	private $settings_cache = null;

	/**
	 * @var bool|null
	 */
	private $junk_request = null;

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
			'expected_tag' => 'luxetrendse0f-20',
			'show_front'   => 1,
			'hourly'       => 0,
		);
	}

	/**
	 * Store defaults. Do not scan and do not publish.
	 */
	public static function activate() {
		$settings = get_option( self::SETTINGS );
		if ( ! is_array( $settings ) ) {
			add_option( self::SETTINGS, self::defaults(), '', true );
		} else {
			update_option( self::SETTINGS, wp_parse_args( $settings, self::defaults() ), true );
		}
		add_option( self::NOINDEX, array(), '', true );
		add_option( self::PATHS, array(), '', false );
		add_option( self::CURSOR, 0, '', false );
		if ( ! get_option( self::BOARD ) ) {
			update_option(
				self::BOARD,
				array(
					'at'      => 0,
					'signals' => LUAE_Board::armed(),
				),
				false
			);
		}
	}

	/**
	 * Stop the optional cron. Keep drafts.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON, array( $this, 'cron_scan' ) );
		add_filter( 'the_content', array( $this, 'append_approved' ), 99 );
		add_filter( 'wp_robots', array( $this, 'wp_robots' ), 99 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'rank_math_robots' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );
		if ( is_admin() ) {
			LUAE_Admin::instance()->boot();
		}
	}

	/**
	 * Keep the optional one-guide cron unscheduled unless the setting is on.
	 */
	public function maybe_schedule() {
		$settings = $this->settings();
		$scheduled = wp_next_scheduled( self::CRON );
		if ( ! empty( $settings['hourly'] ) && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON );
		}
		if ( empty( $settings['hourly'] ) && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * One guide, drafts and flags only.
	 */
	public function cron_scan() {
		$this->scan_batch( 1, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function settings() {
		if ( is_array( $this->settings_cache ) ) {
			return $this->settings_cache;
		}
		$settings = get_option( self::SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$this->settings_cache = wp_parse_args( $settings, self::defaults() );
		return $this->settings_cache;
	}

	/**
	 * Drop the request cache after a settings save.
	 */
	public function forget_settings() {
		$this->settings_cache = null;
	}

	/**
	 * @param int  $limit    Batch size.
	 * @param bool $discover Whether to inspect archives on this pass.
	 * @return int Number scanned.
	 */
	public function scan_batch( $limit, $discover = true ) {
		global $wpdb;
		$limit  = max( 1, min( 20, (int) $limit ) );
		$cursor = (int) get_option( self::CURSOR, 0 );
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','future','private') AND ID > %d ORDER BY ID ASC LIMIT %d",
				'post',
				$cursor,
				$limit
			)
		);
		if ( ! $ids ) {
			update_option( self::CURSOR, 0, false );
			if ( $discover ) {
				$this->discover_paths();
			}
			$this->rebuild();
			return 0;
		}
		foreach ( $ids as $id ) {
			$this->scan_post( (int) $id );
			$cursor = (int) $id;
		}
		update_option( self::CURSOR, $cursor, false );
		if ( $discover ) {
			$this->discover_paths();
		}
		$this->rebuild();
		return count( $ids );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public function scan_post( $post_id ) {
		$report = LUAE_Post::assess( $this->input_for( $post_id ) );
		update_post_meta( $post_id, self::REPORT, $report );
		return $report;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public function input_for( $post_id ) {
		$post = get_post( $post_id );
		$settings = $this->settings();
		$categories = array();
		$cat_ids    = array();
		$terms      = get_the_category( $post_id );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = $term->name;
				$cat_ids[]    = (int) $term->term_id;
			}
		}
		$siblings = array();
		if ( $cat_ids ) {
			$related = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'category__in'        => $cat_ids,
					'post__not_in'        => array( $post_id ),
					'posts_per_page'      => 5,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'suppress_filters'    => true,
				)
			);
			foreach ( $related as $related_post ) {
				$siblings[] = array(
					'title' => get_the_title( $related_post ),
					'url'   => get_permalink( $related_post ),
				);
			}
		}
		$modules = get_post_meta( $post_id, self::MODULES, true );
		if ( ! is_array( $modules ) ) {
			$modules = LUAE_Post::empty_modules();
		}
		return array(
			'title'                 => $post ? $post->post_title : '',
			'content'               => $post ? $post->post_content : '',
			'home'                  => home_url( '/' ),
			'categories'            => $categories,
			'siblings'              => $siblings,
			'rank_math_title'       => (string) get_post_meta( $post_id, 'rank_math_title', true ),
			'rank_math_description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
			'views'                 => $this->numeric_meta( $post_id, array( 'post_views_count', 'views' ) ),
			'clicks'                => $this->numeric_meta( $post_id, array( 'luae_amazon_clicks' ) ),
			'expected_tag'          => (string) $settings['expected_tag'],
			'modules'               => $modules,
		);
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param string[] $keys Keys.
	 * @return int|null
	 */
	private function numeric_meta( $post_id, $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && is_numeric( $value ) ) {
				return (int) $value;
			}
		}
		return null;
	}

	/**
	 * Record archive paths. Does not noindex them.
	 */
	public function discover_paths() {
		$found = array(
			'weight' => array(),
			'tags'   => array(),
			'empty'  => array(),
			'thin'   => array(),
		);
		$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxes as $tax ) {
			$rewrite = '';
			if ( isset( $tax->rewrite['slug'] ) && is_string( $tax->rewrite['slug'] ) ) {
				$rewrite = $tax->rewrite['slug'];
			}
			if ( ! LUAE_Junk::watched_taxonomy( $tax->name, $rewrite ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
					'number'     => 60,
					'fields'     => 'all',
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$path   = LUAE_Junk::term_path( '' !== $rewrite ? $rewrite : $tax->name, $term->slug );
				$action = LUAE_Junk::action( $path );
				if ( 'noindex' === $action && 0 === strpos( LUAE_Junk::normalize( $path ), '/product-tag/' ) ) {
					$found['tags'][] = $path;
				} elseif ( 'noindex' === $action ) {
					$found['weight'][] = $path;
				}
				if ( (int) $term->count < 1 && 'noindex' === $action ) {
					$found['empty'][] = $path;
				}
				if ( 'product_cat' === $tax->name && (int) $term->count < 2 ) {
					$found['thin'][] = $path;
				}
			}
		}
		update_option( self::PATHS, $found, false );
	}

	/**
	 * Rebuild the signal board from stored reports.
	 */
	public function rebuild() {
		global $wpdb;
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
				self::REPORT,
				300
			)
		);
		$stats = LUAE_Stats::blank();
		foreach ( $rows as $raw ) {
			$report = maybe_unserialize( $raw );
			if ( is_array( $report ) ) {
				$stats = LUAE_Stats::add( $stats, $report );
			}
		}
		$paths = get_option( self::PATHS, array() );
		if ( is_array( $paths ) ) {
			$stats['weight_paths']    = isset( $paths['weight'] ) && is_array( $paths['weight'] ) ? count( $paths['weight'] ) : 0;
			$stats['tag_paths']       = isset( $paths['tags'] ) && is_array( $paths['tags'] ) ? count( $paths['tags'] ) : 0;
			$stats['empty_archives']  = isset( $paths['empty'] ) && is_array( $paths['empty'] ) ? count( $paths['empty'] ) : 0;
			$stats['thin_categories'] = isset( $paths['thin'] ) && is_array( $paths['thin'] ) ? count( $paths['thin'] ) : 0;
		}
		update_option( self::STATS, $stats, false );
		update_option(
			self::BOARD,
			array(
				'at'      => time(),
				'signals' => LUAE_Board::build( $stats, $this->env() ),
			),
			false
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function env() {
		$sample = get_option( self::SAMPLE, array() );
		if ( ! is_array( $sample ) ) {
			$sample = array();
		}
		$active = array();
		if ( ! function_exists( 'get_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'get_plugins' ) ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $file => $data ) {
				if ( is_plugin_active( $file ) && isset( $data['Name'] ) ) {
					$active[] = (string) $data['Name'];
				}
			}
		}
		$split = LUAE_Overlap::split( $active );
		$noindex = get_option( self::NOINDEX, array() );
		return array(
			'rank_math'         => defined( 'RANK_MATH_VERSION' ),
			'litespeed'         => defined( 'LSCWP_V' ),
			'overlapping'       => $split['retire'],
			'cache_control'     => isset( $sample['cache_control'] ) ? (string) $sample['cache_control'] : '',
			'litespeed_hit'     => ! empty( $sample['litespeed_hit'] ),
			'html_sample'       => isset( $sample['html'] ) ? (string) $sample['html'] : '',
			'approved_noindex'  => is_array( $noindex ) ? count( $noindex ) : 0,
		);
	}

	/**
	 * Store a homepage sample for the speed signals.
	 *
	 * @return bool
	 */
	public function sample_home() {
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout' => 8,
				'headers' => array( 'User-Agent' => 'LuxeUltimateAuthority/1.0' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$headers = wp_remote_retrieve_headers( $response );
		$cache   = '';
		$hit     = false;
		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$cache = (string) $headers->offsetGet( 'cache-control' );
			$hit   = false !== stripos( (string) $headers->offsetGet( 'x-litespeed-cache' ), 'hit' );
		}
		update_option(
			self::SAMPLE,
			array(
				'cache_control' => $cache,
				'litespeed_hit' => $hit ? 1 : 0,
				'html'          => LUAE_Speed::snippet( (string) wp_remote_retrieve_body( $response ) ),
				'at'            => time(),
			),
			false
		);
		return true;
	}

	/**
	 * @param string[] $paths Paths.
	 */
	public function approve_noindex( $paths ) {
		$current = get_option( self::NOINDEX, array() );
		update_option( self::NOINDEX, LUAE_Junk::merge_noindex( $current, $paths ), true );
	}

	/**
	 * @param string[] $paths Paths.
	 */
	public function remove_noindex( $paths ) {
		$current = get_option( self::NOINDEX, array() );
		if ( ! is_array( $current ) ) {
			return;
		}
		$drop = array();
		foreach ( $paths as $path ) {
			$drop[] = LUAE_Junk::normalize( $path );
		}
		$keep = array();
		foreach ( $current as $path ) {
			if ( ! in_array( LUAE_Junk::normalize( $path ), $drop, true ) ) {
				$keep[] = LUAE_Junk::normalize( $path );
			}
		}
		update_option( self::NOINDEX, $keep, false );
	}

	/**
	 * Deactivate only the named overlap list. Files stay on disk.
	 *
	 * @return array{done:string[],skipped:string[]}
	 */
	public function retire_overlap() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$done    = array();
		$skipped = array();
		foreach ( get_plugins() as $file => $data ) {
			$name = isset( $data['Name'] ) ? (string) $data['Name'] : '';
			if ( 'retire' !== LUAE_Overlap::classify( $name ) ) {
				continue;
			}
			if ( ! is_plugin_active( $file ) ) {
				$skipped[] = $name;
				continue;
			}
			try {
				deactivate_plugins( $file, false );
				$done[] = $name;
			} catch ( Exception $e ) {
				$skipped[] = $name;
			} catch ( Throwable $e ) {
				$skipped[] = $name;
			}
		}
		return array(
			'done'    => $done,
			'skipped' => $skipped,
		);
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	public function append_approved( $content ) {
		if ( ! is_string( $content ) || is_admin() || is_feed() || is_embed() ) {
			return $content;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || wp_doing_cron() ) {
			return $content;
		}
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$settings = $this->settings();
		if ( empty( $settings['show_front'] ) ) {
			return $content;
		}
		if ( false !== strpos( $content, 'luxe-ultimate-authority' ) ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}
		$modules = get_post_meta( $post_id, self::MODULES, true );
		$extra   = LUAE_Render::section( is_array( $modules ) ? $modules : array(), home_url( '/' ) );
		if ( '' === $extra ) {
			return $content;
		}
		return $content . $extra;
	}

	/**
	 * @param array<string,bool> $robots Robots.
	 * @return array<string,bool>
	 */
	public function wp_robots( $robots ) {
		if ( ! is_array( $robots ) || ! $this->request_is_approved_junk() ) {
			return $robots;
		}
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
		return $robots;
	}

	/**
	 * @param array<string,string> $robots Rank Math robots.
	 * @return array<string,string>
	 */
	public function rank_math_robots( $robots ) {
		if ( ! is_array( $robots ) || ! $this->request_is_approved_junk() ) {
			return $robots;
		}
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}

	/**
	 * @return bool
	 */
	private function request_is_approved_junk() {
		if ( null !== $this->junk_request ) {
			return $this->junk_request;
		}
		$approved = get_option( self::NOINDEX, array() );
		if ( ! is_array( $approved ) || ! $approved ) {
			$this->junk_request = false;
			return false;
		}
		$path = '/';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$parsed = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			$path   = is_string( $parsed ) ? $parsed : '/';
		}
		$this->junk_request = LUAE_Junk::matches_approved( $path, $approved );
		return $this->junk_request;
	}

	/**
	 * Front CSS only when a guide will show an approved block.
	 */
	public function front_assets() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$modules = get_post_meta( get_queried_object_id(), self::MODULES, true );
		$extra   = LUAE_Render::section( is_array( $modules ) ? $modules : array(), home_url( '/' ) );
		if ( '' === $extra ) {
			return;
		}
		wp_enqueue_style( 'luae-front', LUAE_URL . 'assets/front.css', array(), LUAE_VERSION );
	}
}
