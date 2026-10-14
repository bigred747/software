<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only assessment of one buyer guide.
 */
class LUAE_Post {

	/**
	 * @return array<string,mixed>
	 */
	public static function empty_modules() {
		return array(
			'verdict'  => '',
			'pros'     => '',
			'cons'     => '',
			'buy'      => '',
			'skip'     => '',
			'faq'      => '',
			'scores'   => array(
				'design'      => '',
				'performance' => '',
				'battery'     => '',
				'value'       => '',
				'overall'     => '',
			),
			'compare'  => array(
				array( 'title' => '', 'url' => '' ),
				array( 'title' => '', 'url' => '' ),
				array( 'title' => '', 'url' => '' ),
			),
			'approved' => array(
				'verdict'   => 0,
				'pros_cons' => 0,
				'buyers'    => 0,
				'scorecard' => 0,
				'compare'   => 0,
				'faq'       => 0,
			),
		);
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public static function assess( $input ) {
		$html    = isset( $input['content'] ) ? (string) $input['content'] : '';
		$title   = isset( $input['title'] ) ? (string) $input['title'] : '';
		$home    = isset( $input['home'] ) ? (string) $input['home'] : '';
		$modules = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : self::empty_modules();
		$approved = isset( $modules['approved'] ) && is_array( $modules['approved'] ) ? $modules['approved'] : array();
		$words   = LUAE_Text::words( $html );
		$phrases = LUAE_Text::template_hits( $html );
		$dupes   = LUAE_Text::duplicate_sentences( $html );
		$repeats = LUAE_Text::title_repeats( $html, $title );
		$urls    = LUAE_Text::amazon_urls( $html );
		$ratio   = LUAE_Text::amazon_ratio( $html );
		$kinds   = LUAE_Text::link_kinds( $html, $home );
		$tag     = isset( $input['expected_tag'] ) ? trim( (string) $input['expected_tag'] ) : '';
		$siblings = isset( $input['siblings'] ) && is_array( $input['siblings'] ) ? $input['siblings'] : array();

		$problems = array();
		$thin     = $words < 1000 || count( $phrases ) >= 2 || $repeats >= 6;
		if ( $words < 1000 ) {
			$problems[] = 'Under 1,000 words (' . $words . ').';
		}
		if ( count( $phrases ) >= 2 ) {
			$problems[] = 'Repeated template blocks are still in the article.';
		}
		if ( $dupes || $repeats >= 6 ) {
			$problems[] = 'Duplicate phrases repeat the same point.';
		}
		if ( empty( $approved['verdict'] ) ) {
			$problems[] = 'No approved verdict.';
		}
		if ( empty( $approved['pros_cons'] ) ) {
			$problems[] = 'No approved pros and cons.';
		}
		if ( empty( $approved['compare'] ) ) {
			$problems[] = 'No approved comparison.';
		}
		if ( empty( $approved['faq'] ) && ! LUAE_Text::has_faq( $html ) ) {
			$problems[] = 'No FAQ.';
		}
		if ( empty( $approved['buyers'] ) ) {
			$problems[] = 'No approved buyer types.';
		}
		if ( empty( $approved['scorecard'] ) ) {
			$problems[] = 'No approved scorecard.';
		}
		if ( ! $kinds['category'] || ! $kinds['guide'] || ! $kinds['comparison'] ) {
			$problems[] = 'Missing an internal link a shopper can follow.';
		}

		$tag_state = self::tag_state( $urls, $tag );
		$link_state = self::link_state( $html, $urls );
		if ( 'missing' === $link_state ) {
			$problems[] = 'No Amazon button. The Amazon Bridge plugin owns that button. This plugin will not create a URL.';
		} elseif ( 'broken' === $link_state ) {
			$problems[] = 'An Amazon link is missing an ASIN. The URL was not changed.';
		}
		if ( null !== $ratio && $ratio > 0.6 ) {
			$problems[] = 'Amazon button is below the fold of the article.';
		}
		if ( 'mismatch' === $tag_state ) {
			$problems[] = 'Amazon tag does not match the tag in settings. The URL was not changed.';
		}

		$meta_title = trim( (string) ( isset( $input['rank_math_title'] ) ? $input['rank_math_title'] : '' ) );
		$meta_desc  = trim( (string) ( isset( $input['rank_math_description'] ) ? $input['rank_math_description'] : '' ) );
		if ( '' === $meta_title ) {
			$problems[] = 'Rank Math title is empty.';
		}
		if ( '' === $meta_desc ) {
			$problems[] = 'Rank Math description is empty.';
		}
		$h1 = LUAE_Text::h1_count( $html );
		if ( $h1 > 1 ) {
			$problems[] = 'More than one H1 in the post body.';
		}
		$cannibals = self::cannibalization( $title, $siblings );
		if ( $cannibals ) {
			$problems[] = 'Another guide targets the same product.';
		}

		$score = self::score( $words, $phrases, $approved, $html, $meta_title, $meta_desc, $link_state, $ratio, $tag_state, $kinds );
		$opportunity = self::opportunity( $problems );

		return array(
			'score'         => $score,
			'opportunity'   => $opportunity,
			'problems'      => $problems,
			'words'         => $words,
			'thin'          => $thin,
			'duplicate'     => (bool) ( $dupes || $repeats >= 6 ),
			'phrases'       => $phrases,
			'amazon_urls'   => $urls,
			'amazon_ratio'  => $ratio,
			'link_state'    => $link_state,
			'tag_state'     => $tag_state,
			'link_kinds'    => $kinds,
			'h1'            => $h1,
			'meta_title'    => '' !== $meta_title,
			'meta_desc'     => '' !== $meta_desc,
			'cannibals'     => $cannibals,
			'faq'           => LUAE_Text::has_faq( $html ) || ! empty( $approved['faq'] ),
			'views'         => isset( $input['views'] ) && is_numeric( $input['views'] ) ? (int) $input['views'] : null,
			'clicks'        => isset( $input['clicks'] ) && is_numeric( $input['clicks'] ) ? (int) $input['clicks'] : null,
			'siblings'      => self::sibling_suggestions( $siblings, $home ),
			'prompts'       => self::prompts( $input ),
		);
	}

	/**
	 * @param string[] $urls URLs.
	 * @param string   $expected Expected tag.
	 * @return string ok|missing|mismatch|unset
	 */
	public static function tag_state( $urls, $expected ) {
		if ( ! $urls ) {
			return 'missing';
		}
		if ( '' === $expected ) {
			return 'unset';
		}
		foreach ( $urls as $url ) {
			$tag = LUAE_Text::amazon_tag( $url );
			if ( '' === $tag || strtolower( $tag ) !== strtolower( $expected ) ) {
				return 'mismatch';
			}
		}
		return 'ok';
	}

	/**
	 * @param string   $html HTML.
	 * @param string[] $urls URLs.
	 * @return string ok|missing|broken
	 */
	public static function link_state( $html, $urls ) {
		if ( ! $urls && null === LUAE_Text::amazon_ratio( $html ) ) {
			return 'missing';
		}
		if ( ! $urls ) {
			return 'broken';
		}
		foreach ( $urls as $url ) {
			if ( '' === LUAE_Text::asin( $url ) && false === stripos( $url, 'amzn.to' ) ) {
				return 'broken';
			}
		}
		return 'ok';
	}

	/**
	 * @param string               $title Title.
	 * @param array<int,mixed>     $siblings Siblings.
	 * @return string[]
	 */
	public static function cannibalization( $title, $siblings ) {
		$stem = self::stem( $title );
		if ( '' === $stem ) {
			return array();
		}
		$hits = array();
		foreach ( $siblings as $sibling ) {
			$name = is_array( $sibling ) && isset( $sibling['title'] ) ? (string) $sibling['title'] : '';
			if ( self::stem( $name ) === $stem ) {
				$hits[] = $name;
			}
		}
		return $hits;
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	public static function stem( $title ) {
		$title = strtolower( LUAE_Text::plain( $title ) );
		$title = preg_replace( '/\b(review|buyer guide|2024|2025|2026|built for ai|the)\b/', ' ', $title );
		$title = preg_replace( '/[^a-z0-9]+/', ' ', (string) $title );
		$title = trim( preg_replace( '/\s+/', ' ', (string) $title ) );
		$parts = explode( ' ', $title );
		$parts = array_slice( $parts, 0, 6 );
		$stem  = trim( implode( ' ', $parts ) );
		return strlen( $stem ) >= 12 ? $stem : '';
	}

	/**
	 * @param array<int,mixed> $siblings Siblings.
	 * @param string           $home Home.
	 * @return array<int,array{title:string,url:string}>
	 */
	public static function sibling_suggestions( $siblings, $home ) {
		$out = array();
		foreach ( $siblings as $sibling ) {
			if ( ! is_array( $sibling ) ) {
				continue;
			}
			$title = isset( $sibling['title'] ) ? trim( LUAE_Text::plain( $sibling['title'] ) ) : '';
			$url   = isset( $sibling['url'] ) ? trim( (string) $sibling['url'] ) : '';
			if ( strlen( $title ) < 3 || ! LUAE_Text::is_local_url( $url, $home ) ) {
				continue;
			}
			$out[] = array(
				'title' => $title,
				'url'   => $url,
			);
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $input Input.
	 * @return array<string,string>
	 */
	public static function prompts( $input ) {
		$title = trim( LUAE_Text::plain( isset( $input['title'] ) ? $input['title'] : '' ) );
		$cats  = array();
		if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
			foreach ( $input['categories'] as $cat ) {
				$cat = trim( LUAE_Text::plain( $cat ) );
				if ( '' !== $cat ) {
					$cats[] = $cat;
				}
			}
		}
		$where = $cats ? implode( ', ', $cats ) : 'this category';
		return array(
			'verdict'   => 'Write 2 to 4 sentences on ' . $title . '. Give one reason you would buy it and one limit. Do not invent a test you did not do.',
			'pros'      => 'One true advantage per line. Leave a line blank if you do not know it.',
			'cons'      => 'One true limit per line.',
			'buyers'    => 'Who is this for in ' . $where . '? One buyer per line.',
			'skip'      => 'Who should skip it? One type per line.',
			'scores'    => 'Enter 0 to 10 for each row after you have judged it. Blank is allowed until then.',
			'compare'   => 'Use real guides already on this site. Do not invent a competing model.',
			'faq'       => 'One question and answer a shopper would actually ask. Start lines with Q: and A:.',
		);
	}

	/**
	 * @param int                  $words Words.
	 * @param string[]             $phrases Phrases.
	 * @param array<string,mixed>  $approved Approved flags.
	 * @param string               $html HTML.
	 * @param string               $meta_title Title.
	 * @param string               $meta_desc Description.
	 * @param string               $link_state Link state.
	 * @param float|null           $ratio Ratio.
	 * @param string               $tag_state Tag state.
	 * @param array<string,bool>   $kinds Link kinds.
	 * @return int
	 */
	private static function score( $words, $phrases, $approved, $html, $meta_title, $meta_desc, $link_state, $ratio, $tag_state, $kinds ) {
		$score = 0;
		if ( $words >= 1000 ) {
			$score += 10;
		} elseif ( $words > 0 ) {
			$score += (int) floor( 10 * ( $words / 1000 ) );
		}
		if ( count( $phrases ) < 2 ) {
			$score += 10;
		}
		if ( ! empty( $approved['verdict'] ) ) {
			$score += 15;
		}
		if ( ! empty( $approved['pros_cons'] ) ) {
			$score += 10;
		}
		if ( ! empty( $approved['buyers'] ) ) {
			$score += 10;
		}
		if ( ! empty( $approved['scorecard'] ) ) {
			$score += 10;
		}
		if ( ! empty( $approved['compare'] ) ) {
			$score += 10;
		}
		if ( ! empty( $approved['faq'] ) || LUAE_Text::has_faq( $html ) ) {
			$score += 5;
		}
		if ( '' !== $meta_title ) {
			$score += 5;
		}
		if ( '' !== $meta_desc ) {
			$score += 5;
		}
		if ( $kinds['category'] && $kinds['guide'] && $kinds['comparison'] ) {
			$score += 5;
		}
		if ( 'ok' === $link_state ) {
			$score += 3;
		}
		if ( null !== $ratio && $ratio <= 0.6 ) {
			$score += 2;
		}
		if ( 'ok' === $tag_state ) {
			$score += 5;
		}
		if ( $score > 100 ) {
			return 100;
		}
		return $score;
	}

	/**
	 * @param string[] $problems Problems.
	 * @return string
	 */
	private static function opportunity( $problems ) {
		$count = count( $problems );
		if ( $count >= 4 ) {
			return 'HIGH';
		}
		if ( $count >= 2 ) {
			return 'MEDIUM';
		}
		return 'LOW';
	}

	/**
	 * Green or yellow for this guide. Red is reserved for a broken check.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return array<int,array{label:string,level:string,detail:string}>
	 */
	public static function signals( $report ) {
		if ( ! is_array( $report ) || ! isset( $report['score'] ) ) {
			return array();
		}
		$kinds = isset( $report['link_kinds'] ) && is_array( $report['link_kinds'] ) ? $report['link_kinds'] : array();
		$rows  = array(
			array( 'Article score', (int) $report['score'] >= 80 ? 'green' : 'yellow', (int) $report['score'] . ' / 100' ),
			array( 'Thin content', empty( $report['thin'] ) ? 'green' : 'yellow', empty( $report['thin'] ) ? 'Long enough and not stuck on the template.' : 'Still thin or still using the template.' ),
			array( 'Duplicate phrases', empty( $report['duplicate'] ) ? 'green' : 'yellow', empty( $report['duplicate'] ) ? 'No repeated sentences found.' : 'The same sentence or title is repeated.' ),
			array( 'Internal links', ( ! empty( $kinds['category'] ) && ! empty( $kinds['guide'] ) && ! empty( $kinds['comparison'] ) ) ? 'green' : 'yellow', 'Category, guide, and comparison links.' ),
			array( 'Amazon link', ( isset( $report['link_state'] ) && 'ok' === $report['link_state'] ) ? 'green' : 'yellow', isset( $report['link_state'] ) ? (string) $report['link_state'] : 'missing' ),
			array( 'Amazon tag', ( isset( $report['tag_state'] ) && 'ok' === $report['tag_state'] ) ? 'green' : 'yellow', isset( $report['tag_state'] ) ? (string) $report['tag_state'] : 'unset' ),
			array( 'Rank Math title', ! empty( $report['meta_title'] ) ? 'green' : 'yellow', ! empty( $report['meta_title'] ) ? 'Title is set.' : 'Title is empty. Not written.' ),
			array( 'Rank Math description', ! empty( $report['meta_desc'] ) ? 'green' : 'yellow', ! empty( $report['meta_desc'] ) ? 'Description is set.' : 'Description is empty. Not written.' ),
			array( 'H1', ( isset( $report['h1'] ) && (int) $report['h1'] <= 1 ) ? 'green' : 'yellow', 'H1 count in the post body: ' . ( isset( $report['h1'] ) ? (int) $report['h1'] : 0 ) ),
			array( 'Cannibalization', empty( $report['cannibals'] ) ? 'green' : 'yellow', empty( $report['cannibals'] ) ? 'No sibling uses the same product stem.' : 'Another guide targets this product.' ),
			array( 'FAQ', ! empty( $report['faq'] ) ? 'green' : 'yellow', ! empty( $report['faq'] ) ? 'FAQ is present.' : 'FAQ is missing.' ),
		);
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'label'  => $row[0],
				'level'  => $row[1],
				'detail' => $row[2],
			);
		}
		return $out;
	}
}
