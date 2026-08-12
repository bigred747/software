<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Titles, descriptions, robots, Open Graph. Output filters only.
 */
class Luxe_Score_Repair_SEO {

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
	 * Register SEO filters after Rank Math / Yoast.
	 */
	public function boot() {
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 999 );
		add_filter( 'wp_title', array( $this, 'wp_title' ), 999, 2 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ), 999 );
		add_filter( 'rank_math/frontend/title', array( $this, 'rank_math_title' ), 999 );
		add_filter( 'rank_math/frontend/description', array( $this, 'rank_math_description' ), 999 );
		add_filter( 'wpseo_title', array( $this, 'rank_math_title' ), 999 );
		add_filter( 'wpseo_metadesc', array( $this, 'rank_math_description' ), 999 );
		add_filter( 'get_the_excerpt', array( $this, 'maybe_excerpt' ), 40 );
		add_filter( 'wp_robots', array( $this, 'wp_robots' ), 999 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'rank_math_robots' ), 999 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'og_title' ), 999 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'og_description' ), 999 );
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'og_image' ), 999 );
		add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'og_title' ), 999 );
		add_filter( 'rank_math/opengraph/twitter/description', array( $this, 'og_description' ), 999 );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'og_image' ), 999 );
		add_action( 'wp_head', array( $this, 'print_meta_fallback' ), 1 );
		add_action( 'wp_head', array( $this, 'remove_generator' ), 1 );
		add_filter( 'the_generator', array( $this, 'empty_generator' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'image_attributes' ), 20, 3 );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function document_title( $title ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_titles' ) ) {
			return $title;
		}
		$fixed = $this->resolved_title( is_string( $title ) ? $title : '' );
		return $fixed ? $fixed : $title;
	}

	/**
	 * @param string $title Title.
	 * @param string $sep   Separator.
	 * @return string
	 */
	public function wp_title( $title, $sep ) {
		unset( $sep );
		return $this->document_title( $title );
	}

	/**
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function title_parts( $parts ) {
		if ( ! is_array( $parts ) || ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_titles' ) ) {
			return $parts;
		}
		$joined = isset( $parts['title'] ) ? (string) $parts['title'] : '';
		$fixed  = $this->resolved_title( $joined );
		if ( $fixed ) {
			return array(
				'title' => $fixed,
			);
		}
		return $parts;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function rank_math_title( $title ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_titles' ) ) {
			return $title;
		}
		$fixed = $this->resolved_title( is_string( $title ) ? $title : '' );
		return $fixed ? $fixed : $title;
	}

	/**
	 * @param string $desc Description.
	 * @return string
	 */
	public function rank_math_description( $desc ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_titles' ) ) {
			return $desc;
		}
		$fixed = $this->resolved_description( is_string( $desc ) ? $desc : '' );
		return $fixed ? $fixed : $desc;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function og_title( $title ) {
		return $this->rank_math_title( $title );
	}

	/**
	 * @param string $desc Description.
	 * @return string
	 */
	public function og_description( $desc ) {
		$clean = is_string( $desc ) ? html_entity_decode( wp_strip_all_tags( $desc ), ENT_QUOTES, 'UTF-8' ) : '';
		$clean = trim( preg_replace( '/\s+/', ' ', str_replace( "\xC2\xA0", ' ', $clean ) ) );
		if ( $this->is_front() ) {
			return Luxe_Score_Repair_Plugin::home_description();
		}
		if ( $this->is_weak_description( $clean ) ) {
			$fixed = $this->resolved_description( $clean );
			return $fixed ? $fixed : $clean;
		}
		return $clean ? $clean : $desc;
	}

	/**
	 * Replace the homepage Amazon laptop social image with the brand icon.
	 *
	 * @param string $image Image URL.
	 * @return string
	 */
	public function og_image( $image ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_open_graph' ) ) {
			return $image;
		}
		if ( ! $this->is_front() ) {
			return $image;
		}
		$brand = Luxe_Score_Repair_Plugin::brand_image();
		if ( $brand ) {
			return $brand;
		}
		if ( is_string( $image ) && false !== strpos( $image, 'media-amazon.com' ) ) {
			return $brand ? $brand : $image;
		}
		return $image;
	}

	/**
	 * @param string $excerpt Excerpt.
	 * @return string
	 */
	public function maybe_excerpt( $excerpt ) {
		if ( is_admin() || ! is_string( $excerpt ) ) {
			return $excerpt;
		}
		if ( $this->is_weak_description( $excerpt ) ) {
			$fixed = $this->resolved_description( $excerpt );
			return $fixed ? $fixed : $excerpt;
		}
		return $excerpt;
	}

	/**
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function wp_robots( $robots ) {
		if ( ! is_array( $robots ) || ! Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ) {
			return $robots;
		}
		if ( $this->should_noindex() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = false;
			$robots['follow']   = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * @param array $robots Rank Math robots.
	 * @return array
	 */
	public function rank_math_robots( $robots ) {
		if ( ! is_array( $robots ) || ! Luxe_Score_Repair_Plugin::instance()->enabled( 'noindex_utility' ) ) {
			return $robots;
		}
		if ( $this->should_noindex() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Early meta description if Rank Math printed a weak one later — buffer still wins.
	 */
	public function print_meta_fallback() {
		if ( is_admin() || ! Luxe_Score_Repair_Plugin::instance()->enabled( 'fix_titles' ) ) {
			return;
		}
		if ( ! $this->is_front() ) {
			return;
		}
		echo '<meta name="luxe-score-repair" content="home-meta-ready" />' . "\n";
	}

	/**
	 * Drop generator tags.
	 */
	public function remove_generator() {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'hide_generator' ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * @param string $gen Generator.
	 * @return string
	 */
	public function empty_generator( $gen ) {
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'hide_generator' ) ) {
			return $gen;
		}
		return '';
	}

	/**
	 * Fill missing alt text from the attachment title. Never overwrites a real alt.
	 *
	 * @param array        $attr       Attributes.
	 * @param WP_Post|null $attachment Attachment.
	 * @param string|int[] $size       Size.
	 * @return array
	 */
	public function image_attributes( $attr, $attachment, $size ) {
		unset( $size );
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'image_alts' ) || ! is_array( $attr ) ) {
			return $attr;
		}
		$alt = isset( $attr['alt'] ) ? trim( (string) $attr['alt'] ) : '';
		if ( '' !== $alt ) {
			return $attr;
		}
		$title = '';
		if ( $attachment && isset( $attachment->post_title ) ) {
			$title = trim( wp_strip_all_tags( (string) $attachment->post_title ) );
		}
		if ( '' === $title ) {
			$title = Luxe_Score_Repair_Plugin::brand_name();
		}
		$attr['alt'] = $title;
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/**
	 * @return bool
	 */
	public function is_front() {
		return is_front_page() && ! is_paged();
	}

	/**
	 * @return bool
	 */
	public function should_noindex() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}
		if ( is_search() || is_author() || is_date() || is_attachment() ) {
			return true;
		}
		if ( function_exists( 'is_wishlist' ) && is_wishlist() ) {
			return true;
		}
		$slug = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && isset( $post->post_name ) ) {
				$slug = (string) $post->post_name;
			}
		}
		$path = Luxe_Score_Repair_Plugin::request_path();
		$tail = trim( strtolower( basename( $path ) ), '/' );
		$need = Luxe_Score_Repair_Plugin::noindex_slugs();
		if ( $slug && in_array( $slug, $need, true ) ) {
			return true;
		}
		if ( $tail && in_array( $tail, $need, true ) ) {
			return true;
		}
		if ( 0 === strpos( $tail, 'test-' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $title Incoming title.
	 * @return string Empty when no change is required.
	 */
	public function resolved_title( $title ) {
		$title = $this->plain( $title );
		if ( $this->is_front() ) {
			return Luxe_Score_Repair_Plugin::home_title();
		}
		if ( $this->is_weak_title( $title ) ) {
			if ( is_singular() ) {
				$raw = get_post_field( 'post_title', get_queried_object_id() );
				$raw = Luxe_Score_Repair_Content::clean_title_text( is_string( $raw ) ? $raw : '' );
				if ( $raw && ! $this->is_weak_title( $raw ) ) {
					return $this->append_brand( $raw );
				}
			}
			if ( is_post_type_archive( 'product' ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
				return 'Shop Luxury Tech & Premium Gear | ' . Luxe_Score_Repair_Plugin::brand_name();
			}
			if ( is_home() ) {
				return 'Premium Buyer Guides & Reviews | ' . Luxe_Score_Repair_Plugin::brand_name();
			}
		}
		$cleaned = Luxe_Score_Repair_Content::clean_title_text( $title );
		if ( $cleaned && $cleaned !== $title ) {
			return $this->append_brand( $cleaned );
		}
		return '';
	}

	/**
	 * @param string $desc Incoming description.
	 * @return string
	 */
	public function resolved_description( $desc ) {
		$desc = $this->plain( $desc );
		if ( $this->is_front() ) {
			return Luxe_Score_Repair_Plugin::home_description();
		}
		if ( $this->is_weak_description( $desc ) ) {
			if ( is_singular() ) {
				$post = get_queried_object();
				if ( $post && ! empty( $post->post_excerpt ) ) {
					$ex = $this->plain( $post->post_excerpt );
					if ( $ex && ! $this->is_weak_description( $ex ) ) {
						return $this->clip( $ex, 158 );
					}
				}
				$title = is_singular() ? Luxe_Score_Repair_Content::clean_title_text( get_the_title() ) : '';
				if ( $title ) {
					return $this->clip( $title . ' — compare features, fit, warranty, and current Amazon price before you buy. ' . Luxe_Score_Repair_Plugin::brand_name() . ' buyer guide.', 158 );
				}
			}
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				return 'Shop curated luxury tech, watches, drones, audio, and smart gear. Compare premium Amazon picks with clear buyer notes from LuxeTrendsetters.';
			}
		}
		return '';
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public function is_weak_title( $title ) {
		$title = $this->plain( $title );
		if ( '' === $title ) {
			return true;
		}
		$needles = array(
			'HOME: Features',
			'Features, Use Cases and Buyer Fit',
			'Buying Guide: Materials, Style and Value',
			'Review: Health Features, Battery and Value',
			'Performance, Display and B',
			'Client Portal Review',
			'Test Blog Page',
		);
		foreach ( $needles as $needle ) {
			if ( false !== stripos( $title, $needle ) ) {
				return true;
			}
		}
		if ( preg_match( '/^(HOME|Shop Shopping Guide for Top Products)\b/i', $title ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $desc Description.
	 * @return bool
	 */
	public function is_weak_description( $desc ) {
		$desc = $this->plain( $desc );
		if ( '' === $desc || strlen( $desc ) < 40 ) {
			return true;
		}
		$needles = array(
			'HOME review:',
			'compare listed features, compatibility, practical tradeoffs',
			'Learn everything about',
			'full analysis, buying guide, pros/cons, and essential insights',
			'across relevant fine jewelry',
			'across relevant product options',
		);
		foreach ( $needles as $needle ) {
			if ( false !== stripos( $desc, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	private function append_brand( $title ) {
		$brand = Luxe_Score_Repair_Plugin::brand_name();
		if ( false !== stripos( $title, $brand ) ) {
			return $this->clip( $title, 60 );
		}
		$out = $title . ' | ' . $brand;
		return $this->clip( $out, 60 );
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	private function clip( $text, $max ) {
		$text = $this->plain( $text );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = substr( $text, 0, $max - 1 );
		$sp  = strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > 40 ) {
			$cut = substr( $cut, 0, $sp );
		}
		return rtrim( $cut, ' ,;:-' ) . '…';
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private function plain( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}
}
