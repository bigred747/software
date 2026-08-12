<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$checks = array(
	'guest_vary_shield'            => 'Skip LiteSpeed Guest Mode reload (fixes 1-second sessions)',
	'hide_leaked_shortcodes'       => 'Hide leaked [toc] shortcodes on output',
	'hide_utility_ai_filler'       => 'Hide AI filler on cart/checkout/account',
	'related_rail'                 => 'Related published guides at end of posts/products',
	'reading_progress'             => 'Reading progress bar',
	'helpful_404'                  => 'Helpful 404 with live guides',
	'log_404'                      => 'Log 404 paths',
	'dwell_beacon'                 => 'Honest first-party dwell buckets',
	'amazon_identification_output' => 'Add Amazon Associate identification on rendered HTML if missing',
	'schema_quarantine'            => 'Remove only invalid JSON-LD from rendered HTML',
	'neutralize_history_traps'     => 'Neutralize back-button traps (off by default)',
	'auto_redirect_404'            => 'Auto-create redirects (leave OFF)',
);
?>
<div class="wrap rbsmc-wrap">
	<h1>Richard Brummer SEO Mission Control 1.9.0</h1>
	<p>Complete plugin: Amazon allowlist, JSON-LD evidence, 38-plugin catalog, Search Console connection status, verified scoring, and Stay Repair. Does not auto-publish or trap the back button.</p>

	<section class="rbsmc-card">
		<h2>Score</h2>
		<p><?php echo isset( $audit['score']['score'] ) ? esc_html( (string) $audit['score']['score'] ) : '—'; ?> / 100
			· red <?php echo isset( $audit['score']['red'] ) ? esc_html( (string) $audit['score']['red'] ) : '0'; ?>
			· MANUAL/INFO excluded from score</p>
		<p>Heartbeat: <?php echo ! empty( $beat['at'] ) ? esc_html( gmdate( 'Y-m-d H:i:s', absint( $beat['at'] ) ) . ' UTC home=' . $beat['home_code'] . ' rest=' . $beat['rest_code'] ) : 'not run yet'; ?></p>
		<p>GSC: <?php echo esc_html( $gsc['state'] ); ?> — <?php echo esc_html( $gsc['note'] ); ?></p>
		<p>Compatibility catalog: <?php echo esc_html( (string) $compat['catalog_size'] ); ?> named plugins · <?php echo esc_html( (string) $compat['active'] ); ?> active</p>
	</section>

	<section class="rbsmc-card">
		<h2>Traffic snapshot + settings</h2>
		<form method="post">
			<?php wp_nonce_field( 'rbsmc_save' ); ?>
			<input type="hidden" name="rbsmc_save" value="1" />
			<div class="rbsmc-grid">
				<label>Unique visitors <input type="number" name="snapshot_users" value="<?php echo isset( $snap['users'] ) ? esc_attr( $snap['users'] ) : '18000'; ?>" /></label>
				<label>Avg seconds <input type="number" name="snapshot_avg_seconds" value="<?php echo isset( $snap['avg_seconds'] ) ? esc_attr( $snap['avg_seconds'] ) : '1'; ?>" /></label>
				<label>Impressions <input type="number" name="snapshot_impressions" value="<?php echo isset( $snap['impressions'] ) ? esc_attr( $snap['impressions'] ) : '36'; ?>" /></label>
				<label>Clicks <input type="number" name="snapshot_clicks" value="<?php echo isset( $snap['clicks'] ) ? esc_attr( $snap['clicks'] ) : '0'; ?>" /></label>
				<label>404 URLs <input type="number" name="snapshot_404" value="<?php echo isset( $snap['not_found'] ) ? esc_attr( $snap['not_found'] ) : '63'; ?>" /></label>
				<label>Woo sales <input type="text" name="snapshot_wc_sales" value="<?php echo isset( $snap['wc_sales'] ) ? esc_attr( $snap['wc_sales'] ) : '0'; ?>" /></label>
			</div>
			<?php foreach ( $checks as $key => $label ) : ?>
				<label class="rbsmc-check"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			<p>
				<label>Facebook <input type="url" name="social_facebook" value="<?php echo esc_attr( $settings['social_facebook'] ); ?>" /></label>
				<label>Instagram <input type="url" name="social_instagram" value="<?php echo esc_attr( $settings['social_instagram'] ); ?>" /></label>
				<label>YouTube <input type="url" name="social_youtube" value="<?php echo esc_attr( $settings['social_youtube'] ); ?>" /></label>
				<label>Pinterest <input type="url" name="social_pinterest" value="<?php echo esc_attr( $settings['social_pinterest'] ); ?>" /></label>
				<label>Red-signal email <input type="email" name="red_email" value="<?php echo esc_attr( $settings['red_email'] ); ?>" /></label>
			</p>
			<p><button class="button button-primary" type="submit">Save</button>
			<button class="button" type="button" id="rbsmc-run-audit">Run full audit now</button></p>
		</form>
	</section>

	<section class="rbsmc-card">
		<h2>Signals</h2>
		<?php if ( empty( $audit['signals'] ) ) : ?>
			<p>Click Run full audit now.</p>
		<?php else : ?>
			<ul class="rbsmc-signals">
				<?php foreach ( $audit['signals'] as $signal ) : ?>
					<li class="lvl-<?php echo esc_attr( $signal['level'] ); ?>">
						<strong><?php echo esc_html( strtoupper( $signal['level'] ) ); ?> — <?php echo esc_html( $signal['title'] ); ?></strong>
						<p><?php echo esc_html( $signal['detail'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="rbsmc-card">
		<h2>Amazon / JSON-LD</h2>
		<p>Amazon status: <?php echo isset( $audit['amazon']['status'] ) ? esc_html( $audit['amazon']['status'] ) : '—'; ?>
			· links <?php echo isset( $audit['amazon']['links'] ) ? esc_html( (string) $audit['amazon']['links'] ) : '0'; ?></p>
		<p>JSON-LD blocks: <?php echo isset( $audit['schema']['blocks'] ) ? esc_html( (string) $audit['schema']['blocks'] ) : '0'; ?>
			· invalid <?php echo isset( $audit['schema']['invalid'] ) ? esc_html( (string) $audit['schema']['invalid'] ) : '0'; ?></p>
		<p>Put the Google key outside web root: <code>define('RBSMC_GSC_KEY_FILE', '/secure/path/service-account.json'); define('RBSMC_GSC_PROPERTY', 'sc-domain:luxetrendsetters.com'); define('RBSMC_AMAZON_TAG', 'yourtag-20');</code></p>
	</section>

	<section class="rbsmc-card">
		<h2>Dwell buckets</h2>
		<?php if ( empty( $dwell['samples'] ) ) : ?>
			<p>No samples yet. Purge cache and visit the site logged out.</p>
		<?php else : ?>
			<p><?php echo esc_html( (string) $dwell['samples'] ); ?> samples · avg <?php echo esc_html( (string) round( $dwell['seconds_sum'] / max( 1, $dwell['samples'] ) ) ); ?>s</p>
			<ul>
				<?php foreach ( (array) $dwell['buckets'] as $bucket => $n ) : ?>
					<li><?php echo esc_html( $bucket . ': ' . $n ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="rbsmc-card">
		<h2>404 log</h2>
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
		} else {
			echo '<p>None yet.</p>';
		}
		?>
	</section>

	<section class="rbsmc-card">
		<h2>Compatibility catalog (38)</h2>
		<table class="widefat"><thead><tr><th>Plugin</th><th>Active</th><th>In catalog</th><th>Note</th></tr></thead><tbody>
		<?php foreach ( array_slice( $compat['rows'], 0, 40 ) as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row['name'] . ' ' . $row['version'] ); ?></td>
				<td><?php echo $row['active'] ? 'yes' : 'no'; ?></td>
				<td><?php echo $row['catalog'] ? 'yes' : 'no'; ?></td>
				<td><?php echo esc_html( $row['note'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</section>
</div>
