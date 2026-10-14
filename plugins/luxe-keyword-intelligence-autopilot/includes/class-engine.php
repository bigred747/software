<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attention queue. One request repairs a bounded batch and never changes post status.
 */
class Luxe_KIA_Engine {

	const STATE    = 'luxe_kia_state';
	const ACTIVITY = 'luxe_kia_activity';
	const LOCK     = 'luxe_kia_lock';
	const SCORE    = '_luxe_kia_score';
	const SCANNED  = '_luxe_kia_scanned_at';
	const COOLDOWN = '_luxe_kia_cooldown_until';
	const BACKUP   = '_luxe_kia_backup';

	/**
	 * @var bool
	 */
	private $ran = false;

	/**
	 * Activity rows collected during one batch and written once.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $pending_logs = array();

	/**
	 * @param bool $burst Larger pass from the admin button.
	 * @return array<string,mixed>
	 */
	public function cycle( $burst = false ) {
		if ( $this->ran ) {
			return array( 'message' => 'A batch already ran in this request.' );
		}
		if ( ! $this->lock() ) {
			return array( 'message' => 'Another batch is still running.' );
		}
		$this->ran = true;
		try {
			$result = $this->run( (bool) $burst );
		} finally {
			delete_option( self::LOCK );
		}
		return $result;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public function one( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 || ! $this->lock() ) {
			return array( 'message' => 'That item could not be locked.' );
		}
		$this->ran = true;
		try {
			$state = $this->state();
			$this->process( $post_id, $state );
			$this->flush_logs();
			$this->save_state( $state );
		} finally {
			delete_option( self::LOCK );
		}
		return array( 'message' => 'Item ' . $post_id . ' finished.' );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function restore( $post_id ) {
		$post_id = (int) $post_id;
		$backup  = get_post_meta( $post_id, self::BACKUP, true );
		if ( ! is_array( $backup ) ) {
			return false;
		}
		$thumb = (int) get_post_thumbnail_id( $post_id );
		foreach ( Luxe_KIA_Plan::allowed_keys() as $key ) {
			if ( ! array_key_exists( $key, $backup ) ) {
				continue;
			}
			$target = ( '_wp_attachment_image_alt' === $key ) ? $thumb : $post_id;
			if ( $target < 1 ) {
				continue;
			}
			if ( null === $backup[ $key ] || '' === $backup[ $key ] ) {
				delete_post_meta( $target, $key );
			} else {
				update_post_meta( $target, $key, $backup[ $key ] );
			}
		}
		$hours = max( 1, (int) Luxe_KIA_Plugin::instance()->setting( 'cooldown' ) );
		update_post_meta( $post_id, self::COOLDOWN, time() + ( $hours * HOUR_IN_SECONDS ) );
		if ( isset( $backup['score'] ) ) {
			update_post_meta( $post_id, self::SCORE, (int) $backup['score'] );
		}
		$this->log( $post_id, get_post_status( $post_id ), 'restore', isset( $backup['score'] ) ? (int) $backup['score'] : 0, isset( $backup['score'] ) ? (int) $backup['score'] : 0, 'Restored the Rank Math fields from the last backup. Status stayed ' . get_post_status( $post_id ) . '.' );
		$this->flush_logs();
		return true;
	}

	/**
	 * @return array<string,int>
	 */
	public function coverage() {
		global $wpdb;
		$types    = $this->types();
		$statuses = $this->statuses();
		$empty    = array(
			'total'     => 0,
			'green'     => 0,
			'never'     => 0,
			'attention' => 0,
			'scanned'   => 0,
		);
		if ( ! $types || ! $statuses ) {
			return $empty;
		}
		$type_in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$args      = array_merge( $types, $statuses );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($type_in) AND post_status IN ($status_in)", $args ) );
		$green     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type IN ($type_in) AND p.post_status IN ($status_in) AND CAST(m.meta_value AS UNSIGNED) >= %d",
				array_merge( array( self::SCORE ), $args, array( (int) Luxe_KIA_Plugin::instance()->setting( 'green' ) ) )
			)
		);
		$never = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type IN ($type_in) AND p.post_status IN ($status_in) AND m.meta_id IS NULL",
				array_merge( array( self::SCORE ), $args )
			)
		);
		return array(
			'total'     => $total,
			'green'     => $green,
			'never'     => $never,
			'attention' => max( 0, $total - $green ),
			'scanned'   => max( 0, $total - $never ),
		);
	}

	/**
	 * @return int
	 */
	public function guard_terms() {
		$slugs = Luxe_KIA_Taxonomies::merge( (string) Luxe_KIA_Plugin::instance()->setting( 'taxonomies' ) );
		$done  = 0;
		foreach ( $slugs as $slug ) {
			if ( $done >= 40 || ! taxonomy_exists( $slug ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $slug,
					'hide_empty' => false,
					'number'     => 40 - $done,
					'meta_query' => array(
						array(
							'key'     => 'rank_math_robots',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) || empty( $term->term_id ) ) {
					continue;
				}
				update_term_meta( (int) $term->term_id, 'rank_math_robots', array( 'noindex' ) );
				$done++;
			}
		}
		$state              = $this->state();
		$state['tax_at']    = time();
		$state['tax_count'] = $done;
		$this->save_state( $state );
		return $done;
	}

	/**
	 * @param bool $burst Burst.
	 * @return array<string,mixed>
	 */
	private function run( $burst ) {
		$plugin = Luxe_KIA_Plugin::instance();
		if ( ! $plugin->enabled() ) {
			return array( 'message' => 'The 24/7 engine is off.' );
		}
		$state  = $this->state();
		$load   = Luxe_KIA_Gate::load();
		$free   = Luxe_KIA_Gate::free_bytes();
		$limit  = $burst ? Luxe_KIA_Gate::burst_size( $load, $free ) : Luxe_KIA_Gate::batch_size( $load, $free, $plugin->settings() );
		$allow  = Luxe_KIA_Gate::allowance( $state['scans'], $state['repairs'], (int) $plugin->setting( 'max_scans' ), (int) $plugin->setting( 'max_repairs' ) );
		$budget = $burst ? 20 : max( 4, (int) $plugin->setting( 'budget' ) );
		$state['load']  = $load;
		$state['batch'] = $limit;
		$state['last']  = time();
		if ( 'ok' !== $allow || $limit < 1 ) {
			$message = ( 'repair_cap' === $allow ) ? 'Daily repair cap reached.' : ( ( 'scan_cap' === $allow ) ? 'Daily scan cap reached.' : 'Server load is too high for a batch. One item will wait.' );
			$state['message'] = $message;
			$this->save_state( $state );
			return array( 'message' => $message );
		}
		$ids   = $this->next_ids( $limit );
		$this->warm( $ids );
		$start = microtime( true );
		$seen  = 0;
		foreach ( $ids as $id ) {
			if ( ( microtime( true ) - $start ) >= $budget ) {
				break;
			}
			if ( 'stop' === $this->process( (int) $id, $state ) ) {
				break;
			}
			$seen++;
		}
		$state['message'] = 'Batch finished: ' . (int) $seen . ' item(s), ' . (int) $state['scans'] . ' scans and ' . (int) $state['repairs'] . ' repairs today.';
		$this->flush_logs();
		$this->save_state( $state );
		if ( ( $budget - ( microtime( true ) - $start ) ) >= 3 ) {
			$this->guard_terms();
		}
		return array(
			'message' => $state['message'],
			'seen'    => $seen,
		);
	}

	/**
	 * @param int   $post_id Post ID.
	 * @param array $state State.
	 * @return string
	 */
	private function process( $post_id, &$state ) {
		$plugin = Luxe_KIA_Plugin::instance();
		$allow  = Luxe_KIA_Gate::allowance( $state['scans'], $state['repairs'], (int) $plugin->setting( 'max_scans' ), (int) $plugin->setting( 'max_repairs' ) );
		if ( 'ok' !== $allow ) {
			$state['message'] = ( 'repair_cap' === $allow ) ? 'Daily repair cap reached.' : 'Daily scan cap reached.';
			return 'stop';
		}
		$item = $this->item( $post_id );
		if ( ! $item ) {
			return 'ok';
		}
		$plan   = Luxe_KIA_Plan::build( $item, $plugin->settings() );
		$writes = $this->safe_writes( $plan['writes'] );
		if ( $writes && (int) $state['repairs'] >= (int) $plugin->setting( 'max_repairs' ) ) {
			$state['message'] = 'Daily repair cap reached.';
			return 'stop';
		}
		if ( $writes ) {
			$this->backup( $post_id, $item, (int) $plan['before']['score'] );
			foreach ( $writes as $write ) {
				$this->apply_write( $post_id, $item, $write );
			}
			$state['repairs']++;
		}
		$state['scans']++;
		$after = isset( $plan['after']['score'] ) ? (int) $plan['after']['score'] : 0;
		$green = (int) $plugin->setting( 'green' );
		update_post_meta( $post_id, self::SCORE, $after );
		update_post_meta( $post_id, self::SCANNED, time() );
		if ( $after < $green ) {
			$hours = max( 1, (int) $plugin->setting( 'cooldown' ) );
			update_post_meta( $post_id, self::COOLDOWN, time() + ( $hours * HOUR_IN_SECONDS ) );
		} else {
			delete_post_meta( $post_id, self::COOLDOWN );
		}
		$before = isset( $plan['before']['score'] ) ? (int) $plan['before']['score'] : 0;
		$count  = isset( $plan['phrases'] ) ? count( $plan['phrases'] ) : 0;
		$status = (string) $item['status'];
		if ( $writes ) {
			$message = 'Repaired Rank Math metadata on #' . $post_id . ' (' . $before . ' → ' . $after . ') with ' . $count . ' tracked phrase(s). Status stayed ' . $status . '.';
			$action  = 'repair';
		} else {
			$message = 'Post #' . $post_id . ' scanned at ' . $after . '/100 with ' . $count . ' tracked phrase(s). Status stayed ' . $status . '.';
			$action  = 'scan';
		}
		$this->log( $post_id, $status, $action, $before, $after, $message );
		return 'ok';
	}

	/**
	 * @param int $limit Limit.
	 * @return array<int,int>
	 */
	private function next_ids( $limit ) {
		global $wpdb;
		$types    = $this->types();
		$statuses = $this->statuses();
		$limit    = max( 1, (int) $limit );
		if ( ! $types || ! $statuses ) {
			return array();
		}
		$type_in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$not       = '';
		$protected = $this->protected_ids();
		if ( $protected ) {
			$not = ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $protected ) ) . ')';
		}
		$sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s
			WHERE p.post_type IN ($type_in) AND p.post_status IN ($status_in)
			AND (s.meta_id IS NULL OR CAST(s.meta_value AS UNSIGNED) < %d)
			AND (c.meta_id IS NULL OR CAST(c.meta_value AS UNSIGNED) < %d)
			{$not}
			ORDER BY CAST(COALESCE(s.meta_value, '0') AS UNSIGNED) ASC, p.ID ASC
			LIMIT %d";
		$args = array_merge(
			array( self::SCORE, self::COOLDOWN ),
			$types,
			$statuses,
			array( (int) Luxe_KIA_Plugin::instance()->setting( 'green' ), time(), $limit )
		);
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
		$out = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	private function item( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! isset( $post->post_title ) ) {
			return null;
		}
		$thumb = (int) get_post_thumbnail_id( $post_id );
		$alt   = $thumb ? (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) : '';
		return array(
			'id'          => $post_id,
			'title'       => (string) $post->post_title,
			'status'      => (string) $post->post_status,
			'focus'       => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			'seo_title'   => (string) get_post_meta( $post_id, 'rank_math_title', true ),
			'description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
			'alt'         => $alt,
			'has_image'   => $thumb > 0,
			'alt_broken'  => $thumb > 0 && Luxe_KIA_Plan::alt_is_broken( $alt ),
			'thumb'       => $thumb,
		);
	}

	/**
	 * @param array $writes Writes.
	 * @return array<int,array<string,mixed>>
	 */
	private function safe_writes( $writes ) {
		$out = array();
		if ( ! is_array( $writes ) ) {
			return $out;
		}
		foreach ( $writes as $write ) {
			if ( ! is_array( $write ) || empty( $write['key'] ) || ! in_array( $write['key'], Luxe_KIA_Plan::allowed_keys(), true ) ) {
				continue;
			}
			$value = wp_strip_all_tags( (string) $write['value'] );
			if ( preg_match( '#https?://#i', $value ) ) {
				continue;
			}
			$write['value'] = $value;
			$out[]          = $write;
		}
		return $out;
	}

	/**
	 * @param int   $post_id Post ID.
	 * @param array $item Item.
	 * @param int   $score Score.
	 */
	private function backup( $post_id, $item, $score ) {
		$existing = get_post_meta( $post_id, self::BACKUP, true );
		if ( is_array( $existing ) && ! empty( $existing['kept'] ) ) {
			return;
		}
		update_post_meta(
			$post_id,
			self::BACKUP,
			array(
				'kept'                     => 1,
				'score'                    => (int) $score,
				'rank_math_focus_keyword'  => $item['focus'],
				'rank_math_title'          => $item['seo_title'],
				'rank_math_description'    => $item['description'],
				'_wp_attachment_image_alt' => $item['alt'],
			)
		);
	}

	/**
	 * @param int   $post_id Post ID.
	 * @param array $item Item.
	 * @param array $write Write.
	 */
	private function apply_write( $post_id, $item, $write ) {
		if ( '_wp_attachment_image_alt' === $write['key'] ) {
			$thumb = isset( $item['thumb'] ) ? (int) $item['thumb'] : 0;
			if ( $thumb > 0 ) {
				update_post_meta( $thumb, '_wp_attachment_image_alt', $write['value'] );
			}
			return;
		}
		update_post_meta( $post_id, $write['key'], $write['value'] );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function state() {
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$day = gmdate( 'Y-m-d' );
		if ( ! isset( $state['day'] ) || $state['day'] !== $day ) {
			$state['day']     = $day;
			$state['scans']   = 0;
			$state['repairs'] = 0;
		}
		$state['scans']   = isset( $state['scans'] ) ? (int) $state['scans'] : 0;
		$state['repairs'] = isset( $state['repairs'] ) ? (int) $state['repairs'] : 0;
		return $state;
	}

	/**
	 * @param array $state State.
	 */
	private function save_state( $state ) {
		update_option( self::STATE, $state, false );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $status Status.
	 * @param string $action Action.
	 * @param int    $before Before.
	 * @param int    $after After.
	 * @param string $message Message.
	 */
	private function log( $post_id, $status, $action, $before, $after, $message ) {
		$this->pending_logs[] = array(
			'time'    => time(),
			'id'      => (int) $post_id,
			'status'  => (string) $status,
			'action'  => (string) $action,
			'before'  => (int) $before,
			'after'   => (int) $after,
			'result'  => ( (int) $after >= (int) Luxe_KIA_Plugin::instance()->setting( 'green' ) ) ? 'GREEN' : 'ATTENTION',
			'message' => (string) $message,
		);
	}

	/**
	 * Write the batch log in one option update. Newest row stays first.
	 */
	private function flush_logs() {
		if ( ! $this->pending_logs ) {
			return;
		}
		$rows = get_option( self::ACTIVITY, array() );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		foreach ( $this->pending_logs as $row ) {
			array_unshift( $rows, $row );
		}
		update_option( self::ACTIVITY, array_slice( $rows, 0, 80 ), false );
		$this->pending_logs = array();
	}

	/**
	 * Load the batch posts and their image labels in two queries.
	 *
	 * @param array $ids Post IDs.
	 */
	private function warm( $ids ) {
		if ( ! $ids || ! function_exists( '_prime_post_caches' ) ) {
			return;
		}
		_prime_post_caches( $ids, false, true );
		$thumbs = array();
		foreach ( $ids as $id ) {
			$thumb = (int) get_post_thumbnail_id( (int) $id );
			if ( $thumb > 0 ) {
				$thumbs[] = $thumb;
			}
		}
		if ( $thumbs && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $thumbs );
		}
	}

	/**
	 * @return array<int,string>
	 */
	private function types() {
		$plugin = Luxe_KIA_Plugin::instance();
		$types  = array();
		if ( $plugin->setting( 'types_post' ) ) {
			$types[] = 'post';
		}
		if ( $plugin->setting( 'types_product' ) ) {
			$types[] = 'product';
		}
		if ( $plugin->setting( 'types_page' ) ) {
			$types[] = 'page';
		}
		if ( ! $types ) {
			return array( 'post', 'product', 'page' );
		}
		return $types;
	}

	/**
	 * @return array<int,string>
	 */
	private function statuses() {
		$plugin = Luxe_KIA_Plugin::instance();
		$list   = array( 'draft', 'pending', 'private' );
		if ( $plugin->setting( 'scan_published' ) ) {
			$list[] = 'publish';
		}
		if ( $plugin->setting( 'scan_scheduled' ) ) {
			$list[] = 'future';
		}
		return $list;
	}

	/**
	 * @return array<int,int>
	 */
	private function protected_ids() {
		$raw  = (string) Luxe_KIA_Plugin::instance()->setting( 'protected' );
		$bits = preg_split( '/[\s,]+/', $raw );
		$out  = array();
		if ( is_array( $bits ) ) {
			foreach ( $bits as $bit ) {
				$id = (int) $bit;
				if ( $id > 0 ) {
					$out[] = $id;
				}
			}
		}
		return $out;
	}

	/**
	 * @return bool
	 */
	private function lock() {
		$locked = get_option( self::LOCK, 0 );
		if ( $locked && ( time() - (int) $locked ) < 90 ) {
			return false;
		}
		update_option( self::LOCK, time(), false );
		return true;
	}
}
