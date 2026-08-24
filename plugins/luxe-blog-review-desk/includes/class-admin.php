<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LBRD_Admin' ) ) {
	return;
}

/**
 * Review desk: View + gated Publish.
 */
class LBRD_Admin {

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
	 * Register UI.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_publish' ) );
		add_filter( 'plugin_action_links_' . LBRD_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Luxe Review Desk', 'luxe-blog-review-desk' ),
			__( 'Luxe Review Desk', 'luxe-blog-review-desk' ),
			'edit_posts',
			'luxe-blog-review-desk',
			array( $this, 'render' ),
			'dashicons-visibility',
			57
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_luxe-blog-review-desk' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'lbrd-admin',
			LBRD_URL . 'assets/admin.css',
			array(),
			LBRD_VERSION
		);
	}

	/**
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=luxe-blog-review-desk' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'luxe-blog-review-desk' ) . '</a>' );
		return $links;
	}

	/**
	 * Human publish only. Never cron.
	 */
	public function maybe_publish() {
		if ( empty( $_POST['lbrd_publish'] ) ) {
			return;
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		check_admin_referer( 'lbrd_publish' );
		$id  = isset( $_POST['lbrd_post_id'] ) ? (int) $_POST['lbrd_post_id'] : 0;
		$row = $this->row_for( $id );
		$gate = LBRD_Eligibility::publish_gate( $row );
		if ( ! $gate['ok'] ) {
			add_settings_error( 'lbrd', 'blocked', $gate['reason'], 'error' );
			return;
		}
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'lbrd', 'fail', $result->get_error_message(), 'error' );
			return;
		}
		add_settings_error(
			'lbrd',
			'published',
			sprintf(
				/* translators: %d: post ID */
				__( 'Published post #%d. View it on the live site.', 'luxe-blog-review-desk' ),
				$id
			),
			'updated'
		);
	}

	/**
	 * Render desk.
	 */
	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		settings_errors( 'lbrd' );
		$live   = array();
		$drafts = array();
		$ready  = array();
		foreach ( $this->rows() as $row ) {
			if ( 'publish' === $row['status'] ) {
				$live[] = $row;
			} else {
				$drafts[] = $row;
				if ( $row['gate']['ok'] ) {
					$ready[] = $row;
				}
			}
		}
		include LBRD_DIR . 'templates/admin.php';
	}

	/**
	 * @return array[]
	 */
	private function rows() {
		$out  = array();
		$seen = array();
		foreach ( $this->guide_ids() as $id ) {
			$id = (int) $id;
			if ( $id < 1 || isset( $seen[ $id ] ) ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || 'post' !== $post->post_type ) {
				continue;
			}
			if ( ! in_array( $post->post_status, array( 'publish', 'draft', 'pending' ), true ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = $this->row_from_post( $post );
		}
		return $out;
	}

	/**
	 * Command Center Keep Live + Safe Draft IDs, then WordPress guide posts.
	 *
	 * @return int[]
	 */
	private function guide_ids() {
		$ids = array( LBRD_Eligibility::MASTER_ID );
		$cc  = get_option( 'luxe_bmc_audit', array() );
		if ( is_array( $cc ) ) {
			foreach ( array( 'keep', 'draft', 'ready', 'mismatch', 'dup' ) as $bucket ) {
				if ( empty( $cc[ $bucket ] ) || ! is_array( $cc[ $bucket ] ) ) {
					continue;
				}
				foreach ( $cc[ $bucket ] as $row ) {
					if ( isset( $row['id'] ) ) {
						$ids[] = (int) $row['id'];
					}
				}
			}
			foreach ( array( 'published_ids', 'safe_drafts' ) as $list_key ) {
				if ( empty( $cc[ $list_key ] ) || ! is_array( $cc[ $list_key ] ) ) {
					continue;
				}
				foreach ( $cc[ $list_key ] as $id ) {
					$ids[] = (int) $id;
				}
			}
		}
		foreach ( array( 'publish', array( 'draft', 'pending' ) ) as $status ) {
			$found = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => $status,
					'posts_per_page' => 250,
					'fields'         => 'ids',
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			foreach ( $found as $id ) {
				$id    = (int) $id;
				$post  = get_post( $id );
				$title = $post ? $post->post_title : '';
				if ( $this->looks_like_guide( $title ) || LBRD_Eligibility::MASTER_ID === $id ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * @param int $id Post ID.
	 * @return array
	 */
	private function row_for( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'post' !== $post->post_type ) {
			return array(
				'id'       => $id,
				'status'   => '',
				'product'  => 0,
				'score'    => 0,
				'approval' => false,
			);
		}
		return $this->row_from_post( $post );
	}

	/**
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private function row_from_post( $post ) {
		$id      = (int) $post->ID;
		$product = LBRD_Eligibility::product_for( $id );
		$score   = LBRD_Eligibility::score_for( $id );
		$ready   = LBRD_Eligibility::approval_for( $id );
		$row     = array(
			'id'       => $id,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'product'  => $product,
			'score'    => $score,
			'approval' => $ready,
			'view'     => $this->view_url( $post ),
			'edit'     => get_edit_post_link( $id, 'raw' ),
		);
		$row['gate'] = LBRD_Eligibility::publish_gate( $row );
		return $row;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function view_url( $post ) {
		if ( 'publish' === $post->post_status ) {
			return get_permalink( $post );
		}
		return get_preview_post_link( $post );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	private function looks_like_guide( $title ) {
		return (bool) preg_match( '/review|buyer guide|macbook|watch|drone|earbuds|buds|laptop|iphone|ipad|tablet|seiko|samsung|pixel|asus|omen|zenbook/i', $title );
	}
}
