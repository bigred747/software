<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$avg_r   = isset( $dash['avg_readiness'] ) ? (int) $dash['avg_readiness'] : 0;
$avg_i   = isset( $dash['avg_integrity'] ) ? (int) $dash['avg_integrity'] : 0;
$scanned = isset( $dash['scanned'] ) ? (int) $dash['scanned'] : 0;
$ready   = isset( $dash['ready'] ) ? (int) $dash['ready'] : 0;
$review  = isset( $dash['review'] ) ? (int) $dash['review'] : 0;
$held    = isset( $dash['held'] ) ? (int) $dash['held'] : 0;
$unknown = isset( $dash['unknown'] ) ? (int) $dash['unknown'] : 0;
$tax_c   = isset( $dash['taxonomy_conflicts'] ) ? (int) $dash['taxonomy_conflicts'] : 0;
$attr_c  = isset( $dash['attribute_conflicts'] ) ? (int) $dash['attribute_conflicts'] : 0;
$need    = isset( $dash['need_work'] ) ? (int) $dash['need_work'] : 0;
$at      = ! empty( $dash['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $dash['at'] ) : 'Not run yet';
$below   = isset( $dash['below'] ) && is_array( $dash['below'] ) ? $dash['below'] : array();
$changes = isset( $dash['changes'] ) && is_array( $dash['changes'] ) ? $dash['changes'] : array();
$integrity_ok = $avg_i >= 95 && $avg_r >= 95;
$learn     = isset( $learn ) && is_array( $learn ) ? $learn : array();
$learn_log = isset( $learn_log ) && is_array( $learn_log ) ? $learn_log : array();
$learn_next = ! empty( $learn_next ) ? (int) $learn_next : 0;
$learn_on  = ! empty( $settings['learning_24_7'] ) || ! empty( $settings['auto_repair'] );
$next_label = $learn_next ? wp_date( 'Y-m-d H:i:s', $learn_next ) : 'scheduling…';
$last_item = ! empty( $learn['id'] ) ? (string) (int) $learn['id'] : '—';
$last_ri   = ( isset( $learn['readiness'] ) ? (int) $learn['readiness'] : 0 ) . '/' . ( isset( $learn['integrity'] ) ? (int) $learn['integrity'] : 0 );
?>
<div class="wrap lsr-wrap laps-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · catalog integrity</p>
		<h1>Luxe Affiliate Product Scout <?php echo esc_html( LAPS_VERSION ); ?></h1>
		<p class="lsr-lead">24/7 catalog learning plus copyright-safe Instagram video scanning. Repair ALL still processes the full catalog. A 95–100 score requires supported brand evidence, ASIN, price, a primary image, and no hard risk flags. Instagram video is never downloaded or republished.</p>
	</header>

	<section class="laps-counters">
		<span>24/7 learning: <strong><?php echo $learn_on ? 'On' : 'Off'; ?></strong></span>
		<span>Next cycle: <strong><?php echo esc_html( $next_label ); ?></strong></span>
		<span>Last learned ID: <strong><?php echo esc_html( $last_item ); ?></strong> (<?php echo esc_html( $last_ri ); ?>)</span>
		<span>One item per cycle · status preserved</span>
	</section>

	<section class="lsr-scorebar <?php echo $integrity_ok ? 'laps-green' : 'laps-red'; ?>">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Catalog Readiness</p>
			<p class="lsr-big"><?php echo esc_html( (string) $avg_r ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Integrity</p>
			<p class="lsr-big"><?php echo esc_html( (string) $avg_i ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Homepage-ready</p>
			<p class="lsr-big"><?php echo esc_html( $ready . '/' . $scanned ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">REVIEW</p>
			<p class="lsr-big"><?php echo esc_html( (string) $review ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">HOLD</p>
			<p class="lsr-big"><?php echo esc_html( (string) $held ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last run</p>
			<p class="lsr-when"><?php echo esc_html( $at ); ?></p>
		</div>
	</section>

	<section class="laps-counters">
		<span>Unknown brands: <strong><?php echo esc_html( (string) $unknown ); ?></strong></span>
		<span>Taxonomy conflicts: <strong><?php echo esc_html( (string) $tax_c ); ?></strong></span>
		<span>Brand-attribute conflicts: <strong><?php echo esc_html( (string) $attr_c ); ?></strong></span>
		<span>Need catalog work: <strong><?php echo esc_html( (string) $need ); ?></strong></span>
	</section>

	<form method="post" class="lsr-learn-form laps-actions">
		<?php wp_nonce_field( 'laps_run' ); ?>
		<button type="submit" name="laps_repair_all" value="1" class="button button-primary lsr-save" data-laps-confirm="Repair ALL Catalog Data + Recalculate will fix brand/taxonomy leftovers, then rescore. Products with supported brand + ASIN + price + image score 95–100. It never publishes or rewrites Amazon URLs.">Repair ALL Catalog Data + Recalculate</button>
		<button type="submit" name="laps_audit_all" value="1" class="button lsr-save">Run Full Catalog Audit</button>
		<button type="submit" name="laps_repair_batch" value="1" class="button">Repair next batch</button>
		<button type="submit" name="laps_learn_one" value="1" class="button">Run 1 learning cycle</button>
		<button type="submit" name="laps_learn_snapshot" value="1" class="button">Run learning snapshot</button>
	</form>
	<p class="lsr-note">24/7 uses WP-Cron: one product every 5 minutes, then an hourly read-only snapshot of stored scores. Tiny batches never replace the full-catalog board. If Hostinger has a real cron job, point it at <code>wp-cron.php</code> every 5 minutes so learning continues without waiting for a visitor.</p>

	<section class="lsr-grid">
		<article class="lsr-card lsr-card-safe">
			<h2>Safety lock</h2>
			<ul>
				<li>Never publishes drafts</li>
				<li>Never changes post status</li>
				<li>Never deletes products</li>
				<li>Never rewrites Amazon or affiliate URLs</li>
				<li>Never invents ratings, seller, warranty, or unknown brands</li>
				<li>Never imports products or creates redirects</li>
				<li>Never copies Instagram video, audio, frames, or captions</li>
				<li>Renewed / refurbished / used stay HOLD</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>Truth gates for 95–100</h2>
			<ul>
				<li>Supported local brand evidence</li>
				<li>Traceable ASIN</li>
				<li>Real local price</li>
				<li>Primary product image</li>
				<li>No used / refurbished / for-parts hard risk</li>
				<li>Unknown brands stay below 95</li>
				<li>Taxonomy leftovers are repaired, not used as a 94 cap</li>
			</ul>
		</article>
	</section>
	<p class="lsr-note">Keyword Intelligence still owns ongoing Rank Math keyword/title/description work. Mission Control still owns evidence auditing. Product Scout owns deterministic product-integrity conflicts only. Upload <strong>Luxe SEO Score Repair</strong> for the public SEO green-signal board.</p>

	<?php
	$ig        = isset( $ig ) && is_array( $ig ) ? $ig : array();
	$ig_log    = isset( $ig_log ) && is_array( $ig_log ) ? $ig_log : array();
	$ig_board  = isset( $ig_board ) && is_array( $ig_board ) ? $ig_board : array();
	$ig_studio = isset( $ig_studio ) && is_array( $ig_studio ) ? $ig_studio : array();
	$ig_signals = isset( $ig_board['signals'] ) && is_array( $ig_board['signals'] ) ? $ig_board['signals'] : array();
	$ig_green   = isset( $ig_board['green'] ) ? (int) $ig_board['green'] : 0;
	$ig_total   = isset( $ig_board['total'] ) ? (int) $ig_board['total'] : 0;
	$ig_rows    = isset( $ig['rows'] ) && is_array( $ig['rows'] ) ? $ig['rows'] : array();
	$ig_on      = ! empty( $settings['ig_scan_24_7'] );
	?>
	<section class="lsr-scorebar laps-green">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Instagram scan</p>
			<p class="lsr-big"><?php echo esc_html( $ig_green . '/' . $ig_total ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Permalinks</p>
			<p class="lsr-big"><?php echo esc_html( isset( $ig['scanned'] ) ? (string) (int) $ig['scanned'] : '0' ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Videos recorded</p>
			<p class="lsr-big"><?php echo esc_html( isset( $ig['videos'] ) ? (string) (int) $ig['videos'] : '0' ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Catalog matches</p>
			<p class="lsr-big"><?php echo esc_html( isset( $ig['matches'] ) ? (string) (int) $ig['matches'] : '0' ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Video copied</p>
			<p class="lsr-big">0</p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">24/7 IG scan</p>
			<p class="lsr-when"><?php echo $ig_on ? 'On' : 'Off'; ?></p>
		</div>
	</section>

	<section class="lsr-signals">
		<h2>Instagram + copyright green signals</h2>
		<ul class="lsr-signal-list">
			<?php foreach ( $ig_signals as $signal ) : ?>
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

	<form method="post" class="laps-actions">
		<?php wp_nonce_field( 'laps_run' ); ?>
		<button type="submit" name="laps_ig_scan_all" value="1" class="button button-primary lsr-save">Scan all Instagram videos</button>
		<button type="submit" name="laps_ig_scan_one" value="1" class="button">Scan next permalink</button>
		<button type="submit" name="laps_ig_scan_site" value="1" class="button">Scan Instagram URLs already on this site</button>
	</form>
	<p class="lsr-note">Paste public reel/post URLs below. Product Scout records the permalink and matches your local catalog. It does <strong>not</strong> download Instagram video, copy captions, or publish anything.</p>

	<?php if ( $ig_rows ) : ?>
		<section class="lsr-card">
			<h2>Instagram scan results</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Type</th>
						<th>Permalink</th>
						<th>Status</th>
						<th>Brands</th>
						<th>Copied</th>
						<th>Why</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $ig_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $row['type'] ) ? $row['type'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['url'] ) ? $row['url'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['status'] ) ? $row['status'] : '' ); ?></td>
						<td><?php echo esc_html( ! empty( $row['brands'] ) ? implode( ', ', $row['brands'] ) : '' ); ?></td>
						<td>no</td>
						<td><?php echo esc_html( ! empty( $row['reasons'] ) ? implode( ', ', $row['reasons'] ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section class="lsr-card">
		<h2>Original Luxe reel studio</h2>
		<p class="lsr-note">These scripts are original LuxeTrendsetters copy for your own filming. They are not copied from Instagram creators.</p>
		<ol>
			<?php foreach ( $ig_studio as $script ) : ?>
				<li><strong><?php echo esc_html( isset( $script['label'] ) ? $script['label'] : '' ); ?>:</strong> <?php echo esc_html( isset( $script['script'] ) ? $script['script'] : '' ); ?></li>
			<?php endforeach; ?>
		</ol>
	</section>

	<?php if ( $below ) : ?>
		<section class="lsr-card">
			<h2>Products below 95</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Brand</th>
						<th>R</th>
						<th>I</th>
						<th>Status</th>
						<th>Product</th>
						<th>Why</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $below as $row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $row['id'] ) ? (string) $row['id'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['brand'] ) ? $row['brand'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['readiness'] ) ? (string) $row['readiness'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['integrity'] ) ? (string) $row['integrity'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['status'] ) ? $row['status'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['title'] ) ? $row['title'] : '' ); ?></td>
						<td><?php echo esc_html( ! empty( $row['reasons'] ) ? implode( ', ', $row['reasons'] ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( $changes ) : ?>
		<section class="lsr-card">
			<h2>Last repair details</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Brand</th>
						<th>Product</th>
						<th>What changed</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $changes as $row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $row['id'] ) ? (string) $row['id'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['brand'] ) ? $row['brand'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['title'] ) ? $row['title'] : '' ); ?></td>
						<td><?php echo esc_html( ! empty( $row['did'] ) ? implode( ', ', $row['did'] ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $log ) ) : ?>
		<section class="lsr-card">
			<h2>Audit log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Mode</th>
						<th>Scanned</th>
						<th>Repaired</th>
						<th>Ready</th>
						<th>R</th>
						<th>I</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( ! empty( $row['repair'] ) ? 'Repair' : 'Audit' ); ?></td>
						<td><?php echo esc_html( isset( $row['scanned'] ) ? $row['scanned'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['repaired'] ) ? $row['repaired'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['ready'] ) ? $row['ready'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['avg_readiness'] ) ? $row['avg_readiness'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['avg_integrity'] ) ? $row['avg_integrity'] : '0' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $learn_log ) ) : ?>
		<section class="lsr-card">
			<h2>24/7 learning log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Source</th>
						<th>ID</th>
						<th>Product</th>
						<th>R</th>
						<th>I</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $learn_log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['source'] ) ? $row['source'] : 'cron' ); ?></td>
						<td><?php echo esc_html( isset( $row['id'] ) ? (string) (int) $row['id'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['title'] ) ? $row['title'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['readiness'] ) ? (string) (int) $row['readiness'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['integrity'] ) ? (string) (int) $row['integrity'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['status'] ) ? $row['status'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<form method="post" class="lsr-form">
		<?php wp_nonce_field( 'laps_save' ); ?>
		<input type="hidden" name="laps_save" value="1" />
		<table class="widefat striped lsr-table">
			<tbody>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="learning_24_7" value="1" <?php checked( $learn_on ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>24/7 catalog learning</strong></td>
					<td>One product per 5-minute cycle, plus an hourly read-only snapshot of the full catalog. Never publishes. Tiny cycles do not replace the 233-product scoreboard.</td>
				</tr>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="related_filter" value="1" <?php checked( ! empty( $settings['related_filter'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>Related-product integrity</strong></td>
					<td>WooCommerce related products stay in the same bucket. Items below 95 are excluded.</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Batch size</strong></td>
					<td>
						<input type="number" name="batch_size" min="5" max="50" value="<?php echo esc_attr( (string) (int) $settings['batch_size'] ); ?>" />
					</td>
				</tr>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="ig_scan_24_7" value="1" <?php checked( ! empty( $settings['ig_scan_24_7'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>24/7 Instagram permalink scan</strong></td>
					<td>One queued Instagram permalink per 5-minute cycle. Never downloads video. Never publishes.</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Instagram reel URLs</strong></td>
					<td>
						<textarea name="ig_urls" rows="5" class="large-text" placeholder="https://www.instagram.com/reel/YOURSHORTCODE/"><?php echo esc_textarea( isset( $settings['ig_urls'] ) ? (string) $settings['ig_urls'] : '' ); ?></textarea>
						<p class="description">One public permalink per line. Profiles are recorded as REVIEW until you paste reel URLs.</p>
					</td>
				</tr>
				<tr>
					<td></td>
					<td><strong>Operator notes</strong></td>
					<td>
						<textarea name="ig_notes" rows="4" class="large-text" placeholder="Supported brands you saw, e.g. DJI Mini, Bose QuietComfort, Apple Watch"><?php echo esc_textarea( isset( $settings['ig_notes'] ) ? (string) $settings['ig_notes'] : '' ); ?></textarea>
						<p class="description">Your words only. The plugin never scrapes Instagram captions.</p>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary lsr-save">Save settings</button>
		</p>
	</form>
</div>
