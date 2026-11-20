<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Junk-path classifier. Recommendations only until an admin approves noindex.
 */
class LUAE_Junk {

	/**
	 * @param string $path Path or URL.
	 * @return string
	 */
	public static function normalize( $path ) {
		$path = (string) $path;
		if ( false !== strpos( $path, '://' ) ) {
			$parsed = parse_url( $path, PHP_URL_PATH );
			$path   = is_string( $parsed ) ? $parsed : '/';
		}
		$path = '/' . trim( strtolower( $path ), '/' );
		if ( '/' === $path ) {
			return '/';
		}
		return $path . '/';
	}

	/**
	 * @param string $path Path.
	 * @return string Empty when the path is a real page. Otherwise noindex or improve.
	 */
	public static function action( $path ) {
		$path = self::normalize( $path );
		if ( '/' === $path ) {
			return '';
		}
		if ( preg_match( '#^/item-[a-z0-9-]+/#', $path ) ) {
			return 'noindex';
		}
		if ( preg_match( '#^/pa_[a-z0-9-]+/#', $path ) ) {
			return 'noindex';
		}
		if ( preg_match( '#^/product-tag/#', $path ) ) {
			return 'noindex';
		}
		return '';
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	public static function reason( $path ) {
		$action = self::action( $path );
		if ( 'noindex' !== $action ) {
			return '';
		}
		$path = self::normalize( $path );
		if ( 0 === strpos( $path, '/item-' ) ) {
			return 'Amazon spec archive. Noindex it. Do not redirect it to the homepage.';
		}
		if ( 0 === strpos( $path, '/pa_' ) ) {
			return 'Product attribute archive. Noindex it.';
		}
		if ( false !== strpos( $path, '/page/' ) ) {
			return 'Product-tag pagination. Noindex it.';
		}
		return 'Product-tag archive. Noindex it, including its pagination.';
	}

	/**
	 * Only archives this plugin is allowed to inspect.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $rewrite  Rewrite slug.
	 * @return bool
	 */
	public static function watched_taxonomy( $taxonomy, $rewrite ) {
		$taxonomy = strtolower( (string) $taxonomy );
		$rewrite  = strtolower( (string) $rewrite );
		if ( 'product_tag' === $taxonomy || 'product_cat' === $taxonomy ) {
			return true;
		}
		if ( 0 === strpos( $taxonomy, 'pa_' ) || 0 === strpos( $rewrite, 'pa_' ) ) {
			return true;
		}
		if ( 0 === strpos( $rewrite, 'item-' ) || 0 === strpos( $taxonomy, 'item-' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $rewrite Rewrite slug.
	 * @param string $slug    Term slug.
	 * @return string
	 */
	public static function term_path( $rewrite, $slug ) {
		$rewrite = trim( (string) $rewrite, '/' );
		$slug    = trim( (string) $slug, '/' );
		if ( '' === $rewrite || '' === $slug ) {
			return '/';
		}
		return self::normalize( '/' . $rewrite . '/' . $slug . '/' );
	}

	/**
	 * Keep the noindex list short. Homepage and real pages never enter it.
	 *
	 * @param string[] $current Current paths.
	 * @param string[] $paths   Requested paths.
	 * @return string[]
	 */
	public static function merge_noindex( $current, $paths ) {
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$merged = array();
		foreach ( $current as $path ) {
			$normal = self::normalize( $path );
			if ( 'noindex' === self::action( $normal ) ) {
				$merged[] = $normal;
			}
		}
		$merged = array_values( array_unique( $merged ) );
		if ( ! is_array( $paths ) ) {
			return array_slice( $merged, 0, 200 );
		}
		foreach ( $paths as $path ) {
			if ( count( $merged ) >= 200 ) {
				break;
			}
			if ( 'noindex' !== self::action( $path ) ) {
				continue;
			}
			$merged[] = self::normalize( $path );
		}
		return array_values( array_unique( $merged ) );
	}

	/**
	 * @param string   $path     Request path.
	 * @param string[] $approved Approved paths.
	 * @return bool
	 */
	public static function matches_approved( $path, $approved ) {
		$request = self::normalize( $path );
		if ( '/' === $request || ! is_array( $approved ) ) {
			return false;
		}
		foreach ( $approved as $item ) {
			$item = self::normalize( $item );
			if ( '/' === $item ) {
				continue;
			}
			if ( $request === $item ) {
				return true;
			}
			$base = rtrim( $item, '/' );
			if ( 0 === strpos( $request, $base . '/page/' ) ) {
				return true;
			}
		}
		return false;
	}
}
