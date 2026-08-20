<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checks = array(
	'keep_all_items'   => array(
		'label' => 'Keep every blog item',
		'help'  => 'Keep Live 23, Safe Draft 26, Duplicate Hold 1, Mismatch Hold 197, Approval Ready 4. Never deletes. Never publishes.',
	),
	'protect_master'   => array(
		'label' => 'Protect master #10833',
		'help'  => 'Apple 2023 MacBook Pro M2 Pro guide stays read-only. This board never writes that post.',
	),
	'block_product_0'  => array(
		'label' => 'Block Product #0 fake-green',
		'help'  => 'Mismatch holds cannot score 95–100 until 2.8.1 has a locked WooCommerce product. Manual re-link only.',
	),
	'never_publish'    => array(
		'label' => 'Hard no-publish',
		'help'  => 'Approval Ready stays a draft/pending/private stamp. WordPress status never becomes publish from this plugin.',
	),
	'menu_safe'        => array(
		'label' => 'Flatsome menu safe',
		'help'  => 'Public DOM hiders stay OFF. Hamburger / off-canvas is never touched. Homepage overlay CSS/JS is never injected.',
	),
	'public_scan'      => array(
		'label' => 'Live public scan',
		'help'  => 'Homepage, /blogs/, /shop/, master #10833, and a published watch guide. Report only.',
	),
	'process_learning' => array(
		'label' => '24/7 process learning',
		'help'  => 'Every 15 minutes: verify each keep-process and live gates. Never writes posts.',
	),
	'auto_purge'       => array(
		'label' => 'Automatic cache purge',
		'help'  => 'LiteSpeed purge_all and wp_cache_flush after a learning cycle.',
	),
);

$green  = isset( $last['green'] ) ? (int) $last['green'] : 0;
$total  = isset( $last['total'] ) ? (int) $last['total'] : 0;
$yellow = isset( $last['yellow'] ) ? (int) $last['yellow'] : 0;
$score  = isset( $last['score_10'] ) ? (int) $last['score_10'] : 10;
$band   = isset( $last['band'] ) ? (string) $last['band'] : 'armed';
$when   = ! empty( $last['at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $last['at'] ) . ' UTC' : 'Not run yet — click Run blog green learning now';
$warn   = ( $yellow > 0 || ( isset( $last['red'] ) && (int) $last['red'] > 0 ) );
$master = Luxe_BMG_Plugin::master_active();
?>
<div class="wrap lbg-wrap">
	<div class="lbg-hero">
		<p class="lbg-kicker">LuxeTrendsetters · blog master 2.9.0</p>
		<h1><?php esc_html_e( 'Luxe Blog Green', 'luxe-blog-master-green' ); ?></h1>
		<p class="lbg-lead">Process-health layer for <strong>Luxe Blog Master Command Center v2.8.1</strong>. Keep 2.8.1 activated for SCAN + BUILD. This board keeps every post, live-verifies published guides including master #10833, and gives each process its own green signal. Never publishes. Never deletes. Never fake-greens Product #0.</p>
	</div>

	<div class="lbg-scorebar<?php echo $warn ? ' lbg-scorebar-warn' : ''; ?>">
		<div>
			<p class="lbg-kicker-dark">Score</p>
			<p class="lbg-big"><?php echo esc_html( (string) $score ); ?><span>/10</span></p>
		</div>
		<div>
			<p class="lbg-kicker-dark">Band</p>
			<p class="lbg-big lbg-band"><?php echo esc_html( $band ); ?></p>
		</div>
		<div>
			<p class="lbg-kicker-dark">Green</p>
			<p class="lbg-big"><?php echo esc_html( $green . ' / ' . $total ); ?></p>
		</div>
		<div>
			<p class="lbg-kicker-dark">Last learn</p>
			<p class="lbg-when"><?php echo esc_html( $when ); ?></p>
		</div>
	</div>

	<div class="lbg-grid">
		<div class="lbg-card">
			<h2><?php esc_html_e( 'Keep 2.8.1 — do not replace it', 'luxe-blog-master-green' ); ?></h2>
			<ul>
				<li><?php echo $master ? esc_html__( 'Blog Master 2.8.1 detected as active.', 'luxe-blog-master-green' ) : esc_html__( 'Leave Blog Master Command Center v2.8.1 activated. This zip is a new folder: luxe-blog-master-green.', 'luxe-blog-master-green' ); ?></li>
				<li><?php esc_html_e( 'All 25 original buttons stay in 2.8.1. SCAN + BUILD, 95–100 repair, Approval Ready, category panels, uniqueness, depth, Amazon resolver.', 'luxe-blog-master-green' ); ?></li>
				<li><?php esc_html_e( 'Keep Live 23 · Safe Draft 26 · Duplicate Hold #11892 · Mismatch Hold 197 · Approval Ready 4.', 'luxe-blog-master-green' ); ?></li>
			</ul>
			<p class="lbg-note"><?php esc_html_e( 'Upload this zip over nothing else. Do not replace Blog Master, Reader-Love, Luxe SEO, Theme Guard, Finish, Mission Control, Stay Repair, Keyword Autopilot, Hard Rescue, or Product Scout.', 'luxe-blog-master-green' ); ?></p>
		</div>
		<div class="lbg-card">
			<h2><?php esc_html_e( 'Safety lock', 'luxe-blog-master-green' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Never publish, never delete, never write post_content or post_title.', 'luxe-blog-master-green' ); ?></li>
				<li><?php esc_html_e( 'Master #10833 is read-only. Published bodies stay protected.', 'luxe-blog-master-green' ); ?></li>
				<li><?php esc_html_e( 'Product #0 stays blocked until a real WooCommerce product is locked in 2.8.1.', 'luxe-blog-master-green' ); ?></li>
				<li><?php esc_html_e( 'Amazon tag stays luxetrendse0f-20. URLs are never rewritten. Flatsome hamburger is never touched.', 'luxe-blog-master-green' ); ?></li>
			</ul>
		</div>
	</div>

	<form method="post" class="lbg-card">
		<?php wp_nonce_field( 'luxe_bmg_save' ); ?>
		<h2><?php esc_html_e( 'Processes', 'luxe-blog-master-green' ); ?></h2>
		<table class="widefat striped lbg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'On', 'luxe-blog-master-green' ); ?></th>
					<th><?php esc_html_e( 'Process', 'luxe-blog-master-green' ); ?></th>
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
						<p class="lbg-note"><?php echo esc_html( $row['help'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="submit" name="luxe_bmg_save" value="1" class="button button-primary lbg-save"><?php esc_html_e( 'Save blog green settings', 'luxe-blog-master-green' ); ?></button>
		</p>
	</form>

	<div class="lbg-card lbg-signals">
		<h2><?php esc_html_e( 'Green signals — every keep process', 'luxe-blog-master-green' ); ?></h2>
		<form method="post" class="lbg-learn-form">
			<?php wp_nonce_field( 'luxe_bmg_learn' ); ?>
			<p>
				<button type="submit" name="luxe_bmg_learn_now" value="1" class="button button-primary lbg-save"><?php esc_html_e( 'Run blog green learning now', 'luxe-blog-master-green' ); ?></button>
			</p>
		</form>
		<ul class="lbg-signal-list">
			<?php
			$items = isset( $last['signals'] ) && is_array( $last['signals'] ) ? $last['signals'] : array();
			foreach ( $items as $signal ) :
				$level = isset( $signal['level'] ) ? $signal['level'] : 'green';
				$class = 'lbg-signal';
				if ( 'yellow' === $level ) {
					$class .= ' lbg-signal-yellow';
				} elseif ( 'red' === $level ) {
					$class .= ' lbg-signal-red';
				}
				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<span class="lbg-dot"></span>
					<span>
						<strong><?php echo esc_html( isset( $signal['label'] ) ? $signal['label'] : '' ); ?></strong>
						<em><?php echo esc_html( isset( $signal['detail'] ) ? $signal['detail'] : '' ); ?></em>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $log ) && is_array( $log ) ) : ?>
			<p class="lbg-note">
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
