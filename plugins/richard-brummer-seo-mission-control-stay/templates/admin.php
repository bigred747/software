<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$checks = array(
	'guest_vary_shield'        => 'Skip LiteSpeed Guest Mode reload (fixes 1-second sessions)',
	'hide_leaked_shortcodes'   => 'Hide leaked [toc] / [ez-toc] shortcodes on output',
	'hide_utility_ai_filler'   => 'Hide AI filler on cart, checkout, account, wishlist',
	'related_rail'             => 'Show related published guides at the end of posts/products',
	'archive_compare_hub'      => 'Add a five-minute compare hub on tag/category/brand archives (the 1s Site Kit pages)',
	'reading_progress'         => 'Show a reading progress bar on long pages',
	'helpful_404'              => 'Replace empty 404s with search + live guides',
	'log_404'                  => 'Log 404 paths for Rank Math redirect review',
	'dwell_beacon'             => 'Record honest first-party time-on-page buckets',
	'drop_missing_assets'      => 'Do not print plugin CSS/JS when the file is missing on disk',
	'serve_bot_placeholders'   => 'Answer /meta.json and /.well-known/agents.js instead of 404',
	'repair_known_dead_urls'   => '301 a short exact-path list of deleted posts to live catalog pages',
	'schema_quarantine'        => 'Remove only invalid rendered JSON-LD (truncated FAQ schema). Valid Rank Math stays',
	'neutralize_history_traps' => 'Neutralize back-button traps if found (off by default)',
	'auto_redirect_404'        => 'Auto-create redirects (leave OFF — review in Rank Math)',
);
?>
<div class="wrap rbsmc-stay-wrap">
	<h1>Richard Brummer SEO · Stay Repair 1.9.5</h1>
	<p class="rbsmc-stay-lead">This module sits beside Mission Control 1.8.1. It does not fake 5-minute Site Kit numbers, auto-publish, rewrite stored posts, or trap the back button. It stops the 1-second Guest Mode reload and puts a real compare hub on the tag/category/brand pages that currently show 1s.</p>

	<section class="rbsmc-stay-card">
		<h2>The 1-second look vs a 5-minute stay</h2>
		<ol>
			<li><strong>Site Kit 1s will not jump today.</strong> It is a 28-day rolling average of 18,555 Direct sessions. After this upload + LiteSpeed purge, new visits can stay. Site Kit lags.</li>
			<li><strong>99.8% Direct + 1s</strong> is Guest Mode reload / crawler traffic. Keep Guest Mode and Crawler OFF. 1.9.5 puts the skip flag first in <code>&lt;head&gt;</code> so the reload cannot run.</li>
			<li><strong>The 1s URLs are archives</strong> (/product-tag/apple/, /brand/dji/, wearables). 1.9.5 adds a compare hub there: checklist, products, guides. That is how someone actually spends five minutes.</li>
			<li>Do not connect AdSense or Reader Revenue Manager. Do not restore back-button traps.</li>
			<li>Trust Stay Repair dwell samples after you browse as a guest. That is the current truth.</li>
		</ol>
	</section>

	<section class="rbsmc-stay-card">
		<h2>Traffic snapshot (paste Site Kit / Rank Math)</h2>
		<form method="post">
			<?php wp_nonce_field( 'rbsmc_stay_save' ); ?>
			<input type="hidden" name="rbsmc_stay_save" value="1" />
			<div class="rbsmc-stay-grid">
				<label>Unique visitors <input type="number" name="snapshot_users" value="<?php echo isset( $snap['users'] ) ? esc_attr( $snap['users'] ) : '19000'; ?>" /></label>
				<label>Avg time on page (seconds) <input type="number" name="snapshot_avg_seconds" value="<?php echo isset( $snap['avg_seconds'] ) ? esc_attr( $snap['avg_seconds'] ) : '1'; ?>" /></label>
				<label>Search impressions <input type="number" name="snapshot_impressions" value="<?php echo isset( $snap['impressions'] ) ? esc_attr( $snap['impressions'] ) : '36'; ?>" /></label>
				<label>Search clicks <input type="number" name="snapshot_clicks" value="<?php echo isset( $snap['clicks'] ) ? esc_attr( $snap['clicks'] ) : '0'; ?>" /></label>
				<label>Direct channel % <input type="number" name="snapshot_direct_pct" min="0" max="100" value="<?php echo isset( $snap['direct_pct'] ) ? esc_attr( $snap['direct_pct'] ) : '100'; ?>" /></label>
				<label>Engagement rate % <input type="number" name="snapshot_engagement" min="0" max="100" value="<?php echo isset( $snap['engagement'] ) ? esc_attr( $snap['engagement'] ) : '18'; ?>" /></label>
				<label>404 URL count <input type="number" name="snapshot_404" value="<?php echo isset( $snap['not_found'] ) ? esc_attr( $snap['not_found'] ) : '75'; ?>" /></label>
				<label>WooCommerce sales <input type="text" name="snapshot_wc_sales" value="<?php echo isset( $snap['wc_sales'] ) ? esc_attr( $snap['wc_sales'] ) : '0'; ?>" /></label>
			</div>
			<h3>Safe output fixes</h3>
			<?php foreach ( $checks as $key => $label ) : ?>
				<label class="rbsmc-stay-check"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			<p>
				<label>Target stay minutes <input type="number" name="target_dwell_minutes_min" value="<?php echo esc_attr( $settings['target_dwell_minutes_min'] ); ?>" min="1" max="60" /> to <input type="number" name="target_dwell_minutes_max" value="<?php echo esc_attr( $settings['target_dwell_minutes_max'] ); ?>" min="1" max="60" /></label>
			</p>
			<p><button class="button button-primary" type="submit">Save Stay Repair</button>
			<button class="button" type="button" id="rbsmc-stay-run-audit">Run audit now</button></p>
		</form>
	</section>

	<section class="rbsmc-stay-card">
		<h2>Latest audit</h2>
		<?php if ( empty( $audit['signals'] ) ) : ?>
			<p>No audit yet. Click <strong>Run audit now</strong> after saving.</p>
		<?php else : ?>
			<p>Ran <?php echo esc_html( gmdate( 'Y-m-d H:i:s', absint( $audit['ran_at'] ) ) ); ?> UTC · <?php echo esc_html( (string) $audit['red_count'] ); ?> red</p>
			<ul class="rbsmc-stay-signals">
				<?php foreach ( $audit['signals'] as $signal ) : ?>
					<li class="lvl-<?php echo esc_attr( $signal['level'] ); ?>">
						<strong><?php echo esc_html( strtoupper( $signal['level'] ) ); ?> — <?php echo esc_html( $signal['title'] ); ?></strong>
						<p><?php echo esc_html( $signal['detail'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="rbsmc-stay-card">
		<h2>Honest dwell buckets</h2>
		<?php if ( empty( $dwell['samples'] ) ) : ?>
			<p>No first-party samples yet. After cache purge, browse the site as a guest.</p>
		<?php else : ?>
			<p><?php echo esc_html( (string) $dwell['samples'] ); ?> samples · average <?php echo esc_html( (string) round( $dwell['seconds_sum'] / max( 1, $dwell['samples'] ) ) ); ?>s</p>
			<ul>
				<?php foreach ( (array) $dwell['buckets'] as $bucket => $n ) : ?>
					<li><?php echo esc_html( $bucket ); ?>: <?php echo esc_html( (string) $n ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="rbsmc-stay-card">
		<h2>404 log (top 20)</h2>
		<?php
		if ( is_array( $log ) && $log ) {
			uasort(
				$log,
				function ( $a, $b ) {
					return $b['hits'] - $a['hits'];
				}
			);
			$log = array_slice( $log, 0, 20, true );
			echo '<table class="widefat"><thead><tr><th>Path</th><th>Hits</th></tr></thead><tbody>';
			foreach ( $log as $path => $row ) {
				echo '<tr><td>' . esc_html( $path ) . '</td><td>' . esc_html( (string) $row['hits'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p>1.9.2 already 301s a short exact-path list and answers bot probes. For anything else, create matching redirects in Rank Math → Redirections. Leave “Auto-create redirects” OFF.</p>';
		} else {
			echo '<p>No 404s logged by Stay Repair yet.</p>';
		}
		?>
	</section>
</div>
