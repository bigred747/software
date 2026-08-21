<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Draft-only 95–100 rebuild. Never publishes. Never writes master #10833 or published bodies.
 */
class Luxe_BMC_Repair {

	/**
	 * @param int $post_id    Post ID.
	 * @param int $product_id Optional product lock.
	 * @return array<string,mixed>
	 */
	public static function repair_draft( $post_id, $product_id = 0 ) {
		$post_id = (int) $post_id;
		if ( ! Luxe_BMC_Safety::can_write_body( $post_id ) ) {
			return array(
				'ok'     => false,
				'reason' => 'Protected live body or master #10833. Status preserved. Nothing published.',
				'score'  => Luxe_BMC_Score::evaluate( $post_id ),
			);
		}
		if ( $product_id > 0 ) {
			Luxe_BMC_Plugin::lock_product( $post_id, $product_id );
		}
		$locked = Luxe_BMC_Plugin::product_id( $post_id );
		if ( $locked < 1 ) {
			Luxe_BMC_Safety::log( 'repair_blocked', 'Blog #' . $post_id . ' Product #0 / no unique high-confidence product match. Manual re-link is required.' );
			$score = Luxe_BMC_Score::evaluate( $post_id );
			return array(
				'ok'     => false,
				'reason' => 'Product #0 blocked. No fake-green.',
				'score'  => $score,
			);
		}
		$post = get_post( $post_id );
		$built = Luxe_BMC_Blueprint::build( $post, $locked );
		if ( ! $built ) {
			return array(
				'ok'     => false,
				'reason' => 'Blueprint could not read the locked WooCommerce product.',
				'score'  => Luxe_BMC_Score::evaluate( $post_id ),
			);
		}
		Luxe_BMC_Safety::backup( $post_id );
		$status = $post->post_status;
		$GLOBALS['luxe_bmc_writing'] = 1;
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $built['title'],
				'post_excerpt' => $built['excerpt'],
				'post_content' => $built['content'],
				'post_status'  => $status,
			)
		);
		unset( $GLOBALS['luxe_bmc_writing'] );
		if ( get_post_status( $post_id ) !== $status ) {
			$GLOBALS['luxe_bmc_writing'] = 1;
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				)
			);
			unset( $GLOBALS['luxe_bmc_writing'] );
		}
		update_post_meta( $post_id, 'rank_math_description', $built['description'] );
		update_post_meta( $post_id, '_luxe_bmc_repaired_at', time() );
		if ( ! get_post_thumbnail_id( $post_id ) ) {
			$thumb = get_post_thumbnail_id( $locked );
			if ( $thumb ) {
				set_post_thumbnail( $post_id, $thumb );
			}
		}
		$score = Luxe_BMC_Score::evaluate( $post_id );
		update_post_meta( $post_id, '_luxe_bmc_last_score', (int) $score['score'] );
		Luxe_BMC_Safety::log( 'repair', '95-100 rebuild blog #' . $post_id . ' product #' . $locked . ' score ' . $score['score'] . '/100. Status ' . $status . '.' );
		return array(
			'ok'     => ! empty( $score['all'] ),
			'reason' => empty( $score['all'] ) ? implode( ', ', $score['fail'] ) : 'ALL GREEN',
			'score'  => $score,
		);
	}

	/**
	 * Meta-only harden for already-published green blogs.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function harden_published( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id === Luxe_BMC_Plugin::MASTER ) {
			update_post_meta( $post_id, '_luxe_bmc_master_protected', 1 );
			return array(
				'ok'     => true,
				'reason' => 'Master #10833 meta-protected. Body not written.',
				'score'  => Luxe_BMC_Score::evaluate( $post_id ),
			);
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return array(
				'ok'     => false,
				'reason' => 'Not published.',
				'score'  => Luxe_BMC_Score::evaluate( $post_id ),
			);
		}
		$score = Luxe_BMC_Score::evaluate( $post_id );
		if ( empty( $score['all'] ) ) {
			return array(
				'ok'     => false,
				'reason' => 'Published blog is not all-green. Body left untouched.',
				'score'  => $score,
			);
		}
		update_post_meta( $post_id, '_luxe_bmc_green_lock', 1 );
		update_post_meta( $post_id, '_luxe_bmc_green_lock_at', time() );
		return array(
			'ok'     => true,
			'reason' => 'Published green lock stamped. Body not rewritten.',
			'score'  => $score,
		);
	}

	/**
	 * Approval Ready stamp. Never publishes.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function approval_ready( $post_id ) {
		$score = Luxe_BMC_Score::evaluate( (int) $post_id );
		if ( empty( $score['all'] ) ) {
			Luxe_BMC_Safety::log( 'approval_blocked', 'APPROVAL BLOCKED blog #' . (int) $post_id . ': ' . implode( ', ', $score['fail'] ) . '. Nothing published.' );
			return array(
				'ok'     => false,
				'reason' => 'Not 95–100 all-green.',
				'score'  => $score,
			);
		}
		if ( 'publish' === $score['status'] ) {
			return array(
				'ok'     => false,
				'reason' => 'Already published. Protected/live.',
				'score'  => $score,
			);
		}
		update_post_meta( (int) $post_id, '_luxe_bmc_approval_ready', 1 );
		update_post_meta( (int) $post_id, '_luxe_bmc_approval_at', time() );
		Luxe_BMC_Safety::log( 'approval', 'APPROVAL READY blog #' . (int) $post_id . '. Status preserved. Nothing published.' );
		return array(
			'ok'     => true,
			'reason' => 'APPROVAL READY. Status unchanged.',
			'score'  => $score,
		);
	}
}
