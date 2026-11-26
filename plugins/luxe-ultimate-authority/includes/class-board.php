<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One signal per process. Green means the check is clear. Yellow means it needs you.
 */
class LUAE_Board {

	/**
	 * @return string[]
	 */
	public static function ids() {
		$ids = array();
		foreach ( self::catalog() as $row ) {
			$ids[] = $row['id'];
		}
		return $ids;
	}

	/**
	 * @return array<int,array{id:string,group:string,label:string,level:string,detail:string}>
	 */
	public static function armed() {
		$signals = array();
		foreach ( self::catalog() as $row ) {
			$signals[] = array(
				'id'     => $row['id'],
				'group'  => $row['group'],
				'label'  => $row['label'],
				'level'  => 'green',
				'detail' => 'Armed. Run a scan before treating this as a content result.',
			);
		}
		return $signals;
	}

	/**
	 * @param array<string,int|float|null> $stats Stats.
	 * @param array<string,mixed>          $env   Environment.
	 * @return array<int,array{id:string,group:string,label:string,level:string,detail:string}>
	 */
	public static function build( $stats, $env ) {
		$stats = is_array( $stats ) ? $stats : array();
		$env   = is_array( $env ) ? $env : array();
		$scanned = isset( $stats['scanned'] ) ? (int) $stats['scanned'] : 0;
		$rows    = array();
		foreach ( self::catalog() as $row ) {
			$signal = self::signal_for( $row['id'], $scanned, $stats, $env );
			$rows[] = array(
				'id'     => $row['id'],
				'group'  => $row['group'],
				'label'  => $row['label'],
				'level'  => $signal['level'],
				'detail' => $signal['detail'],
			);
		}
		return $rows;
	}

	/**
	 * @param array<int,array{level:string}> $signals Signals.
	 * @return array{green:int,total:int,yellow:int,red:int}
	 */
	public static function tally( $signals ) {
		$tally = array(
			'green'  => 0,
			'yellow' => 0,
			'red'    => 0,
			'total'  => 0,
		);
		foreach ( $signals as $signal ) {
			$level = isset( $signal['level'] ) ? $signal['level'] : 'red';
			if ( ! isset( $tally[ $level ] ) ) {
				$level = 'red';
			}
			$tally[ $level ]++;
			$tally['total']++;
		}
		return $tally;
	}

	/**
	 * @return array<int,array{id:string,group:string,label:string}>
	 */
	public static function catalog() {
		return array(
			array( 'id' => 'safety_publish', 'group' => 'Safety', 'label' => 'No auto-publish' ),
			array( 'id' => 'safety_status', 'group' => 'Safety', 'label' => 'Post status lock' ),
			array( 'id' => 'safety_amazon', 'group' => 'Safety', 'label' => 'Amazon URL lock' ),
			array( 'id' => 'safety_experience', 'group' => 'Safety', 'label' => 'No invented experience' ),
			array( 'id' => 'approval_gate', 'group' => 'Safety', 'label' => 'Approval gate' ),
			array( 'id' => 'thin_detector', 'group' => 'Authority', 'label' => 'Thin content detector' ),
			array( 'id' => 'duplicate_detector', 'group' => 'Authority', 'label' => 'Duplicate phrase detector' ),
			array( 'id' => 'article_score', 'group' => 'Authority', 'label' => 'Article score' ),
			array( 'id' => 'internal_links', 'group' => 'Authority', 'label' => 'Internal link gaps' ),
			array( 'id' => 'verdict', 'group' => 'Content', 'label' => "Rick's Verdict" ),
			array( 'id' => 'pros_cons', 'group' => 'Content', 'label' => 'Pros and cons' ),
			array( 'id' => 'comparison', 'group' => 'Content', 'label' => 'Comparison' ),
			array( 'id' => 'faq', 'group' => 'Content', 'label' => 'FAQ' ),
			array( 'id' => 'buyer_type', 'group' => 'Content', 'label' => 'Buyer types' ),
			array( 'id' => 'rating_table', 'group' => 'Content', 'label' => 'Scorecard' ),
			array( 'id' => 'affiliate_links', 'group' => 'Revenue', 'label' => 'Affiliate link shape' ),
			array( 'id' => 'affiliate_tag', 'group' => 'Revenue', 'label' => 'Affiliate tag check' ),
			array( 'id' => 'missing_button', 'group' => 'Revenue', 'label' => 'Amazon button' ),
			array( 'id' => 'opportunity_score', 'group' => 'Revenue', 'label' => 'Revenue opportunity' ),
			array( 'id' => 'meta_title', 'group' => 'SEO', 'label' => 'Rank Math titles' ),
			array( 'id' => 'meta_description', 'group' => 'SEO', 'label' => 'Rank Math descriptions' ),
			array( 'id' => 'h1', 'group' => 'SEO', 'label' => 'H1 structure' ),
			array( 'id' => 'schema_owner', 'group' => 'SEO', 'label' => 'Schema owner' ),
			array( 'id' => 'cannibalization', 'group' => 'SEO', 'label' => 'Cannibalization' ),
			array( 'id' => 'weight_pages', 'group' => 'Index', 'label' => 'Weight and spec archives' ),
			array( 'id' => 'tag_archives', 'group' => 'Index', 'label' => 'Product-tag archives' ),
			array( 'id' => 'empty_archives', 'group' => 'Index', 'label' => 'Empty archives' ),
			array( 'id' => 'thin_categories', 'group' => 'Index', 'label' => 'Thin categories' ),
			array( 'id' => 'noindex_gate', 'group' => 'Index', 'label' => 'Noindex approval gate' ),
			array( 'id' => 'litespeed', 'group' => 'Speed', 'label' => 'LiteSpeed' ),
			array( 'id' => 'image_issues', 'group' => 'Speed', 'label' => 'Image dimensions and alt' ),
			array( 'id' => 'cache_headers', 'group' => 'Speed', 'label' => 'Cache headers' ),
			array( 'id' => 'web_vitals_lock', 'group' => 'Speed', 'label' => 'No second optimizer' ),
			array( 'id' => 'overlap_report', 'group' => 'Stack', 'label' => 'Overlapping plugins' ),
		);
	}

	/**
	 * @param string                      $id ID.
	 * @param int                         $scanned Scanned count.
	 * @param array<string,int|float|null> $stats Stats.
	 * @param array<string,mixed>         $env Env.
	 * @return array{level:string,detail:string}
	 */
	private static function signal_for( $id, $scanned, $stats, $env ) {
		if ( 'safety_publish' === $id ) {
			return array( 'level' => 'green', 'detail' => 'Publishing is locked. Drafts stay in the editor until you approve them.' );
		}
		if ( 'safety_status' === $id ) {
			return array( 'level' => 'green', 'detail' => 'This plugin never changes a post status.' );
		}
		if ( 'safety_amazon' === $id ) {
			return array( 'level' => 'green', 'detail' => 'Amazon URLs are read for checks and are never rewritten.' );
		}
		if ( 'safety_experience' === $id ) {
			return array( 'level' => 'green', 'detail' => 'Scaffolds ask you to write the opinion. They do not invent a hands-on test.' );
		}
		if ( 'approval_gate' === $id ) {
			return array( 'level' => 'green', 'detail' => 'A block reaches the public guide only after you approve it.' );
		}
		if ( 'schema_owner' === $id ) {
			if ( ! empty( $env['rank_math'] ) ) {
				return array( 'level' => 'green', 'detail' => 'Rank Math owns JSON-LD. This plugin does not disable it and does not add a second schema.' );
			}
			return array( 'level' => 'yellow', 'detail' => 'Rank Math was not detected, so schema is not confirmed.' );
		}
		if ( 'noindex_gate' === $id ) {
			$approved = isset( $env['approved_noindex'] ) ? (int) $env['approved_noindex'] : 0;
			return array( 'level' => 'green', 'detail' => 'Noindex is off until you approve a path. Approved paths: ' . $approved . '.' );
		}
		if ( 'web_vitals_lock' === $id ) {
			return array( 'level' => 'green', 'detail' => 'This plugin does not inject a performance script. LiteSpeed remains the optimizer. LCP is measured in Site Kit or PageSpeed, not here.' );
		}
		if ( 'litespeed' === $id ) {
			if ( ! empty( $env['litespeed'] ) ) {
				return array( 'level' => 'green', 'detail' => 'LiteSpeed Cache is active. This plugin does not replace it.' );
			}
			return array( 'level' => 'yellow', 'detail' => 'LiteSpeed Cache was not detected.' );
		}
		if ( 'overlap_report' === $id ) {
			$count = isset( $env['overlapping'] ) && is_array( $env['overlapping'] ) ? count( $env['overlapping'] ) : 0;
			if ( $count > 0 ) {
				return array( 'level' => 'yellow', 'detail' => $count . ' overlapping plugins are still active. Retire them only after a Snapshot backup.' );
			}
			return array( 'level' => 'green', 'detail' => 'None of the overlapping plugins are active.' );
		}
		if ( 'cache_headers' === $id ) {
			return LUAE_Speed::cache(
				isset( $env['cache_control'] ) ? (string) $env['cache_control'] : '',
				! empty( $env['litespeed_hit'] )
			);
		}
		if ( 'image_issues' === $id ) {
			return LUAE_Speed::images( isset( $env['html_sample'] ) ? (string) $env['html_sample'] : '' );
		}
		if ( $scanned < 1 && self::needs_scan( $id ) ) {
			return array( 'level' => 'yellow', 'detail' => 'Waiting for a scan. Nothing has been scored yet.' );
		}
		return self::finding( $id, $stats, $env );
	}

	/**
	 * @param string $id ID.
	 * @return bool
	 */
	private static function needs_scan( $id ) {
		$needs = array(
			'thin_detector',
			'duplicate_detector',
			'article_score',
			'internal_links',
			'verdict',
			'pros_cons',
			'comparison',
			'faq',
			'buyer_type',
			'rating_table',
			'affiliate_links',
			'affiliate_tag',
			'missing_button',
			'opportunity_score',
			'meta_title',
			'meta_description',
			'h1',
			'cannibalization',
			'weight_pages',
			'tag_archives',
			'empty_archives',
			'thin_categories',
		);
		return in_array( $id, $needs, true );
	}

	/**
	 * @param string                       $id ID.
	 * @param array<string,int|float|null> $stats Stats.
	 * @param array<string,mixed>          $env Env.
	 * @return array{level:string,detail:string}
	 */
	private static function finding( $id, $stats, $env ) {
		$map = array(
			'thin_detector'      => array( 'thin', 'guides are thin or still use the template.' ),
			'duplicate_detector' => array( 'duplicate', 'guides repeat the same sentences or the title.' ),
			'internal_links'     => array( 'link_gaps', 'guides are missing a category, guide, or comparison link.' ),
			'verdict'            => array( 'missing_verdict', 'guides have no approved verdict.' ),
			'pros_cons'          => array( 'missing_pros', 'guides have no approved pros and cons.' ),
			'comparison'         => array( 'missing_compare', 'guides have no approved comparison.' ),
			'faq'                => array( 'missing_faq', 'guides have no FAQ.' ),
			'buyer_type'         => array( 'missing_buyer', 'guides have no approved buyer types.' ),
			'rating_table'       => array( 'missing_score', 'guides have no approved scorecard.' ),
			'affiliate_links'    => array( 'bad_amazon', 'Amazon links are missing or have no ASIN. None were rewritten.' ),
			'affiliate_tag'      => array( 'tag_mismatch', 'Amazon tags do not match the tag in settings. None were rewritten.' ),
			'missing_button'     => array( 'missing_button', 'guides have no Amazon button. Use Amazon Bridge to add one.' ),
			'opportunity_score'  => array( 'high_opportunity', 'guides have a high content-gap score. This is not a dollar forecast.' ),
			'meta_title'         => array( 'meta_title_missing', 'guides have an empty Rank Math title. Nothing was written to Rank Math.' ),
			'meta_description'   => array( 'meta_description_missing', 'guides have an empty Rank Math description. Nothing was written to Rank Math.' ),
			'h1'                 => array( 'h1_issues', 'guides have more than one H1 in the post body.' ),
			'cannibalization'    => array( 'cannibalization', 'guides target the same product as another guide.' ),
			'weight_pages'       => array( 'weight_paths', 'spec archives should be noindexed after you approve them.' ),
			'tag_archives'       => array( 'tag_paths', 'product-tag archives should be noindexed after you approve them.' ),
			'empty_archives'     => array( 'empty_archives', 'archives have no posts. Noindex is the recommendation.' ),
			'thin_categories'    => array( 'thin_categories', 'categories have fewer than 2 products. Improve them. Do not noindex a real shopping category.' ),
		);
		if ( 'article_score' === $id ) {
			$avg = isset( $stats['avg_score'] ) ? (int) $stats['avg_score'] : 0;
			if ( $avg >= 80 ) {
				return array( 'level' => 'green', 'detail' => 'Average article score is ' . $avg . ' / 100.' );
			}
			return array( 'level' => 'yellow', 'detail' => 'Average article score is ' . $avg . ' / 100 across ' . (int) $stats['scanned'] . ' scanned guides.' );
		}
		if ( ! isset( $map[ $id ] ) ) {
			return array( 'level' => 'green', 'detail' => 'Check completed.' );
		}
		$key   = $map[ $id ][0];
		$count = isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0;
		if ( $count > 0 ) {
			return array( 'level' => 'yellow', 'detail' => $count . ' ' . $map[ $id ][1] );
		}
		return array( 'level' => 'green', 'detail' => 'Clear on the scanned set.' );
	}
}
