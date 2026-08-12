<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$checks = array(
	'guest_vary_shield'        => 'Skip LiteSpeed Guest Mode reload (fixes 1-second sessions)',
	'hide_leaked_shortcodes'   => 'Hide leaked [toc] / [ez-toc] shortcodes on output',
	'hide_utility_ai_filler'   => 'Hide AI filler on cart, checkout, account, wishlist',
	'related_rail'             => 'Show related published guides at the end of posts/products',
	'reading_progress'         => 'Show a reading progress bar on long pages',
	'helpful_404'              => 'Replace empty 404s with search + live guides',
	'log_404'                  => 'Log 404 paths for Rank Math redirect review',
	'dwell_beacon'             => 'Record honest first-party time-on-page buckets',
	'neutralize_history_traps' => 'Neutralize back-button traps if found (off by default)',
	'auto_redirect_404'        => 'Auto-create redirects (leave OFF — review in Rank Math)',
);
?>
<div class="wrap rbsmc-stay-wrap">
	<h1>Richard Brummer SEO · Stay Repair 1.9.0</h1>
	<p class="rbsmc-stay-lead">This module sits beside Mission Control 1.8.1. It does not auto-publish, rewrite stored posts, or restore fake 5–15 minute time with back-button hijacking. It stops the 1-second Guest Mode reload and keeps people on real guides.</p>

	<section class="rbsmc-stay-card">
		<h2>Why time-on-page collapsed to 1 second</h2>
		<ol>
			<li><strong>LiteSpeed Guest Mode</strong> reloads the first visit after <code>guest.vary.php</code>. Site Kit records that as a 1s page. Disable Guest Mode, then purge cache.</li>
			<li>The old 5–15 minute number was not restored with <code>pushState</code>/<code>popstate</code> traps. Google treats those as spam.</li>
			<li>Rank Math Overview at 0 impressions/clicks is a disconnected widget. Site Kit already shows 36 impressions and 0 clicks — search is not the traffic source.</li>
			<li>63 Rank Math 404 URLs and AI filler on cart/account pages cause instant exits.</li>
			<li>WooCommerce $0 is expected on an Amazon affiliate catalog. Watch Amazon reports, not WooCommerce orders.</li>
		</ol>
	</section>

	<section class="rbsmc-stay-card">
		<h2>Traffic snapshot (paste Site Kit / Rank Math)</h2>
		<form method="post">
			<?php wp_nonce_field( 'rbsmc_stay_save' ); ?>
			<input type="hidden" name="rbsmc_stay_save" value="1" />
			<div class="rbsmc-stay-grid">
				<label>Unique visitors <input type="number" name="snapshot_users" value="<?php echo isset( $snap['users'] ) ? esc_attr( $snap['users'] ) : '18000'; ?>" /></label>
				<label>Avg time on page (seconds) <input type="number" name="snapshot_avg_seconds" value="<?php echo isset( $snap['avg_seconds'] ) ? esc_attr( $snap['avg_seconds'] ) : '1'; ?>" /></label>
				<label>Search impressions <input type="number" name="snapshot_impressions" value="<?php echo isset( $snap['impressions'] ) ? esc_attr( $snap['impressions'] ) : '36'; ?>" /></label>
				<label>Search clicks <input type="number" name="snapshot_clicks" value="<?php echo isset( $snap['clicks'] ) ? esc_attr( $snap['clicks'] ) : '0'; ?>" /></label>
				<label>404 URL count <input type="number" name="snapshot_404" value="<?php echo isset( $snap['not_found'] ) ? esc_attr( $snap['not_found'] ) : '63'; ?>" /></label>
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
			echo '<p>Create matching redirects in Rank Math → Redirections. Stay Repair will not invent redirects automatically.</p>';
		} else {
			echo '<p>No 404s logged by Stay Repair yet.</p>';
		}
		?>
	</section>
</div>
