<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'LAH_Guide' ) ) {
	return;
}

/**
 * Starter-guide copy. Every outbound URL is an official Amazon page.
 */
class LAH_Guide {

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function steps() {
		return array(
			array(
				'id'     => 'payment',
				'label'  => 'Step 1',
				'title'  => 'Add your payment and tax info',
				'points' => array(
					'Sign in yourself at Associates Central. This plugin never sees that login.',
					'Open the menu on your email, then Account Settings, then Payment and Tax Information.',
					'Add a payment method there. Amazon pays direct deposit or a gift certificate from $10, and a check from $100.',
					'Take the tax interview on Amazon. Amazon validates it before they can pay commission.',
					'Commission is paid about 60 days after the month it was earned.',
					'When you are done on Amazon, check the box on this screen. Do not paste a tax ID, bank number, or password here.',
				),
				'links'  => array(
					array(
						'label' => 'Open Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
					array(
						'label' => 'Payment methods',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/GKDG94FQSRXSJCGK',
					),
					array(
						'label' => 'When you get paid',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/G63DR893K4DH55XZ',
					),
					array(
						'label' => 'Tax interview help',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/GYJB2LE2AB473W2L',
					),
				),
			),
			array(
				'id'     => 'share',
				'label'  => 'Step 2',
				'title'  => 'Share one tagged link',
				'points' => array(
					'On your phone, Amazon’s Mobile GetLink creates a link while you are signed in to the Amazon app.',
					'On a desktop, SiteStripe’s Get Link does the same thing on an Amazon product page.',
					'Turn SiteStripe on under Account Settings, then Associates SiteStripe Options.',
					'Or build a standard product link below from a Product Scout ASIN and your tracking ID.',
					'Put the associate disclosure on the page where you share the link.',
					'Creators who share at least one new link in the first week are associated with higher annual sales. Amazon’s note is observational. It is not a promise.',
				),
				'links'  => array(
					array(
						'label' => 'What SiteStripe does',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/GJMMT7G4C8K4Y3AY',
					),
				),
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function sections() {
		return array(
			array(
				'id'     => 'earnings',
				'label'  => 'Earnings',
				'title'  => 'Earnings stay in your Associates login',
				'points' => array(
					'The earnings report is private. This plugin does not connect to it and cannot read your balance.',
					'Open Associates Central while you are signed in to see clicks, shipped items, and payment history.',
					'A short reminder can be saved here. Account numbers and tax IDs are rejected.',
				),
				'links'  => array(
					array(
						'label' => 'Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
					array(
						'label' => 'Payment history help',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/G63DR893K4DH55XZ',
					),
				),
			),
			array(
				'id'     => 'policies',
				'label'  => 'Policies',
				'title'  => 'Policies to follow before you share',
				'points' => array(
					'Use a tagged Amazon link. A normal amazon.com link does not credit your account.',
					'Show a clear disclosure on the page: “As an Amazon Associate I earn from qualifying purchases.”',
					'Do not say Amazon sponsors, supports, or endorses LuxeTrendsetters.',
					'Amazon customers stay Amazon’s customers. Send service questions to Amazon.',
					'Amazon does not promise how much commission you will earn.',
					'Orders you place yourself, or that close family place for you, are not a way to earn commission.',
				),
				'links'  => array(
					array(
						'label' => 'Operating Agreement',
						'url'   => 'https://affiliate-program.amazon.com/help/operating/agreement',
					),
					array(
						'label' => 'Associates Help',
						'url'   => 'https://affiliate-program.amazon.com/help',
					),
				),
			),
			array(
				'id'     => 'central',
				'label'  => 'Associates Central',
				'title'  => 'Associates Central is the source of truth',
				'points' => array(
					'Payment details, tax status, reports, SiteStripe, and program tools live there.',
					'This hub only stores your public tracking ID, a done checkbox, and links you choose to log.',
					'If Amazon moves a button, follow the labels inside Associates Central. Their site wins.',
				),
				'links'  => array(
					array(
						'label' => 'Open Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
					array(
						'label' => 'Program home',
						'url'   => 'https://affiliate-program.amazon.com/',
					),
				),
			),
			array(
				'id'     => 'trending',
				'label'  => 'Trending Now',
				'title'  => 'Trending Now vs public Best Sellers',
				'points' => array(
					'Trending Now inside Associates Central is behind your login. Open it there.',
					'The button below tags Amazon’s public Best Sellers page. That is a different list.',
					'Best Sellers changes. Recheck the product on Amazon before you describe price or stock.',
				),
				'links'  => array(
					array(
						'label' => 'Public Best Sellers',
						'url'   => 'https://www.amazon.com/gp/bestsellers',
					),
				),
			),
			array(
				'id'     => 'faqs',
				'label'  => 'FAQs',
				'title'  => 'FAQs',
				'points' => array(
					'Does this plugin log into Amazon? No. You sign in on Amazon’s site.',
					'Can someone use this plugin to open your Associates account? No. It has no Amazon password and no session.',
					'Does it rewrite catalog URLs? No. Product Scout’s rule stands: existing Amazon URLs stay as they are.',
					'What link does it build? https://www.amazon.com/dp/ASIN?tag=your-tracking-id',
					'Why is earnings blank? Because the report is inside your Associates login, not in WordPress.',
					'Program questions belong in Amazon’s help center. This page only explains the hub.',
				),
				'links'  => array(
					array(
						'label' => 'Amazon Associates Help',
						'url'   => 'https://affiliate-program.amazon.com/help',
					),
				),
			),
			array(
				'id'     => 'rewards',
				'label'  => 'Creator Rewards',
				'title'  => 'Creator Rewards',
				'points' => array(
					'Amazon offers incentives for hitting revenue goals to eligible creators.',
					'Eligibility and the current reward are decided in Associates Central.',
					'This plugin cannot enroll you or show a reward balance.',
				),
				'links'  => array(
					array(
						'label' => 'Open Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
				),
			),
			array(
				'id'     => 'tutorials',
				'label'  => 'Tutorials',
				'title'  => 'Video tutorials and webinars',
				'points' => array(
					'Live onboarding webinars and recorded sessions are listed in Associates Central.',
					'SiteStripe’s help page is the desktop link walkthrough.',
					'Mobile GetLink is inside the Amazon shopping app after you sign in as an Associate.',
				),
				'links'  => array(
					array(
						'label' => 'Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
					array(
						'label' => 'SiteStripe help',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/GJMMT7G4C8K4Y3AY',
					),
				),
			),
			array(
				'id'     => 'connections',
				'label'  => 'Creator Connections',
				'title'  => 'Creator Connections',
				'points' => array(
					'Creator Connections lets eligible creators work with brands for a bonus commission on products they share.',
					'Campaigns are accepted inside Associates Central. This plugin cannot opt you into a brand deal.',
					'SiteStripe can show related Creator Connections campaigns on a product page while you are signed in.',
				),
				'links'  => array(
					array(
						'label' => 'Open Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
				),
			),
			array(
				'id'     => 'bounties',
				'label'  => 'Subscriptions',
				'title'  => 'Prime, Music, Audible, and other bounties',
				'points' => array(
					'Amazon pays a bounty for some sign-ups, including Prime, Amazon Music, and Audible.',
					'Those links come from SiteStripe or the link tools in Associates Central so the bounty is attached.',
					'Do not invent a bounty link. A plain homepage URL does not replace Amazon’s bounty link.',
				),
				'links'  => array(
					array(
						'label' => 'Build bounty links in Associates Central',
						'url'   => 'https://affiliate-program.amazon.com/home',
					),
					array(
						'label' => 'SiteStripe',
						'url'   => 'https://affiliate-program.amazon.com/help/node/topic/GJMMT7G4C8K4Y3AY',
					),
				),
			),
			array(
				'id'     => 'partners',
				'label'  => 'Content Partners',
				'title'  => 'Amazon Content Partners',
				'points' => array(
					'Content Partners is a separate, voluntary program for eligible U.S. creators.',
					'Amazon’s published offer includes a +1% commission boost on eligible Associates sales, plus hosting and AI traffic tools.',
					'Enroll only on Amazon’s Content Partners site. This plugin does not send your site content to Amazon.',
				),
				'links'  => array(
					array(
						'label' => 'Content Partners',
						'url'   => 'https://contentpartners.amazon.com/',
					),
					array(
						'label' => 'Amazon’s announcement',
						'url'   => 'https://www.aboutamazon.com/news/innovation-at-amazon/amazon-content-partners-preview',
					),
				),
			),
			array(
				'id'     => 'agreement',
				'label'  => 'Operating Agreement',
				'title'  => 'Operating Agreement',
				'points' => array(
					'Read the current agreement on Amazon before you rely on a commission.',
					'The agreement requires a clear associate disclosure and tagged special links.',
					'It also says Amazon can change the program, and it does not guarantee earnings.',
					'The copy below is a checklist. It is not a substitute for the agreement.',
				),
				'links'  => array(
					array(
						'label' => 'Read the Operating Agreement',
						'url'   => 'https://affiliate-program.amazon.com/help/operating/agreement',
					),
				),
			),
		);
	}

	/**
	 * @return string[]
	 */
	public static function outbound_urls() {
		$urls = array();
		foreach ( array( self::steps(), self::sections() ) as $groups ) {
			foreach ( $groups as $group ) {
				if ( empty( $group['links'] ) || ! is_array( $group['links'] ) ) {
					continue;
				}
				foreach ( $group['links'] as $link ) {
					if ( isset( $link['url'] ) ) {
						$urls[] = $link['url'];
					}
				}
			}
		}
		return $urls;
	}

	/**
	 * @return string[]
	 */
	public static function required_ids() {
		return array(
			'earnings',
			'policies',
			'central',
			'trending',
			'faqs',
			'rewards',
			'tutorials',
			'connections',
			'bounties',
			'partners',
			'agreement',
		);
	}
}
