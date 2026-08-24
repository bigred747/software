<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ok_flag = isset( $_GET['bmc_ok'] ) ? (string) $_GET['bmc_ok'] : '';
$msg     = isset( $_GET['bmc_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['bmc_msg'] ) ) : '';
$score   = isset( $settings['score'] ) ? (int) $settings['score'] : 100;
$score10 = isset( $settings['score_10'] ) ? (int) $settings['score_10'] : 10;
$band    = isset( $settings['band'] ) ? (string) $settings['band'] : '95-100-ready';
$keep    = isset( $audit['keep_count'] ) ? (int) $audit['keep_count'] : 0;
$drafts  = isset( $audit['draft_count'] ) ? (int) $audit['draft_count'] : 0;
$dups    = isset( $audit['dup_count'] ) ? (int) $audit['dup_count'] : 0;
$holds   = isset( $audit['mismatch_count'] ) ? (int) $audit['mismatch_count'] : 0;
$ready   = isset( $audit['ready_count'] ) ? (int) $audit['ready_count'] : 0;
$when    = ! empty( $settings['last_learning'] ) ? $settings['last_learning'] : 'Not run yet';
$companion = isset( $companion ) && is_array( $companion ) ? $companion : array();
$stack     = isset( $stack ) && is_array( $stack ) ? $stack : array();

if ( ! function_exists( 'luxe_bmc_action_form' ) ) {
	/**
	 * @param string $action Action.
	 * @param string $label  Label.
	 * @param string $class  Class.
	 * @param array  $extra  Extra hidden fields.
	 */
	function luxe_bmc_action_form( $action, $label, $class = 'button', $extra = array() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="luxe-bmc-inline">';
		wp_nonce_field( 'luxe_bmc_action', 'luxe_bmc_nonce' );
		echo '<input type="hidden" name="action" value="luxe_bmc_action" />';
		echo '<input type="hidden" name="bmc_action" value="' . esc_attr( $action ) . '" />';
		foreach ( $extra as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '" />';
		}
		echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}
}
?>
<div class="wrap luxe-bmc-wrap">
	<div class="luxe-bmc-hero">
		<p class="luxe-bmc-kicker">LuxeTrendsetters · Command Center v<?php echo esc_html( LUXE_BMC_VERSION ); ?></p>
		<h1><?php esc_html_e( 'ADAPTIVE UNIQUENESS + AUTO DEPTH MASTER SCANNER', 'luxe-blog-master-command-center' ); ?></h1>
		<p class="luxe-bmc-lead">Full preservation automation. Scan → Repair drafts → Rescan → Reader-Love independent verification → APPROVAL READY. Keeps every blog. Protects master #10833. Never publishes. Never deletes. Never fake-greens Product #0. Never rewrites Amazon URLs. Never touches the Flatsome hamburger.</p>
	</div>

	<?php settings_errors( 'luxe_bmc' ); ?>
	<?php if ( '' !== $msg ) : ?>
		<div class="notice <?php echo '1' === $ok_flag ? 'notice-success' : 'notice-warning'; ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
	<?php endif; ?>

	<div class="luxe-bmc-scorebar">
		<div>
			<p class="luxe-bmc-kicker-dark">Process</p>
			<p class="luxe-bmc-big"><?php echo esc_html( (string) $score10 ); ?><span>/10</span></p>
		</div>
		<div>
			<p class="luxe-bmc-kicker-dark">Band</p>
			<p class="luxe-bmc-big luxe-bmc-band"><?php echo esc_html( $band ); ?></p>
		</div>
		<div>
			<p class="luxe-bmc-kicker-dark">Score</p>
			<p class="luxe-bmc-big"><?php echo esc_html( (string) $score ); ?><span>/100</span></p>
		</div>
		<div>
			<p class="luxe-bmc-kicker-dark">Last learn</p>
			<p class="luxe-bmc-when"><?php echo esc_html( $when ); ?></p>
		</div>
	</div>

	<div class="luxe-bmc-counts">
		<div><strong><?php echo esc_html( (string) $keep ); ?></strong><span>Keep Live</span></div>
		<div><strong><?php echo esc_html( (string) $drafts ); ?></strong><span>Safe Draft</span></div>
		<div><strong><?php echo esc_html( (string) $dups ); ?></strong><span>Duplicate Hold</span></div>
		<div><strong><?php echo esc_html( (string) $holds ); ?></strong><span>Mismatch Hold</span></div>
		<div><strong><?php echo esc_html( (string) $ready ); ?></strong><span>Approval Ready</span></div>
		<div><strong><?php echo esc_html( (string) ( $holds + $dups ) ); ?></strong><span>Needs review</span></div>
	</div>

	<div class="luxe-bmc-card luxe-bmc-scanbox">
		<h2><?php esc_html_e( 'SCAN + BUILD 95–100 MASTER LOCK', 'luxe-blog-master-command-center' ); ?></h2>
		<p class="luxe-bmc-note">One heavy blog per request. Backup before write. Drafts only. Published bodies including master #10833 stay untouched. Product #0 cannot pass.</p>
		<?php luxe_bmc_action_form( 'scan_build', 'SCAN + BUILD 95–100 MASTER LOCK', 'button button-primary luxe-bmc-save' ); ?>
	</div>

	<div class="luxe-bmc-actions">
		<?php
		$buttons = Luxe_BMC_Plugin::action_buttons();
		unset( $buttons['scan_build'], $buttons['cat_fix'], $buttons['cat_verify'] );
		foreach ( $buttons as $id => $label ) {
			luxe_bmc_action_form( $id, $label, 'button' );
		}
		?>
	</div>

	<form method="post" class="luxe-bmc-card">
		<?php wp_nonce_field( 'luxe_bmc_save' ); ?>
		<h2><?php esc_html_e( 'Process switches', 'luxe-blog-master-command-center' ); ?></h2>
		<table class="widefat striped luxe-bmc-table">
			<tbody>
				<tr>
					<td><input type="checkbox" name="process_learning" value="1" <?php checked( ! empty( $settings['process_learning'] ) ); ?> /></td>
					<td><strong>24/7 process learning</strong><p class="luxe-bmc-note">Every 15 minutes: live scan + process greens. Never publishes.</p></td>
				</tr>
				<tr>
					<td><input type="checkbox" name="safe_autopilot" value="1" <?php checked( ! empty( $settings['safe_autopilot'] ) ); ?> /></td>
					<td><strong>Safe autopilot (drafts only)</strong><p class="luxe-bmc-note">Default OFF. When on, one locked-product draft per cycle. Never publishes.</p></td>
				</tr>
				<tr>
					<td><input type="checkbox" name="date_2026" value="1" <?php checked( ! empty( $settings['date_2026'] ) ); ?> /></td>
					<td><strong>Display-only 2026 dates</strong><p class="luxe-bmc-note">Labeling only. Does not rewrite published titles or bodies.</p></td>
				</tr>
				<tr>
					<td><input type="checkbox" name="auto_purge" value="1" <?php checked( ! empty( $settings['auto_purge'] ) ); ?> /></td>
					<td><strong>Automatic cache purge</strong><p class="luxe-bmc-note">LiteSpeed purge_all + object cache flush after learning.</p></td>
				</tr>
				<tr>
					<td><input type="checkbox" disabled="disabled" /></td>
					<td><strong>Public DOM hiders / mobile guard</strong><p class="luxe-bmc-note">LOCKED OFF. That OFF state is the safe green. Flatsome hamburger stays untouched.</p></td>
				</tr>
				<tr>
					<td><input type="checkbox" disabled="disabled" /></td>
					<td><strong>Homepage overlay CSS/JS</strong><p class="luxe-bmc-note">LOCKED OFF. Zero frontend JavaScript from this plugin.</p></td>
				</tr>
			</tbody>
		</table>
		<p><button type="submit" name="luxe_bmc_save" value="1" class="button button-primary luxe-bmc-save">Save Command Center settings</button></p>
	</form>

	<?php foreach ( $categories as $label => $pack ) : ?>
		<?php
		$rows = isset( $pack['rows'] ) ? $pack['rows'] : array();
		$is_hold = ( false !== strpos( $label, 'Product #0' ) );
		?>
		<div class="luxe-bmc-card">
			<h2><?php echo esc_html( $label ); ?> <span class="luxe-bmc-count"><?php echo esc_html( (string) count( $rows ) ); ?></span></h2>
			<?php if ( ! $is_hold ) : ?>
				<div class="luxe-bmc-actions">
					<?php luxe_bmc_action_form( 'cat_fix', 'FIX NEXT DRAFTS IN THIS CATEGORY', 'button', array( 'cat' => $label ) ); ?>
					<?php luxe_bmc_action_form( 'cat_verify', 'VERIFY 95–100 + MARK APPROVAL READY', 'button', array( 'cat' => $label ) ); ?>
				</div>
			<?php else : ?>
				<p class="luxe-bmc-note">Product #0 stays blocked. Manual re-link is required. This table will not fake-green.</p>
			<?php endif; ?>
			<table class="widefat striped luxe-bmc-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Title</th>
						<th>Status</th>
						<th>Product</th>
						<th>Score</th>
						<th>Note</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $rows, 0, 20 ) as $row ) : ?>
					<tr>
						<td>#<?php echo esc_html( (string) (int) $row['id'] ); ?></td>
						<td><?php echo esc_html( (string) $row['title'] ); ?></td>
						<td><?php echo esc_html( (string) $row['status'] ); ?></td>
						<td><?php echo esc_html( (string) (int) $row['product'] ); ?></td>
						<td>
							<?php
							if ( 'publish' === ( $row['status'] ?? '' ) && (int) $row['score'] < 1 ) {
								echo 'live';
							} else {
								echo esc_html( (string) (int) $row['score'] );
							}
							?>
						</td>
						<td><?php echo esc_html( isset( $row['reason'] ) ? $row['reason'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6">None in this bucket.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>

	<div class="luxe-bmc-grid">
		<div class="luxe-bmc-card">
			<h2>Duplicate Hold</h2>
			<ul>
			<?php foreach ( array_slice( isset( $audit['dup'] ) ? $audit['dup'] : array(), 0, 12 ) as $row ) : ?>
				<li>#<?php echo esc_html( (string) (int) $row['id'] ); ?> — <?php echo esc_html( $row['title'] ); ?> (<?php echo esc_html( $row['reason'] ); ?>)</li>
			<?php endforeach; ?>
			<?php if ( empty( $audit['dup'] ) ) : ?>
				<li>No duplicate holds.</li>
			<?php endif; ?>
			</ul>
		</div>
		<div class="luxe-bmc-card">
			<h2>Approval Ready (still drafts)</h2>
			<ul>
			<?php foreach ( array_slice( isset( $audit['ready'] ) ? $audit['ready'] : array(), 0, 12 ) as $row ) : ?>
				<li>#<?php echo esc_html( (string) (int) $row['id'] ); ?> — <?php echo esc_html( $row['title'] ); ?></li>
			<?php endforeach; ?>
			<?php if ( empty( $audit['ready'] ) ) : ?>
				<li>None stamped yet. Run FINAL VERIFY after a 95–100 rebuild.</li>
			<?php endif; ?>
			</ul>
		</div>
	</div>

	<div class="luxe-bmc-card">
		<h2>Public green scan</h2>
		<p class="luxe-bmc-note">Hiders OFF is the safe green. This plugin never injects homepage overlay CSS/JS.</p>
		<table class="widefat striped luxe-bmc-table">
			<thead>
				<tr><th>URL</th><th>Status</th><th>Title / H1</th><th>Public</th></tr>
			</thead>
			<tbody>
			<?php
			$pages = array( 'home' => 'Homepage', 'blogs' => '/blogs/', 'shop' => '/shop/', 'master' => 'Master #10833', 'sample' => 'Watch Series 9 guide' );
			foreach ( $pages as $key => $label ) :
				$row    = isset( $public[ $key ] ) ? $public[ $key ] : array();
				$scored = isset( $row['scored'] ) ? $row['scored'] : array();
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( isset( $row['status'] ) ? (string) $row['status'] : '—' ); ?></td>
					<td><?php echo esc_html( isset( $row['title'] ) ? $row['title'] : ( isset( $row['h1'] ) ? $row['h1'] : '' ) ); ?></td>
					<td>
						<?php
						if ( isset( $scored['score'] ) ) {
							$failed = array();
							if ( ! empty( $scored['gates'] ) && is_array( $scored['gates'] ) ) {
								foreach ( $scored['gates'] as $gate => $pass ) {
									if ( ! $pass ) {
										$failed[] = $gate;
									}
								}
							}
							echo esc_html( (string) (int) $scored['score'] . '/100' . ( $failed ? ' (' . implode( ', ', $failed ) . ')' : '' ) );
						} elseif ( ! empty( $row['ok'] ) ) {
							echo 'fetched';
						} else {
							echo 'not run';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="luxe-bmc-card">
		<h2>Mission Control companion handshake</h2>
		<p class="luxe-bmc-note">Richard Brummer SEO Mission Control 1.8.2 is the evidence dashboard. It does not write blogs. 2.9.4 armed this board locally, but Mission Control still stored <code>blog_master: []</code> and <code>approval_only: false</code> because it scans for the 2.8.1 plugin identity (<strong>Luxe Blog Master</strong>) and a list-shaped companion filter. 2.9.5 keeps Command Center for Replace, exposes that 2.8.1 name in the same folder, returns a list to <code>rbsmc_blog_master_companion</code>, and sets <code>rbsmc_approval_only</code>. After this zip: Plugins → Replace → Activate. If Plugins also lists <strong>Luxe Blog Master</strong> in this folder, leave it Active (same engine). Then in Mission Control click the complete dashboard refresh. Do not upload this zip over Mission Control or Stay Repair.</p>
		<table class="widefat striped luxe-bmc-table">
			<tbody>
				<tr><th>Complete build</th><td><?php echo ! empty( $companion['complete_build'] ) ? 'ARMED' : 'missing'; ?></td></tr>
				<tr><th>Approval companion</th><td><?php echo ! empty( $companion['approval_companion'] ) ? 'ARMED' : 'missing'; ?></td></tr>
				<tr><th>Approval only</th><td><?php echo ! empty( $companion['approval_only'] ) ? 'ARMED' : 'missing'; ?></td></tr>
				<tr><th>2.8.1 plugin name file</th><td>luxe-blog-master.php in this folder</td></tr>
				<tr><th>Master #10833 locked</th><td><?php echo ! empty( $companion['master_locked'] ) ? 'read-only' : 'check'; ?></td></tr>
				<tr><th>Published from this plugin</th><td>0</td></tr>
			</tbody>
		</table>
	</div>

	<div class="luxe-bmc-card">
		<h2>46-plugin stack harmony</h2>
		<p class="luxe-bmc-note">Live check 2026-08-21: 46 unique plugins, no duplicate folders. Amazon buttons use tag luxetrendse0f-20. Official Associate sentence appears in header + article (counts as one). Homepage rotator has one View on Amazon per card. Do not deactivate KEEP rows. LAYERED means overlap by design. PARK means deactivate only — never delete. Duplicate Plugin Cleaner must never quarantine the keep-set. Command Center never deactivates another plugin.</p>
		<table class="widefat striped luxe-bmc-table">
			<thead>
				<tr><th>Verdict</th><th>Plugin</th><th>Role</th><th>Owner</th><th>Coexistence</th></tr>
			</thead>
			<tbody>
			<?php foreach ( $stack as $row ) : ?>
				<?php
				$verdict = isset( $row['verdict'] ) ? $row['verdict'] : 'KEEP';
				$vclass  = 'luxe-bmc-keep';
				if ( 'LAYERED' === $verdict ) {
					$vclass = 'luxe-bmc-layered';
				} elseif ( 'PARK' === $verdict ) {
					$vclass = 'luxe-bmc-park';
				}
				?>
				<tr>
					<td><strong class="<?php echo esc_attr( $vclass ); ?>"><?php echo esc_html( $verdict ); ?></strong></td>
					<td><?php echo esc_html( $row['name'] ); ?></td>
					<td><?php echo esc_html( $row['role'] ); ?></td>
					<td><?php echo esc_html( $row['owner'] ); ?></td>
					<td><?php echo esc_html( $row['note'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="luxe-bmc-card luxe-bmc-signals">
		<h2>25 connected button / hook signals</h2>
		<ul class="luxe-bmc-signal-list">
			<?php foreach ( $process as $signal ) : ?>
				<?php
				$level = isset( $signal['state'] ) ? $signal['state'] : ( isset( $signal['level'] ) ? $signal['level'] : 'green' );
				$class = 'luxe-bmc-signal';
				if ( 'yellow' === $level ) {
					$class .= ' luxe-bmc-signal-yellow';
				} elseif ( 'red' === $level ) {
					$class .= ' luxe-bmc-signal-red';
				}
				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<span class="luxe-bmc-dot"></span>
					<span>
						<strong><?php echo esc_html( isset( $signal['label'] ) ? $signal['label'] : '' ); ?></strong>
						<em><?php echo esc_html( isset( $signal['note'] ) ? $signal['note'] : ( isset( $signal['detail'] ) ? $signal['detail'] : '' ) ); ?></em>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="luxe-bmc-card">
		<h2>Action log</h2>
		<ul class="luxe-bmc-log">
			<?php foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
				<li><code><?php echo esc_html( isset( $row['at'] ) ? $row['at'] : '' ); ?></code> <strong><?php echo esc_html( isset( $row['action'] ) ? $row['action'] : '' ); ?></strong> — <?php echo esc_html( isset( $row['detail'] ) ? $row['detail'] : '' ); ?></li>
			<?php endforeach; ?>
			<?php if ( ! $log ) : ?>
				<li>No actions yet. Run SCAN + BUILD or LEARNING SNAPSHOT.</li>
			<?php endif; ?>
		</ul>
	</div>
</div>
