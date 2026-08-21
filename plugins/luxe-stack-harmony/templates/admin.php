<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checks = array(
	'cancel_duplicates' => array(
		'label' => 'Cancel duplicate PARK engines',
		'help'  => 'Deactivates Hostinger AI, Bad Content Eraser, Master Plugin Orchestrator, and a second Blog Master (Green Board). Never deletes. Never touches the keep-set.',
	),
	'mute_park_hooks'   => array(
		'label' => 'Mute PARK writers this request',
		'help'  => 'If Hostinger AI already loaded, drop its content filters for this page load. Finish still owns the public HTML last pass.',
	),
	'process_learning'  => array(
		'label' => '24/7 process learning',
		'help'  => 'Every 15 minutes: re-cancel PARK duplicates, verify keep-set, one Blog Master engine, Amazon tag, hamburger, purge LiteSpeed.',
	),
	'auto_purge'        => array(
		'label' => 'Automatic cache purge',
		'help'  => 'Calls LiteSpeed purge_all and wp_cache_flush after a learning cycle. Does not call Hostinger’s CDN API.',
	),
);

$green  = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total  = isset( $last['total'] ) ? (int) $last['total'] : 0;
$yellow = isset( $last['yellow'] ) ? (int) $last['yellow'] : 0;
$score  = isset( $last['score_10'] ) ? (int) $last['score_10'] : 10;
$band   = isset( $last['band'] ) ? (string) $last['band'] : 'armed';
$when   = ! empty( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : 'Not run yet — click Run harmony learning now';
$warn   = ( $yellow > 0 || ( isset( $last['red'] ) && (int) $last['red'] > 0 ) );
?>
<div class="wrap lsh-wrap">
	<div class="lsh-hero">
		<p class="lsh-kicker">LuxeTrendsetters · stack conductor</p>
		<h1><?php esc_html_e( 'Luxe Harmony', 'luxe-stack-harmony' ); ?></h1>
		<p class="lsh-lead">One conductor so the live plugins work together. KEEP stays on. PARK duplicates are cancelled, never deleted. Adds <strong>zero</strong> frontend JavaScript. Never publishes. Never rewrites Amazon URLs. Never touches the Flatsome hamburger.</p>
	</div>

	<div class="lsh-scorebar<?php echo $warn ? ' lsh-scorebar-warn' : ''; ?>">
		<div>
			<p class="lsh-kicker-dark">Score</p>
			<p class="lsh-big"><?php echo esc_html( (string) $score ); ?><span>/10</span></p>
		</div>
		<div>
			<p class="lsh-kicker-dark">Band</p>
			<p class="lsh-big lsh-band"><?php echo esc_html( $band ); ?></p>
		</div>
		<div>
			<p class="lsh-kicker-dark">Green</p>
			<p class="lsh-big"><?php echo esc_html( $green . ' / ' . $total ); ?></p>
		</div>
		<div>
			<p class="lsh-kicker-dark">Last learn</p>
			<p class="lsh-when"><?php echo esc_html( $when ); ?></p>
		</div>
	</div>

	<div class="lsh-grid">
		<div class="lsh-card">
			<h2><?php esc_html_e( 'Who does what', 'luxe-stack-harmony' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Command Center 2.9.4 — the only Blog Master repair engine. Replace 2.8.1; do not run a second copy.', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Finish 1.0.1 — last public HTML pass (titles, og:title, official Associate line).', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Rank Math + Luxe SEO + Keyword Autopilot — SEO. Amazon Bridge — the orange View on Amazon button.', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Theme Guard + Mirror Master + Mobile Recovery — Flatsome hamburger. Harmony never hides it.', 'luxe-stack-harmony' ); ?></li>
			</ul>
			<p class="lsh-note"><?php esc_html_e( 'Upload this zip into a new folder named luxe-stack-harmony. Never overwrite Command Center, Finish, Luxe SEO, Theme Guard, Mission Control, Stay Repair, Reader-Love, Hard Rescue, Product Scout, Amazon Bridge, or Mirror Master.', 'luxe-stack-harmony' ); ?></p>
		</div>
		<div class="lsh-card">
			<h2><?php esc_html_e( 'Safety lock', 'luxe-stack-harmony' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Never publish, never delete, never write post_content or post_title.', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Never rewrite Amazon URLs. Tag stays luxetrendse0f-20.', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Never deactivate KEEP plugins. PARK is deactivate-only.', 'luxe-stack-harmony' ); ?></li>
				<li><?php esc_html_e( 'Never add public JS/CSS. Finish already polishes HTML.', 'luxe-stack-harmony' ); ?></li>
			</ul>
		</div>
	</div>

	<form method="post" class="lsh-card">
		<?php wp_nonce_field( 'luxe_stack_harmony_save' ); ?>
		<h2><?php esc_html_e( 'Processes', 'luxe-stack-harmony' ); ?></h2>
		<table class="widefat striped lsh-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'On', 'luxe-stack-harmony' ); ?></th>
					<th><?php esc_html_e( 'Process', 'luxe-stack-harmony' ); ?></th>
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
						<p class="lsh-note"><?php echo esc_html( $row['help'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="submit" name="luxe_stack_harmony_save" value="1" class="button button-primary lsh-save"><?php esc_html_e( 'Save harmony settings', 'luxe-stack-harmony' ); ?></button>
		</p>
	</form>

	<div class="lsh-card lsh-signals">
		<h2><?php esc_html_e( 'Green signals', 'luxe-stack-harmony' ); ?></h2>
		<form method="post" class="lsh-learn-form">
			<?php wp_nonce_field( 'luxe_stack_harmony_learn' ); ?>
			<p>
				<button type="submit" name="luxe_stack_harmony_learn_now" value="1" class="button button-primary lsh-save"><?php esc_html_e( 'Run harmony learning now', 'luxe-stack-harmony' ); ?></button>
			</p>
		</form>
		<ul class="lsh-signal-list">
			<?php
			$items = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
			foreach ( $items as $signal ) :
				$level = isset( $signal['level'] ) ? $signal['level'] : 'green';
				$class = 'lsh-signal';
				if ( 'yellow' === $level ) {
					$class .= ' lsh-signal-yellow';
				} elseif ( 'red' === $level ) {
					$class .= ' lsh-signal-red';
				}
				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<span class="lsh-dot"></span>
					<span>
						<strong><?php echo esc_html( isset( $signal['label'] ) ? $signal['label'] : '' ); ?></strong>
						<em><?php echo esc_html( isset( $signal['detail'] ) ? $signal['detail'] : '' ); ?></em>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $log ) && is_array( $log ) ) : ?>
			<h3><?php esc_html_e( 'Recent cycles', 'luxe-stack-harmony' ); ?></h3>
			<ul class="lsh-log">
				<?php foreach ( array_slice( $log, 0, 8 ) as $row ) : ?>
					<li>
						<code><?php echo esc_html( ! empty( $row['at'] ) ? gmdate( 'Y-m-d H:i', (int) $row['at'] ) . ' UTC' : '' ); ?></code>
						<?php echo esc_html( ( isset( $row['green'] ) ? $row['green'] : '?' ) . '/' . ( isset( $row['total'] ) ? $row['total'] : '?' ) . ' · ' . ( isset( $row['band'] ) ? $row['band'] : '' ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( ! empty( $cancels ) && is_array( $cancels ) ) : ?>
			<h3><?php esc_html_e( 'Cancelled this week (deactivated, not deleted)', 'luxe-stack-harmony' ); ?></h3>
			<ul class="lsh-log">
				<?php foreach ( array_slice( $cancels, 0, 8 ) as $row ) : ?>
					<li>
						<code><?php echo esc_html( ! empty( $row['at'] ) ? gmdate( 'Y-m-d H:i', (int) $row['at'] ) . ' UTC' : '' ); ?></code>
						<?php echo esc_html( implode( ', ', isset( $row['names'] ) ? (array) $row['names'] : array() ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
