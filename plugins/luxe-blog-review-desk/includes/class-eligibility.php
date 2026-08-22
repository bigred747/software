<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LBRD_Eligibility' ) ) {
	return;
}

/**
 * Pure publish gates. Command Center still never auto-publishes.
 */
class LBRD_Eligibility {

	const MASTER_ID = 10833;

	/**
	 * @param array $row Post facts.
	 * @return array{ok:bool,reason:string}
	 */
	public static function publish_gate( $row ) {
		$id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$status  = isset( $row['status'] ) ? (string) $row['status'] : '';
		$product = isset( $row['product'] ) ? (int) $row['product'] : 0;
		$score   = isset( $row['score'] ) ? (int) $row['score'] : 0;
		$ready   = ! empty( $row['approval'] );

		if ( self::MASTER_ID === $id ) {
			return array(
				'ok'     => false,
				'reason' => 'Master #10833 is read-only.',
			);
		}
		if ( 'publish' === $status ) {
			return array(
				'ok'     => false,
				'reason' => 'Already live. Use View.',
			);
		}
		if ( ! in_array( $status, array( 'draft', 'pending' ), true ) ) {
			return array(
				'ok'     => false,
				'reason' => 'Only draft or pending posts can be published here.',
			);
		}
		if ( $product < 1 ) {
			return array(
				'ok'     => false,
				'reason' => 'Product #0 is blocked. Re-link a real product first.',
			);
		}
		if ( $ready || $score >= 95 ) {
			return array(
				'ok'     => true,
				'reason' => 'Approval Ready or 95–100. View, then Publish.',
			);
		}
		return array(
			'ok'     => false,
			'reason' => 'Not 95–100 yet. Repair in Command Center, then FINAL VERIFY.',
		);
	}

	/**
	 * @return string[]
	 */
	public static function product_keys() {
		return array(
			'_luxe_locked_product',
			'_luxe_locked_product_id',
			'_lbm_locked_product',
			'_luxe_cc_product',
			'_luxe_blog_product',
			'_luxe_product_lock',
			'luxe_locked_product',
		);
	}

	/**
	 * @return string[]
	 */
	public static function score_keys() {
		return array(
			'_luxe_cc_score',
			'_lbm_score',
			'_luxe_blog_score',
			'_luxe_approval_score',
			'_luxe_reader_love_score',
		);
	}

	/**
	 * @return string[]
	 */
	public static function approval_keys() {
		return array(
			'_luxe_approval_ready',
			'_lbm_approval_ready',
			'_luxe_cc_approval',
			'_luxe_final_verify',
		);
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string[] $keys   Meta keys.
	 * @return mixed
	 */
	public static function first_meta( $post_id, $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && false !== $value && null !== $value ) {
				return $value;
			}
		}
		return '';
	}
}
