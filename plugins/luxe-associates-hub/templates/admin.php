<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tracking_id = isset( $settings['tracking_id'] ) ? (string) $settings['tracking_id'] : '';
$payment_on  = ! empty( $settings['payment_done'] );
$started_at  = isset( $settings['started_at'] ) ? (int) $settings['started_at'] : 0;
$note        = isset( $settings['earnings_note'] ) ? (string) $settings['earnings_note'] : '';
$last_url    = isset( $last['url'] ) ? (string) $last['url'] : '';
$last_asin   = isset( $last['asin'] ) ? (string) $last['asin'] : '';
$week_open   = ! empty( $week['open'] );
$week_shared = ! empty( $week['shared'] );
$ends        = $started_at > 0 ? wp_date( 'M j, Y', $started_at + 604800 ) : '';
?>
<div class="wrap lah-wrap">
	<header class="lah-hero">
		<p class="lah-kicker">LuxeTrendsetters · Luxe master set</p>
		<h1>Associates Hub <?php echo esc_html( LAH_VERSION ); ?></h1>
		<p class="lah-lead">The starter guide from Associates Central, wired to Product Scout, Score Repair, and Product Integrity Repair. Payment, tax, and your Amazon password stay on Amazon.</p>
	</header>

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<nav class="lah-nav" aria-label="Starter guide">
		<a href="#steps">Steps</a>
		<a href="#links">Build a link</a>
		<a href="#suite">Master set</a>
		<?php foreach ( $sections as $section ) : ?>
			<a href="#<?php echo esc_attr( $section['id'] ); ?>"><?php echo esc_html( $section['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<section class="lah-scorebar" id="steps">
		<div>
			<p class="lah-kicker lah-kicker-dark">Step 1 · Payment and tax</p>
			<p class="lah-big"><?php echo $payment_on ? 'Marked done' : 'Still open'; ?></p>
		</div>
		<div>
			<p class="lah-kicker lah-kicker-dark">Step 2 · First link</p>
			<p class="lah-big"><?php echo $week_shared ? 'Logged' : 'Not logged'; ?></p>
		</div>
		<div>
			<p class="lah-kicker lah-kicker-dark">Tracking ID</p>
			<p class="lah-big lah-band"><?php echo '' !== $tracking_id ? esc_html( $tracking_id ) : 'Not saved'; ?></p>
		</div>
		<div>
			<p class="lah-kicker lah-kicker-dark">First week</p>
			<p class="lah-big"><?php echo $week_open ? 'Open' : 'Closed'; ?></p>
			<?php if ( '' !== $ends ) : ?>
				<p class="lah-when">Window stored by this plugin ends <?php echo esc_html( $ends ); ?>.</p>
			<?php endif; ?>
		</div>
	</section>

	<div class="lah-grid">
		<?php foreach ( $steps as $step ) : ?>
			<section class="lah-card">
				<p class="lah-kicker lah-kicker-dark"><?php echo esc_html( $step['label'] ); ?></p>
				<h2><?php echo esc_html( $step['title'] ); ?></h2>
				<ul>
					<?php foreach ( $step['points'] as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="lah-links">
					<?php foreach ( $step['links'] as $link ) : ?>
						<a class="button" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
				</p>
			</section>
		<?php endforeach; ?>
	</div>

	<section class="lah-card" id="settings">
		<h2>Your tracking ID</h2>
		<p class="lah-note">Copy the tracking ID from Associates Central. It looks like <code>storename-20</code>. Leave payment and tax fields on Amazon’s site.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=luxe-associates-hub' ) ); ?>">
			<?php wp_nonce_field( 'lah_save' ); ?>
			<input type="hidden" name="lah_action" value="settings" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lah-tracking">Tracking ID</label></th>
					<td><input id="lah-tracking" name="tracking_id" type="text" class="regular-text" value="<?php echo esc_attr( $tracking_id ); ?>" autocomplete="off" spellcheck="false" /></td>
				</tr>
				<tr>
					<th scope="row">Payment and tax</th>
					<td>
						<label>
							<input type="checkbox" name="payment_done" value="1" <?php checked( $payment_on ); ?> />
							I already submitted payment and tax info on Amazon
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lah-note">Earnings reminder</label></th>
					<td>
						<textarea id="lah-note" name="earnings_note" rows="3" class="large-text" maxlength="500"><?php echo esc_textarea( $note ); ?></textarea>
						<p class="lah-note">Optional. Example: “Check the March report in April.” Do not type a password, bank number, or tax ID.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Associates settings' ); ?>
		</form>
	</section>

	<section class="lah-card" id="links">
		<h2>Build a product link</h2>
		<p class="lah-note">The link is a direct <code>https://www.amazon.com/dp/ASIN?tag=your-id</code> address. It is not cloaked, and it does not change the product in WooCommerce. In a post, use <code>[luxe_amazon asin="B0ABCDEF12"]</code>. Prime, Music, and Audible bounty links still have to be copied from SiteStripe so Amazon attaches the bounty.</p>
		<ul>
			<li>Amazon requires the tagged Special Link and this sentence next to it: “As an Amazon Associate I earn from qualifying purchases.”</li>
			<li>Google requires <code>rel="sponsored"</code> on affiliate links. The block also uses <code>noopener</code> and <code>nofollow</code>. The tracking ID stays in the URL, which is what Amazon uses to credit the sale.</li>
			<li>The block does not name a price, does not say Amazon endorses this site, and does not use an Amazon logo.</li>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=luxe-associates-hub' ) ); ?>">
			<?php wp_nonce_field( 'lah_save' ); ?>
			<input type="hidden" name="lah_action" value="link" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lah-asin">ASIN or product URL</label></th>
					<td><input id="lah-asin" name="asin" type="text" class="regular-text" value="<?php echo esc_attr( $prefill ); ?>" placeholder="B0ABCDEF12" spellcheck="false" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lah-title">Product name for your log</label></th>
					<td><input id="lah-title" name="link_title" type="text" class="large-text" value="<?php echo esc_attr( $title_in ); ?>" maxlength="180" /></td>
				</tr>
				<tr>
					<th scope="row">First-week log</th>
					<td>
						<label>
							<input type="checkbox" name="log_share" value="1" checked="checked" />
							Add this link to the log on this site
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Build tagged link' ); ?>
		</form>

		<?php
		$block_html = '';
		if ( '' !== $tracking_id && '' !== $last_asin ) {
			$block_label = isset( $last['title'] ) ? (string) $last['title'] : '';
			$block_html  = LAH_Links::block_html( $last_asin, $tracking_id, $block_label );
		}
		?>
		<?php if ( '' !== $block_html ) : ?>
			<div class="lah-result">
				<p class="lah-kicker lah-kicker-dark">Compliant block to paste, or use the shortcode</p>
				<textarea id="lah-block" class="large-text" rows="6" readonly="readonly"><?php echo esc_textarea( $block_html ); ?></textarea>
				<p>
					<button type="button" class="button button-primary" data-lah-copy="lah-block">Copy compliant block</button>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $last_url && LAH_Links::allowed_url( $last_url ) ) : ?>
			<div class="lah-result">
				<p class="lah-kicker lah-kicker-dark">Latest link<?php echo '' !== $last_asin ? ' · ' . esc_html( $last_asin ) : ''; ?></p>
				<textarea id="lah-link" class="large-text" rows="2" readonly="readonly"><?php echo esc_textarea( $last_url ); ?></textarea>
				<p>
					<button type="button" class="button button-primary" data-lah-copy="lah-link">Copy link</button>
				</p>
			</div>
		<?php endif; ?>

		<div class="lah-result">
			<p class="lah-kicker lah-kicker-dark">Disclosure to place near the link</p>
			<textarea id="lah-disclosure" class="large-text" rows="2" readonly="readonly"><?php echo esc_textarea( LAH_Links::DISCLOSURE ); ?></textarea>
			<p>
				<button type="button" class="button" data-lah-copy="lah-disclosure">Copy disclosure</button>
			</p>
			<p class="lah-note">Score Repair’s homepage line is separate: <?php echo esc_html( LAH_Links::SITE_DISCLOSURE ); ?></p>
		</div>
	</section>

	<section class="lah-card" id="catalog">
		<h2>Catalog ASINs from the master set</h2>
		<?php if ( empty( $catalog ) ) : ?>
			<p class="lah-note">No ASINs were read. Activate WooCommerce and Product Scout, or paste an ASIN above. Nothing was written to the catalog.</p>
		<?php else : ?>
			<table class="widefat striped lah-table">
				<thead>
					<tr>
						<th>Product</th>
						<th>ASIN</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catalog as $row ) : ?>
						<?php
						$use = add_query_arg(
							array(
								'page'      => 'luxe-associates-hub',
								'lah_asin'  => $row['asin'],
								'lah_title' => $row['title'],
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td><code><?php echo esc_html( $row['asin'] ); ?></code></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
							<td><a href="<?php echo esc_url( $use ); ?>#links">Use this ASIN</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $log ) ) : ?>
		<section class="lah-card" id="log">
			<h2>Links you logged</h2>
			<table class="widefat striped lah-table">
				<thead>
					<tr>
						<th>When</th>
						<th>Product</th>
						<th>Link</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $row ) : ?>
						<?php
						if ( ! is_array( $row ) ) {
							continue;
						}
						$row_url = isset( $row['url'] ) ? (string) $row['url'] : '';
						if ( ! LAH_Links::allowed_url( $row_url ) ) {
							continue;
						}
						$when = ! empty( $row['at'] ) ? wp_date( 'M j, Y g:i a', (int) $row['at'] ) : '';
						?>
						<tr>
							<td><?php echo esc_html( $when ); ?></td>
							<td><?php echo esc_html( isset( $row['title'] ) ? (string) $row['title'] : '' ); ?></td>
							<td><a href="<?php echo esc_url( $row_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row_url ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<section id="suite">
		<h2>Luxe master set</h2>
		<ul class="lah-signals">
			<?php foreach ( $signals as $signal ) : ?>
				<li class="lah-signal<?php echo 'bad' === $signal['tone'] ? ' lah-signal-bad' : ''; ?>">
					<span class="lah-dot" aria-hidden="true"></span>
					<div>
						<strong><?php echo esc_html( $signal['title'] ); ?></strong>
						<em><?php echo esc_html( $signal['detail'] ); ?></em>
						<?php if ( '' !== $signal['url'] && '' !== $signal['action'] ) : ?>
							<a href="<?php echo esc_url( $signal['url'] ); ?>"><?php echo esc_html( $signal['action'] ); ?></a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<div class="lah-grid">
		<?php foreach ( $sections as $section ) : ?>
			<section class="lah-card" id="<?php echo esc_attr( $section['id'] ); ?>">
				<p class="lah-kicker lah-kicker-dark"><?php echo esc_html( $section['label'] ); ?></p>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
				<ul>
					<?php foreach ( $section['points'] as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="lah-links">
					<?php foreach ( $section['links'] as $link ) : ?>
						<a class="button" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
					<?php if ( 'trending' === $section['id'] && '' !== $bestsellers ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $bestsellers ); ?>" target="_blank" rel="noopener noreferrer">Best Sellers with your tag</a>
					<?php endif; ?>
				</p>
			</section>
		<?php endforeach; ?>
	</div>
</div>
