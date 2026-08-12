<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="luxe-op-widget">
	<p class="luxe-op-kicker">Admin-only rescue view. It does not auto-write, auto-publish, hook frontend content, or run content cron. Hard Rescue stays active.</p>

	<ul class="luxe-op-chips">
		<li class="is-ok">Hard Rescue <?php echo ! empty( $brief['hard_rescue'] ) ? 'active' : 'not detected'; ?></li>
		<li class="<?php echo ( null !== $brief['stay_red'] && $brief['stay_red'] > 0 ) ? 'is-bad' : 'is-ok'; ?>">
			Stay Repair <?php echo ! empty( $brief['stay_repair'] ) ? 'active' : 'not installed'; ?>
			<?php if ( null !== $brief['stay_red'] ) : ?>
				· <?php echo esc_html( (string) $brief['stay_red'] ); ?> red
			<?php endif; ?>
		</li>
		<li class="is-warn">Mission Control <?php echo ! empty( $brief['mission_control'] ) ? 'active · 1 red is truncated FAQ JSON-LD' : 'not detected'; ?></li>
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
				<td>Richard Brummer SEO: 1 verified red</td>
				<td>Homepage FAQ JSON-LD is cut off at “Are prices and availability final?”. Rank Math schema is valid. Upload Stay Repair 1.9.3, purge LiteSpeed, then <a href="<?php echo esc_url( $brief['mission_url'] ); ?>">open Mission Control</a> and run the audit.</td>
			</tr>
			<tr>
				<td>Rank Math Overview 0 / 0 / 0 / 0</td>
				<td>Rank Math is not connected to Search Console. Site Kit already shows 36 impressions and 0 clicks. This is not a ranking crash. Optional: <a href="<?php echo esc_url( $brief['rank_math_url'] ); ?>">Rank Math → General Settings → Analytics</a>.</td>
			</tr>
			<tr>
				<td>Site Kit 19K users · 1s avg</td>
				<td>Rolling 28-day Analytics lag. The 1s figure is the old LiteSpeed Guest Mode reload. Stay Repair first-party samples
					<?php if ( $brief['dwell_samples'] ) : ?>
						(<?php echo esc_html( (string) $brief['dwell_samples'] ); ?> samples, avg <?php echo esc_html( (string) $brief['dwell_avg'] ); ?>s)
					<?php else : ?>
						(none yet — browse the site as a guest after cache purge)
					<?php endif; ?>
					are the current truth.
				</td>
			</tr>
			<tr>
				<td>WooCommerce $0 · 0 orders</td>
				<td>Expected. This catalog sends shoppers to Amazon. Watch Amazon Associate reports, not WooCommerce net sales.</td>
			</tr>
			<tr>
				<td>404 Monitor 75 URLs / 184 hits</td>
				<td>Stay Repair answers <code>/meta.json</code> and <code>/.well-known/agents.js</code> and drops missing Link Guardian assets. Remaining paths need Rank Math redirects, not auto-publish.</td>
			</tr>
			<tr>
				<td>Wordfence US IPv6 260 blocks · login “richard”</td>
				<td>Failed username <code>richard</code> is not an existing user. Do not whitelist the blocked IPv6 blindly. Passkeys are optional on <a href="<?php echo esc_url( $brief['wordfence_url'] ); ?>">Login Security</a>.</td>
			</tr>
			<tr>
				<td>YITH / Rank Math blog widgets</td>
				<td>Vendor marketing. This plugin removes them from Dashboard. Plugin changelogs still exist on Plugins → each plugin.</td>
			</tr>
			<tr>
				<td>At a Glance <?php echo esc_html( (string) $brief['published_posts'] ); ?> posts / <?php echo esc_html( (string) $brief['published_pages'] ); ?> pages</td>
				<td>WordPress <?php echo esc_html( (string) $brief['wp_version'] ); ?> · <?php echo esc_html( (string) $brief['theme'] ); ?>. Counts are fine.</td>
			</tr>
		</tbody>
	</table>

	<p class="luxe-op-actions">
		<a class="button button-primary" href="<?php echo esc_url( $brief['stay_url'] ); ?>">Open Stay Repair</a>
		<a class="button" href="<?php echo esc_url( $brief['mission_url'] ); ?>">Open Mission Control</a>
		<a class="button" href="<?php echo esc_url( $brief['settings_url'] ); ?>">Operator settings</a>
		<button type="button" class="button" id="luxe-op-toggle-notices">Show hidden notices</button>
	</p>
	<p class="luxe-op-note">Reader-Love and Keyword Autopilot nags are hidden on this screen only. Those plugins are still running until you deactivate them. Site Health “No information yet” is WordPress waiting on a check — open <a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>">Site Health</a> once if you want the report.</p>
</div>
