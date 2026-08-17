<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$green = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total = isset( $last['total'] ) ? (int) $last['total'] : 0;
$band  = isset( $last['band'] ) ? (string) $last['band'] : '';
$score = isset( $last['score_10'] ) ? $last['score_10'] : '';
$at    = ! empty( $last['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $last['at'] ) : 'Not run yet';
$signals = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
$snap  = isset( $last['snap'] ) && is_array( $last['snap'] ) ? $last['snap'] : array();
$ver   = isset( $snap['version'] ) ? (string) $snap['version'] : 'unknown';
$all   = $total > 0 && $green === $total;
$learn_on = ! empty( $learn_on );
$learn_next = ! empty( $learn_next ) ? (int) $learn_next : 0;
$next_label = $learn_next ? wp_date( 'Y-m-d H:i:s', $learn_next ) : 'scheduling…';
$last_focus = ! empty( $last['focus_label'] ) ? (string) $last['focus_label'] : '—';
$last_source = ! empty( $last['source'] ) ? (string) $last['source'] : 'cron';
?>
<div class="wrap lsr-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · Flatsome auto-update</p>
		<h1>Luxe Theme Guard <?php echo esc_html( LUXE_THEME_GUARD_VERSION ); ?></h1>
		<p class="lsr-lead"><?php echo $learn_on ? '24/7 process learning is on' : '24/7 process learning is off'; ?>: one theme signal every 5 minutes, official Flatsome auto-update as soon as WordPress has a licensed package. Amazon AI lives on <strong>Luxe SEO</strong>, not this plugin. Never downloads nulled zips. Never overwrites plugins. Never publishes. Never rewrites Amazon URLs.</p>
	</header>

	<section class="laps-counters">
		<span>24/7 learning: <strong><?php echo $learn_on ? 'On' : 'Off'; ?></strong></span>
		<span>Next cycle: <strong><?php echo esc_html( $next_label ); ?></strong></span>
		<span>Last process: <strong><?php echo esc_html( $last_focus ); ?></strong> (<?php echo esc_html( $last_source ); ?>)</span>
		<span>One process per cycle · never publishes</span>
	</section>

	<section class="lsr-scorebar<?php echo $all ? '' : ' lsr-scorebar-warn'; ?>">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Verified processes</p>
			<p class="lsr-big"><?php echo esc_html( $green . ' / ' . $total ); ?> <span>green</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Process score</p>
			<p class="lsr-big"><?php echo esc_html( (string) $score ); ?><span> / 10</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Flatsome</p>
			<p class="lsr-big lsr-band"><?php echo esc_html( $ver ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last check</p>
			<p class="lsr-when"><?php echo esc_html( $at ); ?></p>
		</div>
	</section>

	<section class="lsr-signals">
		<h2>Green signals</h2>
		<ul class="lsr-signal-list">
			<?php foreach ( $signals as $signal ) : ?>
				<?php
				$level  = isset( $signal['level'] ) ? $signal['level'] : 'red';
				$label  = isset( $signal['label'] ) ? $signal['label'] : '';
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
		<form method="post" class="lsr-learn-form laps-actions">
			<?php wp_nonce_field( 'luxe_theme_guard_run' ); ?>
			<button type="submit" name="luxe_theme_guard_run" value="1" class="button button-primary lsr-save">Check and update Flatsome now</button>
			<button type="submit" name="luxe_theme_guard_learn_one" value="1" class="button lsr-save">Run 1 learning cycle</button>
		</form>
		<p class="lsr-note">24/7 uses WP-Cron: one theme process every 5 minutes. Amazon AI is on <strong>Luxe SEO</strong>, not this plugin. If Hostinger has a real cron job, point it at <code>wp-cron.php</code> every 5 minutes.</p>
	</section>

	<?php if ( ! empty( $log ) ) : ?>
		<section class="lsr-card">
			<h2>24/7 learning log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Source</th>
						<th>Process</th>
						<th>Green</th>
						<th>Band</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['source'] ) ? $row['source'] : 'cron' ); ?></td>
						<td><?php echo esc_html( isset( $row['focus'] ) ? $row['focus'] : '' ); ?></td>
						<td><?php echo esc_html( ( isset( $row['green'] ) ? $row['green'] : '0' ) . ' / ' . ( isset( $row['total'] ) ? $row['total'] : '0' ) ); ?></td>
						<td><?php echo esc_html( isset( $row['band'] ) ? $row['band'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="lsr-grid">
		<article class="lsr-card">
			<h2>Safety lock</h2>
			<ul>
				<li>Official Flatsome parent only</li>
				<li>Never downloads nulled / pirate zips</li>
				<li>Never overwrites Product Scout, Luxe SEO, Mission Control, or Stay Repair</li>
				<li>Never writes <code>post_content</code></li>
				<li>Never rewrites Amazon URLs</li>
				<li>Amazon AI lives on Luxe SEO</li>
				<li>Child theme is never replaced</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>If a signal is red</h2>
			<ul>
				<li>Parent-only is <strong>not</strong> a problem. You do not need a child theme unless you edited Flatsome files.</li>
				<li>Enter your ThemeForest purchase code on this page (or under Flatsome → Theme Registration)</li>
				<li>Save settings. Guard auto-updates official Flatsome every 5 minutes once WordPress has the package</li>
				<li>Purge LiteSpeed once after a successful update</li>
				<li>Do not install a nulled Flatsome zip</li>
				<li>Amazon AI is on the left menu <strong>Luxe SEO</strong></li>
			</ul>
		</article>
	</section>

	<form method="post" class="lsr-form">
		<?php wp_nonce_field( 'luxe_theme_guard_save' ); ?>
		<input type="hidden" name="luxe_theme_guard_save" value="1" />
		<table class="widefat striped lsr-table">
			<tbody>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="auto_update" value="1" <?php checked( ! empty( $settings['auto_update'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>Auto-update Flatsome</strong></td>
					<td>WordPress applies official licensed packages as soon as they appear. When Flatsome is behind, Guard refreshes every 5 minutes and upgrades automatically. Never a nulled zip.</td>
				</tr>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="process_learning" value="1" <?php checked( ! empty( $settings['process_learning'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>24/7 process learning</strong></td>
					<td>One signal every 5 minutes plus an hourly read-only package snapshot. Never publishes. Never rewrites Amazon URLs. Never upgrades on a shopper page.</td>
				</tr>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="auto_purge" value="1" <?php checked( ! empty( $settings['auto_purge'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>Purge cache after update</strong></td>
					<td>LiteSpeed + object cache. Does not call Hostinger’s CDN API.</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>ThemeForest purchase code</strong></td>
					<td>
						<input type="password" name="flatsome_purchase_code" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo ! empty( $settings['flatsome_purchase_code'] ) ? 'saved — paste to replace' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'; ?>" />
						<p class="description">ThemeForest → Downloads → License certificate &amp; purchase code. Guard writes it to Flatsome’s official updater and auto-installs 3.20.9+. Leave blank to keep a saved code.</p>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary lsr-save">Save settings</button>
		</p>
	</form>
</div>
