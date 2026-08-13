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
?>
<div class="wrap lsr-wrap laps-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · catalog integrity</p>
		<h1>Luxe Affiliate Product Scout <?php echo esc_html( LAPS_VERSION ); ?></h1>
		<p class="lsr-lead">Repair ALL Catalog Data + Recalculate, then Run Full Catalog Audit. A 95–100 score requires supported brand evidence, ASIN, price, a primary image, and no hard risk flags. Missing ratings, seller, or warranty data never lowers an otherwise valid product. Taxonomy leftovers are repaired and no longer cap those products below 95.</p>
	</header>

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
	</form>

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
	<p class="lsr-note">Keyword Intelligence still owns ongoing Rank Math keyword/title/description work. Mission Control still owns evidence auditing. Product Scout owns deterministic product-integrity conflicts only.</p>

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

	<form method="post" class="lsr-form">
		<?php wp_nonce_field( 'laps_save' ); ?>
		<input type="hidden" name="laps_save" value="1" />
		<table class="widefat striped lsr-table">
			<tbody>
				<tr>
					<td>
						<label class="lsr-switch">
							<input type="checkbox" name="auto_repair" value="1" <?php checked( ! empty( $settings['auto_repair'] ) ); ?> />
							<span>On</span>
						</label>
					</td>
					<td><strong>6-hour automatic conflict repair</strong></td>
					<td>Heals remaining taxonomy/brand conflicts in small batches, then rescores. Does not publish.</td>
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
			</tbody>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary lsr-save">Save settings</button>
		</p>
	</form>
</div>
