<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rolls post reports into the signal board.
 */
class LUAE_Stats {

	/**
	 * @return array<string,int>
	 */
	public static function blank() {
		return array(
			'scanned'                  => 0,
			'thin'                     => 0,
			'duplicate'                => 0,
			'link_gaps'                => 0,
			'missing_verdict'          => 0,
			'missing_pros'             => 0,
			'missing_compare'          => 0,
			'missing_faq'              => 0,
			'missing_buyer'            => 0,
			'missing_score'            => 0,
			'bad_amazon'               => 0,
			'tag_mismatch'             => 0,
			'missing_button'           => 0,
			'high_opportunity'         => 0,
			'meta_title_missing'       => 0,
			'meta_description_missing' => 0,
			'h1_issues'                => 0,
			'cannibalization'          => 0,
			'weight_paths'             => 0,
			'tag_paths'                => 0,
			'empty_archives'           => 0,
			'thin_categories'          => 0,
			'score_total'              => 0,
			'avg_score'                => 0,
		);
	}

	/**
	 * @param array<string,int>   $stats  Stats.
	 * @param array<string,mixed> $report Report.
	 * @return array<string,int>
	 */
	public static function add( $stats, $report ) {
		$stats['scanned']++;
		$stats['score_total'] += isset( $report['score'] ) ? (int) $report['score'] : 0;
		if ( ! empty( $report['thin'] ) ) {
			$stats['thin']++;
		}
		if ( ! empty( $report['duplicate'] ) ) {
			$stats['duplicate']++;
		}
		$kinds = isset( $report['link_kinds'] ) && is_array( $report['link_kinds'] ) ? $report['link_kinds'] : array();
		if ( empty( $kinds['category'] ) || empty( $kinds['guide'] ) || empty( $kinds['comparison'] ) ) {
			$stats['link_gaps']++;
		}
		self::count_problem( $stats, $report, 'No approved verdict.', 'missing_verdict' );
		self::count_problem( $stats, $report, 'No approved pros and cons.', 'missing_pros' );
		self::count_problem( $stats, $report, 'No approved comparison.', 'missing_compare' );
		self::count_problem( $stats, $report, 'No FAQ.', 'missing_faq' );
		self::count_problem( $stats, $report, 'No approved buyer types.', 'missing_buyer' );
		self::count_problem( $stats, $report, 'No approved scorecard.', 'missing_score' );
		if ( isset( $report['link_state'] ) && 'ok' !== $report['link_state'] && 'missing' !== $report['link_state'] ) {
			$stats['bad_amazon']++;
		}
		if ( isset( $report['link_state'] ) && 'missing' === $report['link_state'] ) {
			$stats['missing_button']++;
		}
		if ( isset( $report['tag_state'] ) && 'mismatch' === $report['tag_state'] ) {
			$stats['tag_mismatch']++;
		}
		if ( isset( $report['opportunity'] ) && 'HIGH' === $report['opportunity'] ) {
			$stats['high_opportunity']++;
		}
		if ( empty( $report['meta_title'] ) ) {
			$stats['meta_title_missing']++;
		}
		if ( empty( $report['meta_desc'] ) ) {
			$stats['meta_description_missing']++;
		}
		if ( isset( $report['h1'] ) && (int) $report['h1'] > 1 ) {
			$stats['h1_issues']++;
		}
		if ( ! empty( $report['cannibals'] ) ) {
			$stats['cannibalization']++;
		}
		$stats['avg_score'] = $stats['scanned'] > 0 ? (int) round( $stats['score_total'] / $stats['scanned'] ) : 0;
		return $stats;
	}

	/**
	 * @param array<string,int>   $stats Stats.
	 * @param array<string,mixed> $report Report.
	 * @param string              $needle Needle.
	 * @param string              $key Key.
	 */
	private static function count_problem( &$stats, $report, $needle, $key ) {
		$problems = isset( $report['problems'] ) && is_array( $report['problems'] ) ? $report['problems'] : array();
		foreach ( $problems as $problem ) {
			if ( $problem === $needle ) {
				$stats[ $key ]++;
				return;
			}
		}
	}
}
