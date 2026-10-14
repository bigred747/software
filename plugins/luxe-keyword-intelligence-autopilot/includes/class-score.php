<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honest keyword score for fields this plugin is allowed to repair.
 * 100 means focus, title, description, and phrase count all pass.
 * 95 means those pass and only an optional image label is still open.
 */
class Luxe_KIA_Score {

	/**
	 * @param array $item    Item fields.
	 * @param array $phrases Phrases. The first phrase is the focus used for matching.
	 * @param bool  $alt_required Image label is required for 100.
	 * @return array{score:int,status:string}
	 */
	public static function evaluate( $item, $phrases = array(), $alt_required = false ) {
		$item    = is_array( $item ) ? $item : array();
		$phrases = self::phrases( $item, $phrases );
		$focus   = $phrases ? (string) $phrases[0] : '';
		$title   = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		$seo     = isset( $item['seo_title'] ) ? trim( (string) $item['seo_title'] ) : '';
		$desc    = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		$shown   = ( '' === $seo || false !== strpos( $seo, '%' ) ) ? $title : $seo;
		$score   = 0;
		$focus_ok = '' !== $focus && ! Luxe_KIA_Phrases::is_junk( $focus );
		if ( $focus_ok ) {
			$score += 20;
		}
		if ( $focus_ok && ( false !== stripos( $title, $focus ) || false !== stripos( $shown, $focus ) ) ) {
			$score += 20;
		}
		$length = strlen( $shown );
		if ( $length >= 15 && $length <= 60 ) {
			$score += 20;
		}
		$desc_length = strlen( $desc );
		if ( $focus_ok && $desc_length >= 70 && $desc_length <= 160 && false !== stripos( $desc, $focus ) ) {
			$score += 25;
		}
		if ( count( $phrases ) >= 3 ) {
			$score += 15;
		}
		if ( $alt_required && ( empty( $item['has_image'] ) || ! empty( $item['alt_broken'] ) ) && $score > 95 ) {
			$score = 95;
		}
		if ( $score > 100 ) {
			$score = 100;
		}
		return array(
			'score'  => $score,
			'status' => $score >= 95 ? 'GREEN' : 'ATTENTION',
		);
	}

	/**
	 * @param array $item Item.
	 * @param array $phrases Phrases.
	 * @return array<int,string>
	 */
	private static function phrases( $item, $phrases ) {
		if ( is_array( $phrases ) && $phrases ) {
			return array_values( $phrases );
		}
		$focus = isset( $item['focus'] ) ? (string) $item['focus'] : '';
		$parts = array_filter( array_map( 'trim', explode( ',', $focus ) ) );
		return array_values( $parts );
	}
}
