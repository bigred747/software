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
	'copyright_lock'       => array(
		'label' => 'Copyright lock',
		'help'  => 'Refuses copied Instagram video, CDN media, and republished reels on public pages.',
	),
	'unique_copy'          => array(
		'label' => 'Unique buyer-guide copy',
		'help'  => 'Keeps real unique copy. The 37-word ranking trick is refused for this catalog site.',
	),
	'process_learning'     => array(
		'label' => 'Process learning',
		'help'  => 'Every 15 minutes: heal robots.txt, purge LiteSpeed, verify each process, learn exact 301 hops.',
	),
	'auto_purge'           => array(
		'label' => 'Automatic cache purge',
		'help'  => 'Calls LiteSpeed purge_all and wp_cache_flush after heals. Does not call Hostinger’s CDN API.',
	),
);

$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
$band  = isset( $last['seo_band'] ) ? (string) $last['seo_band'] : '';
$score = isset( $last['score_10'] ) ? $last['score_10'] : '';
$at    = ! empty( $last['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $last['at'] ) : 'Not run yet';
$signals = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
?>
<div class="wrap lsr-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · SEO plugin</p>
		<h1>Luxe SEO Score Repair <?php echo esc_html( LUXE_SCORE_REPAIR_VERSION ); ?></h1>
		<p class="lsr-lead">This is the SEO plugin. Each process has a live green/red signal. Unique buyer-guide copy stays on. Copied Instagram video and thin 37-word ranking pages stay off. Learning heals robots.txt, 301s /blog/ before Rank Math, purges LiteSpeed, and rechecks every 15 minutes.</p>
	</header>

	<section class="lsr-scorebar">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Verified processes</p>
			<p class="lsr-big"><?php echo esc_html( $green . ' / ' . $total ); ?> <span>green</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Process score</p>
			<p class="lsr-big"><?php echo esc_html( (string) $score ); ?><span> / 10</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">SEO band</p>
			<p class="lsr-big lsr-band"><?php echo esc_html( $band ? $band : 'armed' ); ?></p>
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
			<button type="submit" name="luxe_score_repair_learn_now" value="1" class="button button-primary lsr-save">Run process learning now</button>
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
						<th>Score</th>
						<th>Band</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( ( isset( $row['green'] ) ? $row['green'] : '0' ) . ' / ' . ( isset( $row['total'] ) ? $row['total'] : '0' ) ); ?></td>
						<td><?php echo esc_html( isset( $row['score'] ) ? $row['score'] : '' ); ?></td>
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
				<li>Never copies Instagram video or thin third-party landing pages</li>
				<li>Learning is heal + verify only</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>Automatic heals</h2>
			<ul>
				<li>Overwrite bloated <code>robots.txt</code> on disk</li>
				<li>301 <code>/blog/</code> on <code>init</code> before Rank Math</li>
				<li>LiteSpeed <code>purge_all</code> after each heal</li>
				<li>Re-learn exact hops if Rank Math still fires first</li>
			</ul>
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
