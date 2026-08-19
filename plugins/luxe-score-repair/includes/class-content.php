<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display-only content and title cleanup. Never writes post_content.
 */
class Luxe_Score_Repair_Content {

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
	 * Register content filters.
	 */
	public function boot() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 36 );
		add_filter( 'the_title', array( $this, 'filter_title' ), 20, 2 );
		add_filter( 'woocommerce_product_title', array( $this, 'filter_product_title' ), 20 );
		add_action( 'wp_head', array( $this, 'affiliate_meta' ), 20 );
		add_action( 'wp_footer', array( $this, 'affiliate_disclosure' ), 20 );
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( is_admin() || is_feed() || ! is_string( $content ) ) {
			return $content;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'clean_content' ) ) {
			return $content;
		}
		$content = preg_replace( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', '', $content );
		$content = preg_replace( '/This section has been expanded automatically[\s\S]{0,1200}/i', '', $content );
		$content = preg_replace( '/<[^>]*>\s*Expanded Analysis:[\s\S]{0,1600}?(?:<\/(div|section|article|p)>)/i', '', $content );
		$content = preg_replace( '/Additional insights, detailed explanations, and contextual information have been added using AI-driven rewriting\.[\s\S]{0,500}/i', '', $content );
		$content = preg_replace( '/Topics covered may include product features, comparisons, buying guides[\s\S]{0,400}/i', '', $content );
		$content = preg_replace( '/This product passed the site quality filter because[\s\S]{0,280}/i', '', $content );
		$content = str_replace( array( 'Didn217;t', 'Accessoriors' ), array( "Didn't", 'Accessories' ), $content );
		return $content;
	}

	/**
	 * @param string $title Title.
	 * @param int    $id    Post ID.
	 * @return string
	 */
	public function filter_title( $title, $id ) {
		unset( $id );
		if ( is_admin() || ! is_string( $title ) ) {
			return $title;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'clean_titles' ) ) {
			return $title;
		}
		return self::clean_title_text( $title );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public function filter_product_title( $title ) {
		if ( is_admin() || ! is_string( $title ) ) {
			return $title;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'clean_titles' ) ) {
			return $title;
		}
		return self::clean_title_text( $title );
	}

	/**
	 * Strip concatenated second-product tails such as "Rolex … – canon eos r5 mark ii 2025".
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function clean_title_text( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = trim( preg_replace( '/\s+/', ' ', $title ) );
		$brands = 'canon|sony|nikon|apple|samsung|dji|hp|asus|bose|rolex|seiko|gucci|fendi|prada|omega';
		if ( preg_match( '/^(.+?)\s+[–—-]\s+(.+)$/u', $title, $m ) ) {
			$left  = trim( $m[1] );
			$right = trim( $m[2] );
			if ( preg_match( '/\b(?:' . $brands . ')\b/i', $left ) && preg_match( '/\b(?:' . $brands . ')\b/i', $right ) ) {
				$left_brand  = self::first_brand( $left, $brands );
				$right_brand = self::first_brand( $right, $brands );
				if ( $left_brand && $right_brand && strcasecmp( $left_brand, $right_brand ) !== 0 ) {
					$title = $left;
				}
			}
		}
		$title = str_replace( array( 'Didn217;t', 'Accessoriors' ), array( "Didn't", 'Accessories' ), $title );
		return $title;
	}

	/**
	 * @param string $text   Text.
	 * @param string $brands Alternation.
	 * @return string
	 */
	private static function first_brand( $text, $brands ) {
		if ( preg_match( '/\b(' . $brands . ')\b/i', $text, $m ) ) {
			return strtolower( $m[1] );
		}
		return '';
	}

	/**
	 * Amazon Associates identification. Official required statement, not copied third-party copy.
	 *
	 * @return string
	 */
	public static function associate_identification() {
		return 'As an Amazon Associate I earn from qualifying purchases.';
	}

	/**
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function html_has_identification( $html ) {
		return is_string( $html ) && false !== stripos( $html, self::associate_identification() );
	}

	/**
	 * Visible footer identification. Official required phrase first. Own site sentence after.
	 */
	public function affiliate_disclosure() {
		if ( ! self::may_print_identification() ) {
			return;
		}
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		echo self::identification_paragraph();
	}

	/**
	 * Scanner-visible official phrase in the document head.
	 */
	public function affiliate_meta() {
		if ( ! self::may_print_identification() ) {
			return;
		}
		$id = self::associate_identification();
		echo '<meta name="amazon-associate" content="' . esc_attr( $id ) . '" />' . "\n";
	}

	/**
	 * Last-pass HTML inject if the theme skipped wp_footer.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function ensure_identification_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( false !== stripos( $html, 'luxe-score-repair-disclosure' ) ) {
			return $html;
		}
		$block = self::identification_paragraph();
		if ( preg_match( '/<\/body>/i', $html ) ) {
			return preg_replace( '/<\/body>/i', $block . '</body>', $html, 1 );
		}
		return $html . $block;
	}

	/**
	 * @return string
	 */
	public static function identification_paragraph() {
		$id = self::associate_identification();
		return '<p class="luxe-score-repair-disclosure" data-amazon-associate="identification" style="text-align:center;font-size:13px;line-height:1.5;color:#475569;padding:12px 16px 28px;">' . esc_html( $id ) . ' LuxeTrendsetters is a participant in the Amazon Services LLC Associates Program. Prices and availability can change on Amazon.</p>';
	}

	/**
	 * @return bool
	 */
	private static function may_print_identification() {
		if ( is_admin() || is_feed() ) {
			return false;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'affiliate_disclosure' ) ) {
			return false;
		}
		if ( ! Luxe_Score_Repair_Plugin::is_public_html_request() ) {
			return false;
		}
		return true;
	}
}
