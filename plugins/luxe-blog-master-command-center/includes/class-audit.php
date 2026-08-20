<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only audit into Keep Live / Safe Draft / Duplicate Hold / Mismatch Hold.
 */
class Luxe_BMC_Audit {

	const OPTION = 'luxe_bmc_audit';

	/**
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function run( $limit = 300 ) {
		$limit = max( 25, min( 500, (int) $limit ) );
		$ids   = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$keep         = array();
		$draft        = array();
		$dup          = array();
		$mismatch     = array();
		$ready        = array();
		$seen_titles  = array();
		$safe_drafts  = array();
		$published_ids = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$row = array(
				'id'      => (int) $id,
				'status'  => $post->post_status,
				'title'   => $post->post_title,
				'product' => Luxe_BMC_Plugin::product_id( $id ),
				'score'   => (int) get_post_meta( $id, '_luxe_bmc_last_score', true ),
				'reason'  => '',
			);
			$key = strtolower( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_title ) ) );
			if ( isset( $seen_titles[ $key ] ) && 'publish' !== $post->post_status ) {
				$row['reason'] = 'Same/near title as #' . $seen_titles[ $key ];
				$dup[]         = $row;
				continue;
			}
			$seen_titles[ $key ] = (int) $id;
			if ( 'publish' === $post->post_status ) {
				$keep[]          = $row;
				$published_ids[] = (int) $id;
				continue;
			}
			if ( $row['product'] < 1 || preg_match( '/SEO Format|SEO Fixed Draft|SEO URL Review/i', $post->post_title ) ) {
				$row['reason'] = 'Missing or invalid locked product; No valid product locked';
				if ( preg_match( '/SEO Format|SEO Fixed Draft|SEO URL Review/i', $post->post_title ) ) {
					$row['reason'] = 'SEO Format/URL draft title; ' . $row['reason'];
				}
				$mismatch[] = $row;
				continue;
			}
			if ( get_post_meta( $id, '_luxe_bmc_approval_ready', true ) ) {
				$ready[] = $row;
			}
			$draft[]       = $row;
			$safe_drafts[] = (int) $id;
		}
		$out = array(
			'at'             => time(),
			'keep'           => $keep,
			'draft'          => $draft,
			'dup'            => $dup,
			'mismatch'       => $mismatch,
			'ready'          => $ready,
			'safe_drafts'    => $safe_drafts,
			'published_ids'  => $published_ids,
			'keep_count'     => count( $keep ),
			'draft_count'    => count( $draft ),
			'dup_count'      => count( $dup ),
			'mismatch_count' => count( $mismatch ),
			'ready_count'    => count( $ready ),
			'counts'         => array(
				'keep'      => count( $keep ),
				'draft'     => count( $draft ),
				'dup'       => count( $dup ),
				'mismatch'  => count( $mismatch ),
				'ready'     => count( $ready ),
			),
		);
		update_option( self::OPTION, $out, false );
		Luxe_BMC_Safety::log( 'audit', 'Read-only audit memory updated. Keep ' . count( $keep ) . ', drafts ' . count( $draft ) . ', holds ' . count( $mismatch ) . '. No posts changed.' );
		return $out;
	}

	/**
	 * @return array
	 */
	public static function last() {
		$last = get_option( self::OPTION, array() );
		return is_array( $last ) ? $last : array();
	}

	/**
	 * Published guide IDs (Keep Live).
	 *
	 * @return array<int,int>
	 */
	public static function published_guides() {
		$last = self::last();
		if ( ! empty( $last['published_ids'] ) && is_array( $last['published_ids'] ) ) {
			return array_map( 'intval', $last['published_ids'] );
		}
		$ids = array();
		if ( ! empty( $last['keep'] ) && is_array( $last['keep'] ) ) {
			foreach ( $last['keep'] as $row ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}

	/**
	 * Category buckets for the command center table.
	 *
	 * @return array<string,array>
	 */
	public static function categories() {
		$map = array(
			'Computers & Laptops'              => 'laptop|macbook|chromebook|omen|zenbook|gram|xps|inspiron',
			'Headphones & Earbuds'             => 'bud|earbud|headphone|airpod',
			'Watches & Wearables'              => 'watch|seiko|wearable',
			'Phones & Tablets'                 => 'iphone|galaxy s|tablet|ipad|pixel',
			'Drones & Cameras'                 => 'drone|mavic|avata|swellpro|camera|gopro',
			'Needs Product Re-Link / Product #0' => '',
		);
		$last = self::last();
		$rows = array_merge(
			isset( $last['keep'] ) ? $last['keep'] : array(),
			isset( $last['draft'] ) ? $last['draft'] : array(),
			isset( $last['ready'] ) ? $last['ready'] : array()
		);
		$out = array();
		foreach ( $map as $label => $re ) {
			$out[ $label ] = array(
				'rows'      => array(),
				'draft_ids' => array(),
			);
		}
		$out['Needs Product Re-Link / Product #0']['rows'] = isset( $last['mismatch'] ) ? array_slice( $last['mismatch'], 0, 40 ) : array();
		foreach ( $rows as $row ) {
			$title = isset( $row['title'] ) ? $row['title'] : '';
			$hit   = false;
			foreach ( $map as $label => $re ) {
				if ( '' === $re ) {
					continue;
				}
				if ( preg_match( '/(?:' . $re . ')/i', $title ) ) {
					$out[ $label ]['rows'][] = $row;
					if ( 'publish' !== ( $row['status'] ?? '' ) && ! empty( $row['product'] ) ) {
						$out[ $label ]['draft_ids'][] = (int) $row['id'];
					}
					$hit = true;
					break;
				}
			}
			if ( ! $hit && ! empty( $row['product'] ) ) {
				$out['Computers & Laptops']['rows'][] = $row;
				if ( 'publish' !== ( $row['status'] ?? '' ) ) {
					$out['Computers & Laptops']['draft_ids'][] = (int) $row['id'];
				}
			}
		}
		return $out;
	}
}
