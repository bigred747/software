<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contract_signals = isset( $contract['signals'] ) && is_array( $contract['signals'] ) ? $contract['signals'] : array();
$live_signals     = isset( $live['signals'] ) && is_array( $live['signals'] ) ? $live['signals'] : array();
$scanned_at       = ! empty( $live['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $live['at'] ) : 'Not scanned yet';
$token            = isset( $settings['verification'] ) ? (string) $settings['verification'] : '';
?>
<div class="wrap lgp-wrap">
	<header class="lgp-hero">
		<p class="lgp-kicker">LuxeTrendsetters · computer and mobile</p>
		<h1>Luxe Google Presence <?php echo esc_html( LUXE_GOOGLE_PRESENCE_VERSION ); ?></h1>
		<p class="lgp-lead">Google can read one page on a computer and on a phone. Shoppers compare guides here, then use the Amazon button already on the page. This plugin does not buy traffic, invent rankings, or send people to Amazon without a click.</p>
	</header>

	<section class="lgp-scorebar">
		<div>
			<p class="lgp-kicker lgp-kicker-dark">Plugin contract</p>
			<p class="lgp-big"><?php echo esc_html( (int) $contract['green'] . ' / ' . (int) $contract['total'] ); ?> <span>green</span></p>
		</div>
		<div>
			<p class="lgp-kicker lgp-kicker-dark">Contract score</p>
			<p class="lgp-big"><?php echo esc_html( (string) $contract['score'] ); ?><span> / 100</span></p>
		</div>
		<div>
			<p class="lgp-kicker lgp-kicker-dark">Live readiness</p>
			<p class="lgp-big"><?php echo esc_html( isset( $live['score'] ) ? (string) $live['score'] : '—' ); ?><span><?php echo isset( $live['score'] ) ? ' / 100' : ''; ?></span></p>
		</div>
		<div>
			<p class="lgp-kicker lgp-kicker-dark">Last live scan</p>
			<p class="lgp-when"><?php echo esc_html( $scanned_at ); ?></p>
		</div>
	</section>

	<form method="post" class="lgp-scan-form">
		<?php wp_nonce_field( 'luxe_google_presence_scan' ); ?>
		<input type="hidden" name="luxe_google_presence_scan" value="1" />
		<button type="submit" class="button button-primary lgp-save">Scan homepage on computer and mobile</button>
	</form>

	<div class="lgp-grid">
		<section class="lgp-card">
			<h2>What raises real visits</h2>
			<ul>
				<li>Submit <code>https://luxetrendsetters.com/sitemap_index.xml</code> in Google Search Console. Rank Math already publishes it.</li>
				<li>Paste the Search Console HTML token below so Google can match the site to your account.</li>
				<li>Keep people on buyer guides and category pages. The compare block links to those pages before the Amazon button.</li>
			</ul>
			<p class="lgp-note">A plugin cannot put a site in the top three results. Google decides that from the query, the page, and other sites. Bought clicks and automatic Amazon jumps are against Amazon Associates rules, so this plugin does not do them.</p>
		</section>
		<section class="lgp-card">
			<h2>Plugins left in charge</h2>
			<?php if ( $siblings ) : ?>
				<ul>
					<?php foreach ( $siblings as $label => $state ) : ?>
						<li><?php echo esc_html( $label . ' — ' . $state ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="lgp-note">Sibling plugins are not loaded in this request. Their files are still not overwritten.</p>
			<?php endif; ?>
			<p class="lgp-note">Score Repair keeps titles, robots.txt, schema, and the homepage disclosure. Product Scout keeps scores. Product Integrity keeps taxonomy repairs. Rank Math keeps the sitemap.</p>
		</section>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'luxe_google_presence_save' ); ?>
		<input type="hidden" name="luxe_google_presence_save" value="1" />
		<table class="widefat striped lgp-table">
			<tbody>
				<tr>
					<th scope="row">Computer and mobile CSS</th>
					<td><label><input type="checkbox" name="device_css" value="1" <?php checked( ! empty( $settings['device_css'] ) ); ?> /> Shared stylesheet, viewport only if the theme omitted it</label></td>
				</tr>
				<tr>
					<th scope="row">rel=sponsored on Amazon buttons</th>
					<td><label><input type="checkbox" name="qualify_links" value="1" <?php checked( ! empty( $settings['qualify_links'] ) ); ?> /> Adds Google’s paid-link attribute. The href and associate tag stay the same</label></td>
				</tr>
				<tr>
					<th scope="row">On-site compare block</th>
					<td><label><input type="checkbox" name="onsite_compare" value="1" <?php checked( ! empty( $settings['onsite_compare'] ) ); ?> /> Guides on this site first, then the existing Amazon button</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="lgp-verification">Search Console token</label></th>
					<td>
						<input id="lgp-verification" class="large-text" type="text" name="verification" value="<?php echo esc_attr( $token ); ?>" placeholder="Paste the token or the meta tag" />
						<p class="lgp-note">Optional. Empty is valid. A token with HTML in it is ignored.</p>
					</td>
				</tr>
			</tbody>
		</table>
		<p><button type="submit" class="button button-primary lgp-save">Save settings</button></p>
	</form>

	<section class="lgp-signals">
		<h2>Plugin contract</h2>
		<ul class="lgp-signal-list">
			<?php foreach ( $contract_signals as $signal ) : ?>
				<?php $red = empty( $signal['level'] ) || 'green' !== $signal['level']; ?>
				<li class="lgp-signal<?php echo $red ? ' lgp-signal-red' : ''; ?>">
					<span class="lgp-dot" aria-hidden="true"></span>
					<div>
						<strong><?php echo esc_html( strtoupper( $red ? 'red' : 'green' ) . ' · ' . $signal['label'] ); ?></strong>
						<em><?php echo esc_html( isset( $signal['detail'] ) ? $signal['detail'] : '' ); ?></em>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php if ( $live_signals ) : ?>
		<section class="lgp-signals">
			<h2>Live homepage</h2>
			<ul class="lgp-signal-list">
				<?php foreach ( $live_signals as $signal ) : ?>
					<?php $red = empty( $signal['level'] ) || 'green' !== $signal['level']; ?>
					<li class="lgp-signal<?php echo $red ? ' lgp-signal-red' : ''; ?>">
						<span class="lgp-dot" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( strtoupper( $red ? 'red' : 'green' ) . ' · ' . $signal['label'] ); ?></strong>
							<em><?php echo esc_html( isset( $signal['detail'] ) ? $signal['detail'] : '' ); ?></em>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="lgp-note">95 means the required crawl, device, and disclosure checks passed. 100 means those checks passed and the page also shows the official Associate sentence and the full Amazon price disclaimer this plugin prints. The two official lines turn green after this plugin is active on a public page.</p>
		</section>
	<?php endif; ?>
</div>
