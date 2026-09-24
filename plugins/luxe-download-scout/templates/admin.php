<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$downloads = ( is_array( $result ) && isset( $result['downloads'] ) ) ? $result['downloads'] : array();
$skips     = ( is_array( $result ) && isset( $result['skips'] ) ) ? $result['skips'] : array();
$parsed    = ( is_array( $result ) && isset( $result['parsed'] ) ) ? (int) $result['parsed'] : 0;
$compared  = ( is_array( $result ) && isset( $result['catalog_compared'] ) ) ? (int) $result['catalog_compared'] : 0;
?>
<div class="wrap lds-wrap">
	<header class="lds-hero">
		<p class="lds-kicker">LuxeTrendsetters · catalog integrity</p>
		<h1>Luxe Download Scout <?php echo esc_html( LDS_VERSION ); ?></h1>
		<p class="lds-lead">Paste a WZone result page. This screen lists the products that can reach 95–100: supported brand, real price, and no renewed, used, or unknown-brand hold. It does not import, publish, or rewrite Amazon URLs.</p>
	</header>

	<?php if ( ! $catalog_ready ) : ?>
		<div class="notice notice-error"><p>Install and activate Luxe Affiliate Product Scout 5.6.8 first. Download Scout uses that supported-brand list and does not invent brands.</p></div>
	<?php endif; ?>
	<?php if ( $notice ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<section class="lds-grid">
		<article class="lds-card">
			<h2>Download only when</h2>
			<ul>
				<li>The title leads with a supported brand</li>
				<li>The row has a real price</li>
				<li>The WZone card shows an ASIN and a primary image</li>
				<li>It is not renewed, refurbished, used, or for-parts</li>
				<li>It is not an accessory such as a landing pad or case</li>
				<li>That model is not already imported or already in the catalog</li>
			</ul>
		</article>
		<article class="lds-card">
			<h2>Safety lock</h2>
			<ul>
				<li>Never imports products</li>
				<li>Never publishes drafts</li>
				<li>Never changes post status</li>
				<li>Never deletes products</li>
				<li>Never rewrites Amazon or affiliate URLs</li>
				<li>Never invents ratings, seller, warranty, or unknown brands</li>
			</ul>
		</article>
	</section>
	<p class="lds-note">Product Scout still owns catalog repair and 24/7 scoring. This screen only tells you which new rows are worth adding. Unknown brands stay off the list because they would join the HOLD board.</p>
	<p class="lds-note">Live catalog products read for this check: <strong><?php echo esc_html( (string) (int) $live_count ); ?></strong>. Already Imported rows in the paste count as already stored. Extra titles below are included too.</p>

	<form method="post" class="lds-card">
		<?php wp_nonce_field( 'lds_pick' ); ?>
		<h2>WZone paste</h2>
		<p class="lds-note">Copy the product list from WZone, including the price and the “Add to import list” or “Already Imported” line.</p>
		<textarea name="lds_paste" rows="14" class="large-text code" placeholder="Product title&#10;$49999&#10;Add to import list"><?php echo esc_textarea( $paste ); ?></textarea>
		<h2>Extra catalog titles</h2>
		<p class="lds-note">Optional. One stored product name per line. Use this when a HOLD product is missing from the live read.</p>
		<textarea name="lds_catalog" rows="5" class="large-text code"><?php echo esc_textarea( $extra ); ?></textarea>
		<p><button type="submit" name="lds_pick" value="1" class="button button-primary lds-save">Show products to download</button></p>
	</form>

	<?php if ( is_array( $result ) ) : ?>
		<section class="lds-scorebar">
			<div>
				<p class="lds-kicker lds-kicker-dark">Download</p>
				<p class="lds-big"><?php echo esc_html( (string) count( $downloads ) ); ?></p>
			</div>
			<div>
				<p class="lds-kicker lds-kicker-dark">Skip</p>
				<p class="lds-big"><?php echo esc_html( (string) count( $skips ) ); ?></p>
			</div>
			<div>
				<p class="lds-kicker lds-kicker-dark">Rows read</p>
				<p class="lds-big"><?php echo esc_html( (string) $parsed ); ?></p>
			</div>
			<div>
				<p class="lds-kicker lds-kicker-dark">Catalog compared</p>
				<p class="lds-big"><?php echo esc_html( (string) $compared ); ?></p>
			</div>
		</section>

		<section class="lds-card">
			<h2>Download these</h2>
			<?php if ( ! $downloads ) : ?>
				<p>No row on this paste can clear the 95–100 gates.</p>
			<?php else : ?>
				<ol class="lds-copy">
					<?php foreach ( $downloads as $row ) : ?>
						<li><?php echo esc_html( $row['title'] ); ?></li>
					<?php endforeach; ?>
				</ol>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Brand</th>
							<th>Can reach</th>
							<th>Price</th>
							<th>Product</th>
							<th>Why</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $downloads as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['brand'] ); ?></td>
								<td><?php echo esc_html( (string) (int) $row['integrity'] ); ?></td>
								<td><?php echo esc_html( $row['price_label'] ); ?></td>
								<td><?php echo esc_html( $row['title'] ); ?></td>
								<td><?php echo esc_html( $row['why'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="lds-card">
			<h2>Leave these</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Reason</th>
						<th>Brand</th>
						<th>Price</th>
						<th>Product</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $skips as $row ) : ?>
						<tr>
							<td><?php echo esc_html( LDS_Picker::reason_label( $row['reason'] ) ); ?></td>
							<td><?php echo esc_html( $row['brand'] ); ?></td>
							<td><?php echo esc_html( $row['price_label'] ); ?></td>
							<td><?php echo esc_html( $row['title'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
