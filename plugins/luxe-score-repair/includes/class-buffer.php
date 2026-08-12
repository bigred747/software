<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Last-pass HTML repair for tags other plugins print after wp_head filters.
 */
class Luxe_Score_Repair_Buffer {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $started = false;

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
	 * Buffer public HTML only.
	 */
	public function boot() {
		add_action( 'template_redirect', array( $this, 'start' ), 0 );
	}

	/**
	 * Start output buffer.
	 */
	public function start() {
		if ( $this->started ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::instance()->enabled( 'buffer_html' ) ) {
			return;
		}
		if ( ! Luxe_Score_Repair_Plugin::is_public_html_request() ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$this->started = true;
		ob_start( array( $this, 'rewrite' ) );
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	public function rewrite( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 32 ) {
			return $html;
		}
		if ( false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$plugin = Luxe_Score_Repair_Plugin::instance();
		$seo    = Luxe_Score_Repair_SEO::instance();

		if ( $plugin->enabled( 'fix_schema' ) ) {
			$html = Luxe_Score_Repair_Schema::instance()->sanitize_html( $html );
		}

		if ( $plugin->enabled( 'fix_titles' ) ) {
			$title = $seo->resolved_title( $this->tag_inner( $html, 'title' ) );
			if ( $title ) {
				$html = preg_replace( '/<title[^>]*>.*?<\/title>/is', '<title>' . esc_html( $title ) . '</title>', $html, 1 );
			}
			$desc = $seo->resolved_description( $this->meta_content( $html, 'description' ) );
			if ( $desc ) {
				$html = $this->upsert_meta( $html, 'name', 'description', $desc );
			}
			if ( $seo->is_front() ) {
				$home_title = Luxe_Score_Repair_Plugin::home_title();
				$home_desc  = Luxe_Score_Repair_Plugin::home_description();
				$html       = $this->upsert_meta( $html, 'property', 'og:title', $home_title );
				$html       = $this->upsert_meta( $html, 'property', 'og:description', $home_desc );
				$html       = $this->upsert_meta( $html, 'name', 'twitter:title', $home_title );
				$html       = $this->upsert_meta( $html, 'name', 'twitter:description', $home_desc );
				$html       = preg_replace( '/<meta[^>]+name=["\']twitter:label[12]["\'][^>]*>\s*/i', '', $html );
				$html       = preg_replace( '/<meta[^>]+name=["\']twitter:data[12]["\'][^>]*>\s*/i', '', $html );
			}
		}

		if ( $plugin->enabled( 'fix_open_graph' ) && $seo->is_front() ) {
			$brand = Luxe_Score_Repair_Plugin::brand_image();
			if ( $brand ) {
				$html = $this->upsert_meta( $html, 'property', 'og:image', $brand );
				$html = $this->upsert_meta( $html, 'property', 'og:image:secure_url', $brand );
				$html = $this->upsert_meta( $html, 'name', 'twitter:image', $brand );
				$html = $this->upsert_meta( $html, 'property', 'og:image:alt', Luxe_Score_Repair_Plugin::brand_name() );
			}
		}

		if ( $plugin->enabled( 'noindex_utility' ) && $seo->should_noindex() ) {
			$html = $this->upsert_meta( $html, 'name', 'robots', 'noindex, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' );
		}

		if ( $plugin->enabled( 'hide_generator' ) ) {
			$html = preg_replace( '/<meta[^>]+name=["\']generator["\'][^>]*>\s*/i', '', $html );
		}

		if ( $plugin->enabled( 'image_alts' ) ) {
			$html = preg_replace_callback(
				'/<img\b([^>]*)>/i',
				function ( $m ) {
					$tag = $m[0];
					if ( preg_match( '/\balt\s*=/i', $tag ) ) {
						return $tag;
					}
					$alt = Luxe_Score_Repair_Plugin::brand_name();
					if ( preg_match( '/\btitle=("|\')([^"\']+)\1/i', $tag, $tm ) ) {
						$alt = $tm[2];
					}
					return '<img alt="' . esc_attr( $alt ) . '"' . $m[1] . '>';
				},
				$html
			);
		}

		if ( $plugin->enabled( 'clean_content' ) ) {
			$html = preg_replace( '/\[(\/?ez-?toc|toc)[^\]]*\]/i', '', $html );
		}

		if ( $plugin->enabled( 'process_learning' ) && $seo->is_front() && ! is_user_logged_in() ) {
			Luxe_Score_Repair_Learning::capture_from_html( $html );
		}

		return $html;
	}

	/**
	 * @param string $html HTML.
	 * @param string $tag  Tag.
	 * @return string
	 */
	private function tag_inner( $html, $tag ) {
		if ( preg_match( '/<' . preg_quote( $tag, '/' ) . '[^>]*>(.*?)<\/' . preg_quote( $tag, '/' ) . '>/is', $html, $m ) ) {
			return wp_strip_all_tags( $m[1] );
		}
		return '';
	}

	/**
	 * @param string $html HTML.
	 * @param string $name Name.
	 * @return string
	 */
	private function meta_content( $html, $name ) {
		if ( preg_match( '/<meta[^>]+name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote( $name, '/' ) . '["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		return '';
	}

	/**
	 * @param string $html     HTML.
	 * @param string $attr     name or property.
	 * @param string $key      Key.
	 * @param string $content  Content.
	 * @return string
	 */
	private function upsert_meta( $html, $attr, $key, $content ) {
		$esc = esc_attr( $content );
		$tag = '<meta ' . $attr . '="' . esc_attr( $key ) . '" content="' . $esc . '" />';
		$re  = '/<meta[^>]+' . preg_quote( $attr, '/' ) . '=["\']' . preg_quote( $key, '/' ) . '["\'][^>]*>/i';
		if ( preg_match( $re, $html ) ) {
			return preg_replace( $re, $tag, $html, 1 );
		}
		return preg_replace( '/<title\b/i', $tag . "\n<title", $html, 1 );
	}
}
