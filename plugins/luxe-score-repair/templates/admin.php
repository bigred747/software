<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checks = array(
	'fix_titles'           => array(
		'label' => 'Homepage title and meta description',
		'help'  => 'Replaces “HOME: Features, Use Cases and Buyer Fit” with a brand title Google can rank.',
	),
	'fix_open_graph'       => array(
		'label' => 'Open Graph and Twitter cards',
		'help'  => 'Stops the cheap HP laptop Amazon image from representing the homepage.',
	),
	'fix_schema'           => array(
		'label' => 'JSON-LD schema quarantine',
		'help'  => 'Removes truncated FAQ JSON-LD and emits a complete, valid FAQ + Organization graph.',
	),
	'fix_robots_txt'       => array(
		'label' => 'Clean robots.txt',
		'help'  => 'Replaces the 110KB Autopilot duplicate-comment file with a short valid robots.txt and sitemap_index.xml.',
	),
	'noindex_utility'      => array(
		'label' => 'Noindex junk and utility URLs',
		'help'  => 'Cart, checkout, account, wishlist, client portal, test blog, authors, and search stay out of Google.',
	),
	'clean_content'        => array(
		'label' => 'Hide leaked shortcodes and AI filler',
		'help'  => 'Display-only. Does not write post_content.',
	),
	'clean_titles'         => array(
		'label' => 'Clean concatenated product titles',
		'help'  => 'Display-only. Does not rewrite Amazon or product permalinks.',
	),
	'fix_headers'          => array(
		'label' => 'Public cache and security headers',
		'help'  => 'Stops no-store on public catalog HTML. Adds HSTS on HTTPS.',
	),
	'repair_known_404s'    => array(
		'label' => 'Exact 301s for known dead URLs',
		'help'  => '/blog/ → /blogs/. No fuzzy matching. No Rank Math redirect rows.',
	),
	'buffer_html'          => array(
		'label' => 'Final HTML pass',
		'help'  => 'Catches titles, robots, and schema that other plugins print after wp_head filters.',
	),
	'image_alts'           => array(
		'label' => 'Missing image alt text',
		'help'  => 'Fills empty alt attributes. Never overwrites a real alt.',
	),
	'hide_generator'       => array(
		'label' => 'Hide generator meta',
		'help'  => 'Removes Site Kit / WordPress generator tags from public HTML.',
	),
	'affiliate_disclosure' => array(
		'label' => 'Homepage Amazon disclosure',
		'help'  => 'Prints a clear Associate disclosure on the homepage footer.',
	),
);
?>
<div class="wrap lsr-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · public output repair</p>
		<h1>Luxe Score Repair <?php echo esc_html( LUXE_SCORE_REPAIR_VERSION ); ?></h1>
		<p class="lsr-lead">Fixes the live SEO errors on luxetrendsetters.com without touching stored posts, Amazon URLs, or Rank Math redirects.</p>
	</header>

	<section class="lsr-grid">
		<article class="lsr-card lsr-card-score">
			<h2>What this can raise</h2>
			<ul>
				<li><strong>Lighthouse SEO</strong> toward 95–100 after a LiteSpeed Purge All (titles, description, robots.txt, crawlable links, valid schema, image alts).</li>
				<li><strong>Public site quality</strong> by hiding junk URLs, truncated FAQ, and AI filler from visitors and Google.</li>
			</ul>
			<p class="lsr-note">A plugin cannot turn 200 thin Amazon listings into a 10/10 editorial magazine. It can make every technical error from the scan stop shipping to Google.</p>
		</article>
		<article class="lsr-card lsr-card-safe">
			<h2>Safety lock</h2>
			<ul>
				<li>Never writes <code>post_content</code></li>
				<li>Never changes post status</li>
				<li>Never rewrites Amazon or product permalinks</li>
				<li>Never creates Rank Math redirect rows</li>
				<li>No content cron</li>
			</ul>
			<p>After activate: <strong>LiteSpeed → Purge All</strong>. Guest Mode OFF, Crawler OFF.</p>
		</article>
	</section>

	<form method="post" class="lsr-form">
		<?php wp_nonce_field( 'luxe_score_repair_save' ); ?>
		<input type="hidden" name="luxe_score_repair_save" value="1" />
		<table class="widefat striped lsr-table">
			<thead>
				<tr>
					<th scope="col">On</th>
					<th scope="col">Repair</th>
					<th scope="col">What it does</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $checks as $key => $row ) : ?>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
					<td><?php echo esc_html( $row['help'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary lsr-save">Save repairs</button>
		</p>
	</form>
</div>
