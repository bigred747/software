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
$amazon     = isset( $amazon ) && is_array( $amazon ) ? $amazon : array();
$amazon_log = isset( $amazon_log ) && is_array( $amazon_log ) ? $amazon_log : array();
$amazon_on  = ! empty( $amazon_on );
?>
<div class="wrap lsr-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · Flatsome auto-update</p>
		<h1>Luxe Theme Guard <?php echo esc_html( LUXE_THEME_GUARD_VERSION ); ?></h1>
		<p class="lsr-lead"><?php echo $learn_on ? '24/7 process learning is on' : '24/7 process learning is off'; ?>: one theme signal and one Amazon catalog item every 5 minutes, Amazon AI audit-only, official Flatsome upgrade at most once per 12 hours. Never downloads nulled zips. Never overwrites plugins. Never publishes. Never rewrites Amazon URLs.</p>
	</header>

	<section class="laps-counters">
		<span>24/7 learning: <strong><?php echo $learn_on ? 'On' : 'Off'; ?></strong></span>
		<span>Next cycle: <strong><?php echo esc_html( $next_label ); ?></strong></span>
		<span>Last process: <strong><?php echo esc_html( $last_focus ); ?></strong> (<?php echo esc_html( $last_source ); ?>)</span>
		<span>One process per cycle · never publishes</span>
	</section>

	<section id="luxe-amazon-ai" class="lsr-scorebar<?php echo ( ! empty( $amazon['green'] ) && isset( $amazon['total'] ) && (int) $amazon['green'] === (int) $amazon['total'] ) ? '' : ' lsr-scorebar-warn'; ?>">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Amazon AI</p>
			<p class="lsr-big"><?php echo esc_html( ( isset( $amazon['green'] ) ? (string) (int) $amazon['green'] : '0' ) . ' / ' . ( isset( $amazon['total'] ) ? (string) (int) $amazon['total'] : '0' ) ); ?> <span>green</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Process score</p>
			<p class="lsr-big"><?php echo esc_html( isset( $amazon['score_10'] ) ? (string) $amazon['score_10'] : '0' ); ?><span> / 10</span></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last ASIN</p>
			<p class="lsr-big lsr-band"><?php echo esc_html( ! empty( $amazon['asin'] ) ? (string) $amazon['asin'] : '—' ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Mode</p>
			<p class="lsr-when"><?php echo esc_html( ! empty( $amazon_on ) ? ( ( ! empty( $amazon['api_mode'] ) ? (string) $amazon['api_mode'] : 'local' ) . ' · On' ) : 'Off' ); ?></p>
		</div>
	</section>
	<?php
	$amazon_signals = isset( $amazon['signals'] ) && is_array( $amazon['signals'] ) ? $amazon['signals'] : array();
	if ( $amazon_signals ) :
		?>
	<section class="lsr-signals">
		<h2>Amazon AI signals</h2>
		<ul class="lsr-signal-list">
			<?php foreach ( $amazon_signals as $signal ) : ?>
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
	</section>
	<?php endif; ?>

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
			<button type="submit" name="luxe_theme_guard_amazon_one" value="1" class="button lsr-save">Run 1 Amazon AI cycle</button>
		</form>
		<p class="lsr-note">24/7 uses WP-Cron: one theme process and one Amazon catalog item every 5 minutes. Amazon AI is audit-only: official Creators API if you paste credentials, otherwise local ASIN/tag checks. It never rewrites Amazon URLs, never invents ratings, and never publishes. If Hostinger has a real cron job, point it at <code>wp-cron.php</code> every 5 minutes.</p>
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

	<?php if ( ! empty( $amazon_log ) ) : ?>
		<section class="lsr-card">
			<h2>Amazon AI log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Source</th>
						<th>ASIN</th>
						<th>Mode</th>
						<th>Green</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $amazon_log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['source'] ) ? $row['source'] : 'cron' ); ?></td>
						<td><?php echo esc_html( isset( $row['asin'] ) ? $row['asin'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['mode'] ) ? $row['mode'] : 'local' ); ?></td>
						<td><?php echo esc_html( ( isset( $row['green'] ) ? $row['green'] : '0' ) . ' / ' . ( isset( $row['total'] ) ? $row['total'] : '0' ) ); ?></td>
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
				<li>Amazon AI never invents ratings</li>
				<li>Child theme is never replaced</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>If a signal is red</h2>
			<ul>
				<li>Parent-only is <strong>not</strong> a problem. You do not need a child theme unless you edited Flatsome files.</li>
				<li>Open <strong>Flatsome → Theme Registration</strong></li>
				<li>Enter your ThemeForest purchase code from ThemeForest → Downloads</li>
				<li>Click <strong>Check and update Flatsome now</strong> so WordPress can fetch official <strong>3.20.9</strong></li>
				<li>Purge LiteSpeed once after a successful update</li>
				<li>Do not install a nulled Flatsome zip</li>
				<li>Amazon AI: optional Creators API credentials from Associates Central → Tools → Creators API. PA-API v5 is retired.</li>
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
					<td>WordPress applies official licensed packages. 24/7 learning tries an upgrade at most once per 12 hours.</td>
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
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="amazon_ai" value="1" <?php checked( ! empty( $settings['amazon_ai'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>Amazon AI</strong></td>
					<td>One catalog item per 5-minute cycle. Local ASIN/tag audit. Optional official Creators API. Never rewrites URLs. Never publishes.</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Associates tag</strong></td>
					<td>
						<input type="text" name="amazon_tag" class="regular-text" value="<?php echo esc_attr( isset( $settings['amazon_tag'] ) ? (string) $settings['amazon_tag'] : 'luxetrendse0f-20' ); ?>" />
					</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Creators API Credential ID</strong></td>
					<td>
						<input type="text" name="amazon_client_id" class="regular-text" autocomplete="off" value="<?php echo esc_attr( isset( $settings['amazon_client_id'] ) ? (string) $settings['amazon_client_id'] : '' ); ?>" />
						<p class="description">Associates Central → Tools → Creators API. Leave blank for local audit only.</p>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Creators API Credential Secret</strong></td>
					<td>
						<input type="password" name="amazon_client_secret" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo ! empty( $settings['amazon_client_secret'] ) ? 'saved — paste to replace' : ''; ?>" />
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary lsr-save">Save settings</button>
		</p>
	</form>
</div>
