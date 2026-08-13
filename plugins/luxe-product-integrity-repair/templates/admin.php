<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scanned  = isset( $last['scanned'] ) ? (int) $last['scanned'] : 0;
$repaired = isset( $last['repaired'] ) ? (int) $last['repaired'] : 0;
$held     = isset( $last['held'] ) ? (int) $last['held'] : 0;
$clean    = isset( $last['clean'] ) ? (int) $last['clean'] : 0;
$cursor   = isset( $last['cursor'] ) ? (int) $last['cursor'] : 0;
$at       = ! empty( $last['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $last['at'] ) : 'Not run yet';
$changes  = isset( $last['changes'] ) && is_array( $last['changes'] ) ? $last['changes'] : array();
?>
<div class="wrap lsr-wrap pir-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · Product Scout companion</p>
		<h1>Luxe Product Integrity Repair <?php echo esc_html( LUXE_PIR_VERSION ); ?></h1>
		<p class="lsr-lead">Version 1.1.0 repairs the live catalog conflicts that keep Product Scout at 94, then flushes WooCommerce/LiteSpeed so the next Full Catalog Audit can score supported products 95–100. This plugin does not write fake 100s.</p>
	</header>

	<section class="lsr-scorebar">
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last scanned</p>
			<p class="lsr-big"><?php echo esc_html( (string) $scanned ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Repaired</p>
			<p class="lsr-big"><?php echo esc_html( (string) $repaired ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Held</p>
			<p class="lsr-big"><?php echo esc_html( (string) $held ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Already clean</p>
			<p class="lsr-big"><?php echo esc_html( (string) $clean ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Cursor</p>
			<p class="lsr-big"><?php echo esc_html( (string) $cursor ); ?></p>
		</div>
		<div>
			<p class="lsr-kicker lsr-kicker-dark">Last run</p>
			<p class="lsr-when"><?php echo esc_html( $at ); ?></p>
		</div>
	</section>

	<form method="post" class="lsr-learn-form">
		<?php wp_nonce_field( 'luxe_pir_run' ); ?>
		<button type="submit" name="luxe_pir_run_now" value="1" class="button button-primary lsr-save">Repair next batch</button>
		<button type="submit" name="luxe_pir_run_all" value="1" class="button lsr-save">Repair ALL catalog conflicts</button>
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
				<li>Renewed / refurbished / used stay HOLD</li>
			</ul>
		</article>
		<article class="lsr-card">
			<h2>What it repairs</h2>
			<ul>
				<li>Samsung watches out of <code>Apple Watches</code></li>
				<li>Non-Apple accessories out of <code>MacBooks</code></li>
				<li>Brand + Manufacturer attributes aligned to the title brand</li>
				<li>Stale manufacturer tags that disagree with the real brand</li>
				<li>6-hour bounded batches while Auto repair is on</li>
			</ul>
		</article>
	</section>
	<p class="lsr-note">After repair, Product Scout can honestly score supported products 95–100 (brand + ASIN + price + image, no hard risk, no taxonomy conflict). Unknown-brand and renewed items stay below 95 on purpose.</p>

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
			<h2>Repair log</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Scanned</th>
						<th>Repaired</th>
						<th>Held</th>
						<th>Clean</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $row['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $row['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['scanned'] ) ? $row['scanned'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['repaired'] ) ? $row['repaired'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['held'] ) ? $row['held'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $row['clean'] ) ? $row['clean'] : '0' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<form method="post" class="lsr-form">
		<?php wp_nonce_field( 'luxe_pir_save' ); ?>
		<input type="hidden" name="luxe_pir_save" value="1" />
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
					<td>Heals remaining taxonomy/brand conflicts in small batches. Does not publish.</td>
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
