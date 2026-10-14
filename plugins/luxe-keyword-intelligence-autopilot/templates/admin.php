<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total     = isset( $coverage['total'] ) ? (int) $coverage['total'] : 0;
$green     = isset( $coverage['green'] ) ? (int) $coverage['green'] : 0;
$never     = isset( $coverage['never'] ) ? (int) $coverage['never'] : 0;
$attention = isset( $coverage['attention'] ) ? (int) $coverage['attention'] : 0;
$scanned   = isset( $coverage['scanned'] ) ? (int) $coverage['scanned'] : 0;
$percent   = $total > 0 ? (int) floor( ( $scanned / $total ) * 100 ) : 0;
$interval  = max( 2, (int) $settings['interval'] );
$per_hour  = $interval > 0 ? (int) floor( ( 60 / $interval ) * (int) $batch ) : 0;
$scans     = isset( $state['scans'] ) ? (int) $state['scans'] : 0;
$repairs   = isset( $state['repairs'] ) ? (int) $state['repairs'] : 0;
$message   = isset( $state['message'] ) ? (string) $state['message'] : 'Waiting for the first batch.';
$checked   = function ( $key ) use ( $settings ) {
	return ! empty( $settings[ $key ] ) ? 'checked' : '';
};
?>
<div class="wrap luxe-kia">
	<header class="luxe-kia-hero">
		<p class="luxe-kia-kicker">LuxeTrendsetters · Keyword Intelligence Autopilot v<?php echo esc_html( LUXE_KIA_VERSION ); ?></p>
		<h1>24/7 engine</h1>
		<p><?php echo esc_html( $message ); ?></p>
		<p>Permanent safety rule: scan the eligible catalog, install natural keyword phrases, repair Rank Math metadata, and preserve the exact WordPress status. This plugin never publishes and never rewrites article bodies.</p>
	</header>

	<section class="luxe-kia-metrics">
		<article>
			<span>Full-site coverage</span>
			<strong><?php echo esc_html( $scanned . ' / ' . $total ); ?></strong>
			<em><?php echo esc_html( $percent . '% scanned; ' . $never . ' never scanned; ' . $green . ' keyword-green; ' . $attention . ' need attention.' ); ?></em>
		</article>
		<article>
			<span>Today</span>
			<strong><?php echo esc_html( $scans . ' / ' . $repairs ); ?></strong>
			<em><?php echo esc_html( 'Scans / repairs. Daily caps ' . (int) $settings['max_scans'] . ' and ' . (int) $settings['max_repairs'] . '.' ); ?></em>
		</article>
		<article>
			<span>Resource gate</span>
			<strong><?php echo $batch > 0 ? 'GREEN' : 'HOLD'; ?></strong>
			<em><?php echo esc_html( 'Load ' . number_format( (float) $load, 2 ) . '. This cycle repairs up to ' . (int) $batch . ' items, about ' . $per_hour . ' an hour. A burst repairs up to ' . (int) $burst . '.' ); ?></em>
		</article>
		<article>
			<span>Keyword phrase sets</span>
			<strong><?php echo esc_html( (string) (int) $settings['phrases'] ); ?> per item</strong>
			<em>One primary phrase plus natural long-tail phrases. The score reaches 95–100 only when the fields actually pass.</em>
		</article>
	</section>

	<div class="luxe-kia-grid">
		<section class="luxe-kia-card">
			<h2>Publishing · hard disabled</h2>
			<p>No workflow creates a published status or changes an existing WordPress status. Published article bodies and excerpts stay audit-only.</p>
			<h2>Taxonomy guard · active</h2>
			<p>item-weight and date-first-available are always merged into the noindex list. Product categories stay indexable.</p>
		</section>
		<section class="luxe-kia-card">
			<h2>Run</h2>
			<form method="post">
				<?php wp_nonce_field( 'luxe_kia_action' ); ?>
				<input type="hidden" name="luxe_kia_action" value="1" />
				<p>
					<button class="button button-primary" name="luxe_kia_burst" value="1">Repair attention items</button>
					<button class="button" name="luxe_kia_cycle" value="1">Run one cycle</button>
					<button class="button" name="luxe_kia_tax" value="1">Apply taxonomy noindex</button>
				</p>
				<p class="luxe-kia-inline">
					<label>Scan and repair a specific ID<br /><input type="number" name="luxe_kia_post" min="1" step="1" /></label>
					<button class="button" name="luxe_kia_id" value="1">Run ID</button>
				</p>
			</form>
		</section>
	</div>

	<form method="post" class="luxe-kia-card">
		<?php wp_nonce_field( 'luxe_kia_action' ); ?>
		<input type="hidden" name="luxe_kia_action" value="1" />
		<input type="hidden" name="luxe_kia_save" value="1" />
		<h2>Settings</h2>
		<label><input type="checkbox" name="enabled" value="1" <?php echo esc_attr( $checked( 'enabled' ) ); ?> /> Enable full-site 24/7 engine</label>
		<label>Schedule (minutes) <input type="number" name="interval" value="<?php echo esc_attr( (string) (int) $settings['interval'] ); ?>" min="2" max="60" /></label>
		<label>Batch size before the load gate <input type="number" name="batch" value="<?php echo esc_attr( (string) (int) $settings['batch'] ); ?>" min="1" max="20" /></label>
		<label>Maximum scans per UTC day <input type="number" name="max_scans" value="<?php echo esc_attr( (string) (int) $settings['max_scans'] ); ?>" min="24" max="5000" /></label>
		<label>Maximum repairs per UTC day <input type="number" name="max_repairs" value="<?php echo esc_attr( (string) (int) $settings['max_repairs'] ); ?>" min="12" max="2000" /></label>
		<label>Keyword phrases maintained per item <input type="number" name="phrases" value="<?php echo esc_attr( (string) (int) $settings['phrases'] ); ?>" min="3" max="5" /></label>
		<label>Green score threshold /100 <input type="number" name="green" value="<?php echo esc_attr( (string) (int) $settings['green'] ); ?>" min="95" max="100" /></label>
		<label>Minimum automatic confidence % <input type="number" name="confidence" value="<?php echo esc_attr( (string) (int) $settings['confidence'] ); ?>" min="60" max="95" /></label>
		<label>Attention cooldown hours after a miss <input type="number" name="cooldown" value="<?php echo esc_attr( (string) (int) $settings['cooldown'] ); ?>" min="1" max="48" /></label>
		<label>Seconds per cycle <input type="number" name="budget" value="<?php echo esc_attr( (string) (int) $settings['budget'] ); ?>" min="4" max="20" /></label>
		<label><input type="checkbox" name="types_post" value="1" <?php echo esc_attr( $checked( 'types_post' ) ); ?> /> Posts</label>
		<label><input type="checkbox" name="types_product" value="1" <?php echo esc_attr( $checked( 'types_product' ) ); ?> /> Products</label>
		<label><input type="checkbox" name="types_page" value="1" <?php echo esc_attr( $checked( 'types_page' ) ); ?> /> Pages</label>
		<label><input type="checkbox" name="scan_published" value="1" <?php echo esc_attr( $checked( 'scan_published' ) ); ?> /> Scan published content. Published posts remain published.</label>
		<label><input type="checkbox" name="scan_scheduled" value="1" <?php echo esc_attr( $checked( 'scan_scheduled' ) ); ?> /> Scan scheduled content. Scheduled content is scan-only.</label>
		<label><input type="checkbox" name="repair_meta" value="1" <?php echo esc_attr( $checked( 'repair_meta' ) ); ?> /> Repair Rank Math focus, title, and description</label>
		<label><input type="checkbox" name="repair_alt" value="1" <?php echo esc_attr( $checked( 'repair_alt' ) ); ?> /> Repair a broken featured-image alt text</label>
		<label>Extra low-value taxonomy slugs <input type="text" name="taxonomies" value="<?php echo esc_attr( (string) $settings['taxonomies'] ); ?>" /></label>
		<label>Protected post IDs <input type="text" name="protected" value="<?php echo esc_attr( (string) $settings['protected'] ); ?>" /></label>
		<p><button class="button button-primary" type="submit">Save settings</button></p>
	</form>

	<section class="luxe-kia-card">
		<h2>Recent activity</h2>
		<table class="luxe-kia-table">
			<thead>
				<tr><th>UTC</th><th>Item</th><th>Action</th><th>Score</th><th>Result</th><th>Message</th><th>Restore</th></tr>
			</thead>
			<tbody>
				<?php if ( ! $activity ) : ?>
					<tr><td colspan="7" class="luxe-kia-empty">No batches yet.</td></tr>
				<?php else : ?>
					<?php foreach ( $activity as $row ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', isset( $row['time'] ) ? (int) $row['time'] : time() ) ); ?></td>
							<td>#<?php echo esc_html( (string) (int) $row['id'] ); ?> <?php echo esc_html( isset( $row['status'] ) ? (string) $row['status'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['action'] ) ? (string) $row['action'] : '' ); ?></td>
							<td><?php echo esc_html( (int) $row['before'] . ' → ' . (int) $row['after'] ); ?></td>
							<td><?php echo esc_html( isset( $row['result'] ) ? (string) $row['result'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['message'] ) ? (string) $row['message'] : '' ); ?></td>
							<td>
								<form method="post">
									<?php wp_nonce_field( 'luxe_kia_action' ); ?>
									<input type="hidden" name="luxe_kia_action" value="1" />
									<input type="hidden" name="luxe_kia_post" value="<?php echo esc_attr( (string) (int) $row['id'] ); ?>" />
									<button class="button" name="luxe_kia_restore" value="1">Restore</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</section>
</div>
