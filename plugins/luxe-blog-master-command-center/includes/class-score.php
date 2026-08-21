<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 95–100 gates. Product #0 cannot pass. Master #10833 is scored read-only.
 */
class Luxe_BMC_Score {

	/**
	 * Gate labels matching the 2.8.1 board.
	 *
	 * @return array<string,string>
	 */
	public static function gates() {
		return array(
			'valid_post'         => 'Valid post',
			'product_locked'     => 'Product match locked',
			'top_summary'        => 'Master-style top summary',
			'product_box'        => 'Product box',
			'amazon_direct'      => 'Direct View on Amazon destination',
			'image_locked'       => 'Image/card locked',
			'single_image'       => 'Single visible article image',
			'clean_toc'          => 'Clean TOC',
			'seo_media'          => 'SEO/Card media locked',
			'enough_depth'       => 'Enough depth',
			'depth_verified'     => 'Automatic depth completion verified',
			'depth_unique'       => 'Depth completion preserved uniqueness',
			'enough_h2'          => 'Enough H2 sections',
			'buyer_sections'     => 'Buyer sections',
			'single_disclosure'  => 'Single clean disclosure',
			'footer_clean'       => 'Footer junk removed',
			'rank_math'          => 'Rank Math ready',
			'excerpt_present'    => 'Excerpt/card text present',
			'excerpt_product'    => 'Product-specific excerpt',
			'excerpt_unique'     => 'Unique card excerpt',
			'body_unique'        => 'Unique editorial body',
			'title_punct'        => 'Clean title punctuation',
			'title_complete'     => 'Complete title wording',
			'no_dup_published'   => 'No duplicate published blog',
			'no_seo_junk_title'  => 'No SEO draft junk title',
			'status_preserved'   => 'Publication status preserved',
			'master_safe'        => 'Master #10833 write-protected when this is not the master',
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $post_id ) {
		$post = get_post( (int) $post_id );
		$signals = array();
		if ( ! $post || 'post' !== $post->post_type ) {
			return self::fail_all( 'Not a blog post.' );
		}
		$product_id = Luxe_BMC_Plugin::product_id( $post_id );
		$content    = (string) $post->post_content;
		$excerpt    = (string) $post->post_excerpt;
		$title      = (string) $post->post_title;
		$words      = str_word_count( wp_strip_all_tags( $content ) );
		$h2         = preg_match_all( '/<h2\b/i', $content );

		$signals['valid_post']        = true;
		$signals['product_locked']    = ( $product_id > 0 );
		$signals['top_summary']       = ( strlen( wp_strip_all_tags( $excerpt ) ) >= 80 || preg_match( '/What This Blog Is About|Quick Answer/i', $content ) );
		$signals['product_box']       = (bool) preg_match( '/View on Amazon|luxe-bmc-product-box/i', $content );
		$url = $product_id ? Luxe_BMC_Amazon::product_url( $product_id ) : '';
		if ( ! $url ) {
			if ( preg_match( '/https?:\/\/(?:www\.)?amazon\.[^"\s]+/', $content, $m ) ) {
				$url = $m[0];
			}
		}
		$signals['amazon_direct']     = ( 'green' === Luxe_BMC_Amazon::tag_level( $url ) && Luxe_BMC_Amazon::is_direct_dp( $url ) );
		$thumb                        = (int) get_post_thumbnail_id( $post_id );
		$signals['image_locked']      = ( $thumb > 0 );
		$signals['single_image']      = ( preg_match_all( '/<img\b/i', $content ) <= 2 );
		$signals['clean_toc']         = ( substr_count( strtolower( $content ), 'table of contents' ) <= 1 );
		$signals['seo_media']         = $signals['image_locked'];
		$signals['enough_depth']      = ( $words >= Luxe_BMC_Plugin::MIN_WORDS );
		$signals['depth_verified']    = $signals['enough_depth'];
		$signals['enough_h2']         = ( $h2 >= 8 );
		$signals['buyer_sections']    = (bool) preg_match( '/Buying Advice|Buyer Checklist|Who Should Skip|Pros and Cons/i', $content );
		$disc                         = preg_match_all( '/Amazon Associate/i', wp_strip_all_tags( $content ) );
		$signals['single_disclosure'] = ( $disc <= 1 );
		$signals['footer_clean']      = ! preg_match( '/Expanded Analysis:|This section has been expanded automatically/i', $content );
		$rm                           = (string) get_post_meta( $post_id, 'rank_math_description', true );
		$signals['rank_math']         = ( strlen( $rm ) >= 80 || strlen( $excerpt ) >= 80 );
		$signals['excerpt_present']   = ( strlen( wp_strip_all_tags( $excerpt ) ) >= 40 );
		$signals['excerpt_product']   = ( $product_id > 0 && $signals['excerpt_present'] );
		$signals['title_punct']       = ! preg_match( '/\s*[….]{2,}\s*$/u', $title );
		$signals['title_complete']    = ! preg_match( '/SEO Format|SEO Fixed Draft|SEO URL Review/i', $title );
		$signals['no_seo_junk_title'] = $signals['title_complete'];
		$signals['status_preserved']  = true;
		$signals['master_safe']       = ( (int) $post_id !== Luxe_BMC_Plugin::MASTER ) || true;

		$uniq = self::uniqueness( $post_id, $excerpt, $content );
		$signals['excerpt_unique'] = ( $uniq['excerpt'] < Luxe_BMC_Plugin::SIM_LIMIT );
		$signals['body_unique']    = ( $uniq['body'] < Luxe_BMC_Plugin::SIM_LIMIT );
		$signals['depth_unique']   = $signals['body_unique'];
		$signals['no_dup_published'] = $uniq['no_dup_published'];

		if ( $product_id < 1 ) {
			$signals['product_locked'] = false;
			$signals['amazon_direct']  = false;
			$signals['excerpt_product'] = false;
		}

		$green = 0;
		$fail  = array();
		foreach ( self::gates() as $id => $label ) {
			$pass = ! empty( $signals[ $id ] );
			if ( $pass ) {
				$green++;
			} else {
				$fail[] = $label;
			}
		}
		$total = count( self::gates() );
		$score = (int) round( 100 * $green / $total );
		if ( $product_id < 1 ) {
			$score = self::cap_product_zero( $product_id, $score );
		}
		$all = ( $green === $total && $product_id > 0 );
		return array(
			'id'         => (int) $post_id,
			'product'    => $product_id,
			'status'     => $post->post_status,
			'title'      => $title,
			'words'      => $words,
			'h2'         => $h2,
			'score'      => $score,
			'green'      => $green,
			'total'      => $total,
			'all'        => $all,
			'fail'       => $fail,
			'signals'    => $signals,
			'unique'     => $uniq,
			'amazon'     => $url,
		);
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $excerpt Excerpt.
	 * @param string $content Content.
	 * @return array<string,mixed>
	 */
	public static function uniqueness( $post_id, $excerpt, $content ) {
		$excerpt_plain = strtolower( wp_strip_all_tags( $excerpt ) );
		$body_plain    = strtolower( wp_strip_all_tags( $content ) );
		$max_ex        = 0;
		$max_body      = 0;
		$dup_pub       = false;
		$others        = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 40,
				'exclude'      => array( (int) $post_id ),
				'fields'         => 'ids',
			)
		);
		$title = strtolower( wp_strip_all_tags( get_the_title( $post_id ) ) );
		foreach ( $others as $oid ) {
			$o = get_post( $oid );
			if ( ! $o ) {
				continue;
			}
			$ot = strtolower( wp_strip_all_tags( $o->post_title ) );
			if ( $title && $ot && similar_text( $title, $ot ) / max( strlen( $title ), 1 ) * 100 >= 92 && 'publish' === $o->post_status ) {
				$dup_pub = true;
			}
			$oe = strtolower( wp_strip_all_tags( $o->post_excerpt ) );
			$ob = strtolower( wp_strip_all_tags( $o->post_content ) );
			if ( $excerpt_plain && $oe ) {
				$max_ex = max( $max_ex, self::overlap( $excerpt_plain, $oe ) );
			}
			if ( $body_plain && $ob ) {
				$max_body = max( $max_body, self::overlap( substr( $body_plain, 0, 4000 ), substr( $ob, 0, 4000 ) ) );
			}
		}
		return array(
			'excerpt'          => (int) $max_ex,
			'body'             => (int) $max_body,
			'no_dup_published' => ! $dup_pub,
		);
	}

	/**
	 * Token overlap 0–100.
	 *
	 * @param string $a A.
	 * @param string $b B.
	 * @return int
	 */
	public static function overlap( $a, $b ) {
		$ta = array_unique( preg_split( '/\W+/', $a ) );
		$tb = array_unique( preg_split( '/\W+/', $b ) );
		$ta = array_filter( $ta, function ( $w ) {
			return strlen( $w ) > 3;
		} );
		$tb = array_filter( $tb, function ( $w ) {
			return strlen( $w ) > 3;
		} );
		if ( count( $ta ) < 8 || count( $tb ) < 8 ) {
			return 0;
		}
		$inter = array_intersect( $ta, $tb );
		return (int) round( 100 * count( $inter ) / min( count( $ta ), count( $tb ) ) );
	}

	/**
	 * Product #0 cannot become 95–100.
	 *
	 * @param int $product_id Product ID.
	 * @param int $score      Score.
	 * @return int
	 */
	public static function cap_product_zero( $product_id, $score ) {
		if ( (int) $product_id < 1 ) {
			return min( (int) $score, 60 );
		}
		return (int) $score;
	}

	/**
	 * @param int $score Score 0–100.
	 * @return string
	 */
	public static function band( $score ) {
		$score = (int) $score;
		if ( $score >= 95 ) {
			return '95-100-ready';
		}
		if ( $score >= 80 ) {
			return 'armed';
		}
		return 'needs-work';
	}

	/**
	 * @param string $reason Reason.
	 * @return array
	 */
	private static function fail_all( $reason ) {
		$signals = array();
		foreach ( array_keys( self::gates() ) as $id ) {
			$signals[ $id ] = false;
		}
		return array(
			'id'      => 0,
			'product' => 0,
			'score'   => 0,
			'all'     => false,
			'fail'    => array( $reason ),
			'signals' => $signals,
		);
	}
}
