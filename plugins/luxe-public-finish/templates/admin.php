<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checks = array(
	'polish_titles'    => array(
		'label' => 'Complete document and Open Graph titles',
		'help'  => 'Fixes clipped tabs such as “| Pr”, “Review & Buyer Che”, and “Review 2026: Best Buyer”. Always syncs og:title to the tab title. Caps at 70 characters on a word. Never writes post_title.',
	),
	'unique_listings'  => array(
		'label' => 'Unique product listing labels',
		'help'  => 'If two cards share a title, the second gets · SKU or · #ID in HTML only. Never writes the catalog.',
	),
	'disclosure_once'  => array(
		'label' => 'One Amazon Associate identification',
		'help'  => 'Official header + article sentences count as one. Extra “we earn from qualifying purchases” blocks are removed from the public HTML only.',
	),
	'schema_org_once'  => array(
		'label' => 'One Organization JSON-LD graph',
		'help'  => 'Keeps the first valid Organization node. Drops later duplicates. Does not invent brands or ratings.',
	),
	'buffer_html'      => array(
		'label' => 'Final HTML pass',
		'help'  => 'Outer buffer so polish runs after Rank Math and Luxe SEO. Adds zero frontend JavaScript or CSS.',
	),
	'process_learning' => array(
		'label' => '24/7 process learning',
		'help'  => 'Every 15 minutes: verify each green signal, purge LiteSpeed, never publish, never rewrite Amazon URLs.',
	),
	'auto_purge'       => array(
		'label' => 'Automatic cache purge',
		'help'  => 'Calls LiteSpeed purge_all and wp_cache_flush after a learning cycle. Does not call Hostinger’s CDN API.',
	),
);

$green    = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total    = isset( $last['total'] ) ? (int) $last['total'] : 0;
$yellow   = isset( $last['yellow'] ) ? (int) $last['yellow'] : 0;
$score    = isset( $last['score_10'] ) ? (int) $last['score_10'] : 10;
$band     = isset( $last['band'] ) ? (string) $last['band'] : 'armed';
$when     = ! empty( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : 'Not run yet — click Run finish learning now';
$warn     = ( $yellow > 0 || ( isset( $last['red'] ) && (int) $last['red'] > 0 ) );
?>
<div class="wrap lpf-wrap">
	<div class="lpf-hero">
		<p class="lpf-kicker">LuxeTrendsetters · public finish</p>
		<h1><?php esc_html_e( 'Luxe Finish', 'luxe-public-finish' ); ?></h1>
		<p class="lpf-lead">Last-pass polish for a smoother public storefront. Complete titles, unique listing labels, one Amazon identification, one Organization schema. Adds <strong>zero</strong> frontend JavaScript. Never writes posts. Never rewrites Amazon URLs.</p>
	</div>

	<div class="lpf-scorebar<?php echo $warn ? ' lpf-scorebar-warn' : ''; ?>">
		<div>
			<p class="lpf-kicker-dark">Score</p>
			<p class="lpf-big"><?php echo esc_html( (string) $score ); ?><span>/10</span></p>
		</div>
		<div>
			<p class="lpf-kicker-dark">Band</p>
			<p class="lpf-big lpf-band"><?php echo esc_html( $band ); ?></p>
		</div>
		<div>
			<p class="lpf-kicker-dark">Green</p>
			<p class="lpf-big"><?php echo esc_html( $green . ' / ' . $total ); ?></p>
		</div>
		<div>
			<p class="lpf-kicker-dark">Last learn</p>
			<p class="lpf-when"><?php echo esc_html( $when ); ?></p>
		</div>
	</div>

	<div class="lpf-grid">
		<div class="lpf-card">
			<h2><?php esc_html_e( 'What this plugin fixes', 'luxe-public-finish' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Clipped browser tabs: “Seiko … | Pr”, “MacBook … Review & Buyer Che”, “Review 2026: Best Buyer”.', 'luxe-public-finish' ); ?></li>
				<li><?php esc_html_e( 'Duplicate related-product labels — second card gets SKU, not a copied title.', 'luxe-public-finish' ); ?></li>
				<li><?php esc_html_e( 'Stacked Amazon Associate sentences and duplicate Organization JSON-LD.', 'luxe-public-finish' ); ?></li>
			</ul>
			<p class="lpf-note"><?php esc_html_e( 'Leave Luxe SEO 1.4.2, Luxe Theme Guard, Mission Control, Stay Repair, Keyword Autopilot, Reader-Love, Hard Rescue, and Product Scout activated. Upload this zip into a new folder named luxe-public-finish. Never overwrite those plugins.', 'luxe-public-finish' ); ?></p>
		</div>
		<div class="lpf-card">
			<h2><?php esc_html_e( 'Safety lock', 'luxe-public-finish' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Never publish, never delete, never write post_content or post_title.', 'luxe-public-finish' ); ?></li>
				<li><?php esc_html_e( 'Never rewrite Amazon URLs. Tag stays luxetrendse0f-20.', 'luxe-public-finish' ); ?></li>
				<li><?php esc_html_e( 'Never copy Instagram video. Never invent Rank Math redirects.', 'luxe-public-finish' ); ?></li>
				<li><?php esc_html_e( 'Never add public JS/CSS — Flatsome already loads enough scripts.', 'luxe-public-finish' ); ?></li>
			</ul>
		</div>
	</div>

	<form method="post" class="lpf-card">
		<?php wp_nonce_field( 'luxe_public_finish_save' ); ?>
		<h2><?php esc_html_e( 'Processes', 'luxe-public-finish' ); ?></h2>
		<table class="widefat striped lpf-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'On', 'luxe-public-finish' ); ?></th>
					<th><?php esc_html_e( 'Process', 'luxe-public-finish' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $checks as $key => $row ) : ?>
				<tr>
					<td>
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
					</td>
					<td>
						<strong><?php echo esc_html( $row['label'] ); ?></strong>
						<p class="lpf-note"><?php echo esc_html( $row['help'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="submit" name="luxe_public_finish_save" value="1" class="button button-primary lpf-save"><?php esc_html_e( 'Save finish settings', 'luxe-public-finish' ); ?></button>
		</p>
	</form>

	<div class="lpf-card lpf-signals">
		<h2><?php esc_html_e( 'Green signals', 'luxe-public-finish' ); ?></h2>
		<form method="post" class="lpf-learn-form">
			<?php wp_nonce_field( 'luxe_public_finish_learn' ); ?>
			<p>
				<button type="submit" name="luxe_public_finish_learn_now" value="1" class="button button-primary lpf-save"><?php esc_html_e( 'Run finish learning now', 'luxe-public-finish' ); ?></button>
			</p>
		</form>
		<ul class="lpf-signal-list">
			<?php
			$items = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
			foreach ( $items as $signal ) :
				$level = isset( $signal['level'] ) ? $signal['level'] : 'green';
				$class = 'lpf-signal';
				if ( 'yellow' === $level ) {
					$class .= ' lpf-signal-yellow';
				} elseif ( 'red' === $level ) {
					$class .= ' lpf-signal-red';
				}
				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<span class="lpf-dot"></span>
					<span>
						<strong><?php echo esc_html( isset( $signal['label'] ) ? $signal['label'] : '' ); ?></strong>
						<em><?php echo esc_html( isset( $signal['detail'] ) ? $signal['detail'] : '' ); ?></em>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $log ) && is_array( $log ) ) : ?>
			<p class="lpf-note">
				<?php
				$recent = array_slice( $log, 0, 6 );
				$bits   = array();
				foreach ( $recent as $row ) {
					$bits[] = ( isset( $row['green'] ) ? (int) $row['green'] : 0 ) . '/' . ( isset( $row['total'] ) ? (int) $row['total'] : 0 );
				}
				echo esc_html( 'Recent cycles: ' . implode( ' · ', $bits ) );
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
