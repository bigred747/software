<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public HTML for approved blocks only. Appended. Never a replacement.
 */
class LUAE_Render {

	/**
	 * @param array<string,mixed> $modules Modules.
	 * @param string              $home    Home URL.
	 * @return string
	 */
	public static function section( $modules, $home ) {
		if ( ! is_array( $modules ) ) {
			return '';
		}
		$approved = isset( $modules['approved'] ) && is_array( $modules['approved'] ) ? $modules['approved'] : array();
		$html     = '';
		if ( ! empty( $approved['verdict'] ) && array() === LUAE_Approve::verdict( isset( $modules['verdict'] ) ? $modules['verdict'] : '' ) ) {
			$html .= self::verdict_html( $modules['verdict'] );
		}
		if ( ! empty( $approved['pros_cons'] ) ) {
			$pros = LUAE_Approve::clean_lines( isset( $modules['pros'] ) ? $modules['pros'] : '' );
			$cons = LUAE_Approve::clean_lines( isset( $modules['cons'] ) ? $modules['cons'] : '' );
			if ( array() === LUAE_Approve::lines( isset( $modules['pros'] ) ? $modules['pros'] : '', 2, 'Pros' ) && array() === LUAE_Approve::lines( isset( $modules['cons'] ) ? $modules['cons'] : '', 1, 'Cons' ) ) {
				$html .= '<h2>Pros</h2><ul>' . self::items( $pros, '' ) . '</ul>';
				$html .= '<h2>Cons</h2><ul>' . self::items( $cons, '' ) . '</ul>';
			}
		}
		if ( ! empty( $approved['buyers'] ) ) {
			$buy  = LUAE_Approve::clean_lines( isset( $modules['buy'] ) ? $modules['buy'] : '' );
			$skip = LUAE_Approve::clean_lines( isset( $modules['skip'] ) ? $modules['skip'] : '' );
			if ( $buy && $skip ) {
				$html .= '<h2>Who Should Buy This?</h2><ul>' . self::items( $buy, '✓ ' ) . '</ul>';
				$html .= '<h2>Who Should Skip It?</h2><ul>' . self::items( $skip, '✗ ' ) . '</ul>';
			}
		}
		if ( ! empty( $approved['scorecard'] ) && array() === LUAE_Approve::scorecard( isset( $modules['scores'] ) ? $modules['scores'] : array() ) ) {
			$html .= self::scorecard_html( $modules['scores'] );
		}
		if ( ! empty( $approved['compare'] ) && array() === LUAE_Approve::compare( isset( $modules['compare'] ) ? $modules['compare'] : array(), $home ) ) {
			$html .= self::compare_html( $modules['compare'], $home );
		}
		if ( ! empty( $approved['faq'] ) && array() === LUAE_Approve::faq( isset( $modules['faq'] ) ? $modules['faq'] : '' ) ) {
			$html .= '<h2>FAQ</h2>' . self::faq_html( $modules['faq'] );
		}
		if ( '' === $html ) {
			return '';
		}
		return "\n" . '<section class="luae-authority" data-luae="1"><!-- luxe-ultimate-authority -->' . $html . '</section>' . "\n";
	}

	/**
	 * @param string $text Verdict.
	 * @return string
	 */
	public static function verdict_html( $text ) {
		return '<h2>Rick\'s Verdict</h2><p>' . LUAE_Text::esc( LUAE_Text::plain( $text ) ) . '</p>';
	}

	/**
	 * @param string[] $rows   Rows.
	 * @param string   $prefix Prefix.
	 * @return string
	 */
	private static function items( $rows, $prefix ) {
		$html = '';
		foreach ( $rows as $row ) {
			$html .= '<li>' . LUAE_Text::esc( $prefix . $row ) . '</li>';
		}
		return $html;
	}

	/**
	 * @param array<string,string> $scores Scores.
	 * @return string
	 */
	public static function scorecard_html( $scores ) {
		$labels = array(
			'design'      => 'Design',
			'performance' => 'Performance',
			'battery'     => 'Battery',
			'value'       => 'Value',
			'overall'     => 'Overall',
		);
		$html = '<h2>Rick\'s Scorecard</h2><ul class="luae-scorecard">';
		foreach ( $labels as $key => $label ) {
			$value = isset( $scores[ $key ] ) ? $scores[ $key ] : '';
			$html .= '<li><span>' . LUAE_Text::esc( $label ) . '</span><span class="luae-dots"></span><span>' . LUAE_Text::esc( $value ) . '</span></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param array<int,array{title:string,url:string}> $items Items.
	 * @param string                                    $home  Home.
	 * @return string
	 */
	public static function compare_html( $items, $home ) {
		$html = '<h2>Compare This Product To</h2><ul>';
		$used = 0;
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? trim( LUAE_Text::plain( $item['title'] ) ) : '';
			$url   = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
			if ( '' === $title || ! LUAE_Text::is_local_url( $url, $home ) ) {
				continue;
			}
			$html .= '<li><a href="' . LUAE_Text::esc( $url ) . '">' . LUAE_Text::esc( $title ) . '</a></li>';
			$used++;
			if ( $used >= 3 ) {
				break;
			}
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param string $text FAQ text.
	 * @return string
	 */
	public static function faq_html( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', trim( (string) $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$html = '';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^(q|question)\s*:\s*(.+)$/i', $line, $match ) ) {
				$html .= '<h3>' . LUAE_Text::esc( $match[2] ) . '</h3>';
				continue;
			}
			if ( preg_match( '/^(a|answer)\s*:\s*(.+)$/i', $line, $match ) ) {
				$html .= '<p>' . LUAE_Text::esc( $match[2] ) . '</p>';
				continue;
			}
			$html .= '<p>' . LUAE_Text::esc( LUAE_Text::plain( $line ) ) . '</p>';
		}
		return $html;
	}
}
