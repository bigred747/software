<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="luxe-op-widget">
	<p class="luxe-op-kicker">Admin-only rescue view. It does not auto-write, auto-publish, hook frontend content, or run content cron. Hard Rescue stays active. Do not connect AdSense or Reader Revenue Manager to “fix” these numbers.</p>

	<ul class="luxe-op-chips">
		<li class="is-ok">Hard Rescue <?php echo ! empty( $brief['hard_rescue'] ) ? 'active' : 'not detected'; ?></li>
		<li class="<?php echo ( null !== $brief['stay_red'] && $brief['stay_red'] > 0 ) ? 'is-bad' : 'is-ok'; ?>">
			Stay Repair <?php echo ! empty( $brief['stay_repair'] ) ? 'active' : 'not installed'; ?>
			<?php if ( null !== $brief['stay_red'] ) : ?>
				· <?php echo esc_html( (string) $brief['stay_red'] ); ?> red
			<?php endif; ?>
		</li>
		<li class="is-warn">Site Kit 19K · 99.8% Direct · 1s · 0 search clicks</li>
	</ul>

	<table class="widefat luxe-op-table">
		<thead>
			<tr>
				<th>Dashboard number</th>
				<th>What it actually means</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>19K users · 99.8% Direct · 1s</td>
				<td>Guest Mode reload + thin tag/category pages. Stay Repair 1.9.5 stops the reload and adds a five-minute compare hub on those archives. Site Kit is a 28-day average and will not show 5 minutes today. Stay Repair dwell samples
					<?php if ( $brief['dwell_samples'] ) : ?>
						(<?php echo esc_html( (string) $brief['dwell_samples'] ); ?> samples, avg <?php echo esc_html( (string) $brief['dwell_avg'] ); ?>s)
					<?php else : ?>
						(none yet — after purge, open /product-tag/apple/ as a guest and browse)
					<?php endif; ?>
					are the current truth. Keep Guest Mode and Crawler OFF.
				</td>
			</tr>
			<tr>
				<td>Search 36 impressions · 0 clicks · 0% CTR</td>
				<td>This is the real search picture. Keywords like “macbook air m4…” have 1 impression each. Rank Math Overview at 0 is a disconnected widget, not a second data source.</td>
			</tr>
			<tr>
				<td>Add to cart 0% · AdSense disconnected · no sales</td>
				<td>Expected. This is an Amazon affiliate catalog, not a checkout store. Do not connect AdSense, Google Ads, or Reader Revenue Manager. Watch Amazon Associate reports.</td>
			</tr>
			<tr>
				<td>Top pages /product-tag/apple/ at 1s</td>
				<td>Those archives had no compare hub. 1.9.5 prints one under the product grid. Five minutes is real browsing, not a faked analytics ping.</td>
			</tr>
			<tr>
				<td>Engagement 18% · AI Assistant 0%</td>
				<td>18% engagement on 18,555 Direct sessions is bot-heavy. AI Assistant is not a traffic source to optimize. TBT 260ms “needs improvement”; LCP 1.7s and CLS 0 are already Good.</td>
			</tr>
			<tr>
				<td>Richard Brummer SEO: 1 verified red</td>
				<td>Homepage FAQ JSON-LD is truncated. Upload Stay Repair 1.9.4, purge LiteSpeed, then <a href="<?php echo esc_url( $brief['mission_url'] ); ?>">open Mission Control</a> and run the audit.</td>
			</tr>
			<tr>
				<td>Reader Revenue Manager / Ads nags</td>
				<td>Site Kit upsells. Operator Dashboard 1.1.0 hides them on the Site Kit screen. They are not required for this site.</td>
			</tr>
		</tbody>
	</table>

	<p class="luxe-op-actions">
		<a class="button button-primary" href="<?php echo esc_url( $brief['stay_url'] ); ?>">Open Stay Repair</a>
		<a class="button" href="<?php echo esc_url( $brief['mission_url'] ); ?>">Open Mission Control</a>
		<a class="button" href="<?php echo esc_url( $brief['settings_url'] ); ?>">Operator settings</a>
		<button type="button" class="button" id="luxe-op-toggle-notices">Show hidden notices</button>
	</p>
	<p class="luxe-op-note">WordPress <?php echo esc_html( (string) $brief['wp_version'] ); ?> · <?php echo esc_html( (string) $brief['theme'] ); ?> · <?php echo esc_html( (string) $brief['published_posts'] ); ?> posts / <?php echo esc_html( (string) $brief['published_pages'] ); ?> pages.</p>
</div>
