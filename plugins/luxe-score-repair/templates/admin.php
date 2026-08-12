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
		'help'  => 'Writes a short valid robots.txt to disk so Hostinger cannot keep serving the 110KB Autopilot file.',
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
		'help'  => '/blog/ → /blogs/ on init, before Rank Math. No Rank Math redirect rows.',
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
	'process_learning'     => array(
		'label' => '24/7 process learning',
		'help'  => 'Always on: heal bloated robots.txt on public hits, 5-minute heal cron, 15-minute verify, visitor HTML snapshots when Hostinger blocks loopback.',
	),
	'auto_purge'           => array(
		'label' => 'Automatic cache purge',
		'help'  => 'Calls LiteSpeed purge_all and wp_cache_flush after heals. Does not call Hostinger’s CDN API.',
	),
	'fix_sitemaps'         => array(
		'label' => 'Clean XML sitemaps',
		'help'  => 'Strips wishlist, portal, test, and other noindex URLs from Rank Math sitemaps. Drops the shop page from the page sitemap so it is listed once.',
	),
);

$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
$band  = isset( $last['seo_band'] ) ? (string) $last['seo_band'] : '';
$score = isset( $last['score_10'] ) ? $last['score_10'] : '';
$seo100 = isset( $last['seo_100'] ) ? (int) $last['seo_100'] : 0;
$at    = ! empty( $last['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $last['at'] ) : 'Not run yet';
$signals = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
?>
<div class="wrap lsr-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · 24/7 public output repair</p>
		<h1>Luxe Score Repair <?php echo esc_html( LUXE_SCORE_REPAIR_VERSION ); ?></h1>
		<p class="lsr-lead">Always-on learning holds technical SEO at 95–100 and public-output at 9.5–10. It heals robots.txt, strips junk from sitemaps, 301s /blog/, purges LiteSpeed, and verifies from live homepage HTML — including when Hostinger blocks loopback.</p>
	</header>

	<section class="lsr-scorebar">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Verified processes</p>
			<p class="lsr-big"><?php echo esc_html( $green . ' / ' . $total ); ?> <span>green</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Public output</p>
			<p class="lsr-big"><?php echo esc_html( (string) $score ); ?><span> / 10</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Technical SEO</p>
			<p class="lsr-big"><?php echo esc_html( (string) $seo100 ); ?><span> / 100</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">SEO band</p>
			<p class="lsr-big lsr-band"><?php echo esc_html( $band ? $band : 'armed' ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">24/7 learning</p>
			<p class="lsr-big">ON</p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last learning</p>
			<p class="lsr-when"><?php echo esc_html( $at ); ?></p>
		</div>
	</section>

	<section class="lsr-signals">
		<h2>Green signals</h2>
		<ul class="lsr-signal-list">
			<?php foreach ( $signals as $signal ) : ?>
				<?php
				$level = isset( $signal['level'] ) ? $signal['level'] : 'red';
				$label = isset( $signal['label'] ) ? $signal['label'] : '';
				$detail = isset( $signal['detail'] ) ? $signal['detail'] : '';
				?>
				<li class="lsr-signal lsr-signal-<?php echo esc_attr( $level ); ?>">
					<span class="lsr-dot" aria-hidden="true"></span>
					<span>
						<strong><?php echo esc_html( strtoupper( $level ) ); ?> · <?php echo esc_html( $label ); ?></strong>
						<em><?php echo esc_html( $detail ); ?></em>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<form method="post" class="lsr-learn-form">
			<?php wp_nonce_field( 'luxe_score_repair_learn' ); ?>
			<button type="submit" name="luxe_score_repair_learn_now" value="1" class="button button-primary lsr-save">Run 24/7 learning now</button>
		</form>
	</section>

	<?php if ( ! empty( $log ) ) : ?>
		<section class="lsr-card">
			<h2>Learning log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Green</th>
						<th>Output</th>
						<th>SEO</th>
						<th>Band</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( ( isset( $row['green'] ) ? $row['green'] : '0' ) . ' / ' . ( isset( $row['total'] ) ? $row['total'] : '0' ) ); ?></td>
						<td><?php echo esc_html( isset( $row['score'] ) ? $row['score'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['seo_100'] ) ? $row['seo_100'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['band'] ) ? $row['band'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="lsr-grid">
		<article class="lsr-card lsr-card-safe">
			<h2>Safety lock</h2>
			<ul>
				<li>Never writes <code>post_content</code></li>
				<li>Never changes post status</li>
				<li>Never rewrites Amazon or product permalinks</li>
				<li>Never creates Rank Math redirect rows</li>
				<li>Learning is heal + verify only</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>24/7 automatic heals</h2>
			<ul>
				<li>Overwrite bloated <code>robots.txt</code> on every public hit</li>
				<li>Strip wishlist and junk URLs from XML sitemaps</li>
				<li>301 <code>/blog/</code> on <code>init</code> before Rank Math</li>
				<li>LiteSpeed <code>purge_all</code> after each file heal</li>
				<li>Visitor HTML snapshot when loopback is blocked</li>
			</ul>
		</article>
	</section>
	<p class="lsr-note">Technical SEO 95–100 and public-output 9.5–10 are this plugin’s job (titles, robots, schema, headers, noindex, sitemaps). Google’s article-quality score is not. This plugin cannot write buyer guides.</p>

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
