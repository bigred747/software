<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$green  = isset( $board['green'] ) ? (int) $board['green'] : 0;
$yellow = isset( $board['yellow'] ) ? (int) $board['yellow'] : 0;
$red    = isset( $board['red'] ) ? (int) $board['red'] : 0;
$total  = isset( $board['total'] ) ? (int) $board['total'] : 0;
$at     = ! empty( $board['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $board['at'] ) : '';
$cap    = isset( $memory['cap'] ) ? (int) $memory['cap'] : 1;
$rows   = ( isset( $board['rows'] ) && is_array( $board['rows'] ) ) ? $board['rows'] : array();
$log    = ( isset( $log ) && is_array( $log ) ) ? $log : array();
?>
<div class="wrap lxc">
	<style>
		.lxc{max-width:1180px;color:#1c1915}
		.lxc *{box-sizing:border-box}
		.lxc-hero{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;padding:22px 0 18px;border-bottom:1px solid #e4ddd2}
		.lxc-kicker{margin:0 0 6px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#8a5a32}
		.lxc h1{margin:0;font-size:28px;line-height:1.15;font-weight:650;color:#1c1915}
		.lxc-lead{max-width:680px;margin:8px 0 0;color:#4a453e;font-size:14px;line-height:1.5}
		.lxc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}
		.lxc-stat{background:#fff;border:1px solid #e4ddd2;border-radius:14px;padding:14px 16px}
		.lxc-stat span{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#746b62}
		.lxc-stat strong{display:block;margin-top:4px;font-size:28px;line-height:1;font-weight:650}
		.lxc-stat em{font-style:normal;font-size:14px;color:#746b62}
		.lxc-green strong{color:#0f6b45}
		.lxc-yellow strong{color:#8a5a12}
		.lxc-red strong{color:#9f1239}
		.lxc-tools{display:flex;gap:10px;align-items:center;margin:0 0 12px}
		.lxc-tools input,.lxc-tools select{height:36px;border:1px solid #d9d0c4;border-radius:10px;background:#fff;padding:0 12px;color:#1c1915}
		.lxc-tools input{flex:1;min-width:180px}
		.lxc-table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e4ddd2;border-radius:14px;overflow:hidden}
		.lxc-table th{text-align:left;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#746b62;padding:12px 14px;background:#faf7f2;border-bottom:1px solid #e4ddd2}
		.lxc-table td{padding:12px 14px;border-bottom:1px solid #f0ebe3;vertical-align:top}
		.lxc-table tr:last-child td{border-bottom:0}
		.lxc-name{font-weight:650}
		.lxc-meta{display:block;margin-top:3px;color:#746b62;font-size:12px}
		.lxc-pip{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.08em}
		.lxc-pip i{width:9px;height:9px;border-radius:50%;display:inline-block}
		.lxc-pip-green i{background:#128a57}
		.lxc-pip-yellow i{background:#c4841d}
		.lxc-pip-red i{background:#be123c}
		.lxc-pip-green{color:#0f6b45}
		.lxc-pip-yellow{color:#8a5a12}
		.lxc-pip-red{color:#9f1239}
		.lxc details{margin-top:8px}
		.lxc summary{cursor:pointer;color:#8a5a32;font-size:12px}
		.lxc-sub{margin:8px 0 0;padding:0;list-style:none}
		.lxc-sub li{margin:0 0 6px;font-size:12px;line-height:1.45;color:#4a453e}
		.lxc-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;margin-top:14px}
		.lxc-card{background:#fff;border:1px solid #e4ddd2;border-radius:14px;padding:16px 18px}
		.lxc-card h2{margin:0 0 8px;font-size:16px}
		.lxc-card ul{margin:0;padding-left:18px}
		.lxc-card li{margin:0 0 6px}
		.lxc-card p,.lxc-note{color:#4a453e;font-size:13px;line-height:1.5}
		.lxc-save{border:0;border-radius:999px;background:#1c1915;color:#fff;padding:8px 16px;cursor:pointer}
		.lxc-save:hover{background:#3a3128}
		.lxc-open{color:#8a5a32;text-decoration:none;font-size:12px;font-weight:650}
		@media (max-width:860px){
			.lxc-stats,.lxc-grid{grid-template-columns:1fr}
			.lxc-hero{flex-direction:column;align-items:flex-start}
		}
	</style>

	<header class="lxc-hero">
		<div>
			<p class="lxc-kicker">LuxeTrendsetters · stack conductor</p>
			<h1>Luxe Stack Conductor <?php echo esc_html( LUXE_CONDUCTOR_VERSION ); ?></h1>
			<p class="lxc-lead">Every installed plugin has a process signal. Green means that process passed. Heavy 24/7 learners take turns, one lane at a time, so cron finishes instead of stacking forty jobs on the same second.</p>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'luxe_conductor_snapshot' ); ?>
			<button type="submit" class="lxc-save" name="luxe_conductor_snapshot" value="1">Store learning snapshot</button>
		</form>
	</header>

	<section class="lxc-stats">
		<article class="lxc-stat lxc-green">
			<span>Green</span>
			<strong><?php echo esc_html( (string) $green ); ?> <em>/ <?php echo esc_html( (string) $total ); ?></em></strong>
		</article>
		<article class="lxc-stat lxc-yellow">
			<span>Review</span>
			<strong><?php echo esc_html( (string) $yellow ); ?></strong>
		</article>
		<article class="lxc-stat lxc-red">
			<span>Red</span>
			<strong><?php echo esc_html( (string) $red ); ?></strong>
		</article>
		<article class="lxc-stat">
			<span>Slot cap · <?php echo esc_html( $at ); ?></span>
			<strong><?php echo esc_html( (string) $cap ); ?></strong>
		</article>
	</section>

	<div class="lxc-tools">
		<input id="lxc-q" type="search" placeholder="Filter plugins" aria-label="Filter plugins">
		<select id="lxc-level" aria-label="Filter by signal">
			<option value="">All signals</option>
			<option value="green">Green</option>
			<option value="yellow">Review</option>
			<option value="red">Red</option>
		</select>
	</div>

	<table class="lxc-table" id="lxc-table">
		<thead>
			<tr>
				<th>Signal</th>
				<th>Process</th>
				<th>Lane</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<?php
			$level = isset( $row['level'] ) ? $row['level'] : 'yellow';
			$name  = isset( $row['name'] ) ? $row['name'] : '';
			?>
			<tr data-level="<?php echo esc_attr( $level ); ?>" data-name="<?php echo esc_attr( strtolower( $name ) ); ?>">
				<td><span class="lxc-pip lxc-pip-<?php echo esc_attr( $level ); ?>"><i></i><?php echo esc_html( strtoupper( $level ) ); ?></span></td>
				<td>
					<span class="lxc-name"><?php echo esc_html( $name ); ?></span>
					<span class="lxc-meta"><?php echo esc_html( isset( $row['version'] ) && $row['version'] ? 'v' . $row['version'] . ' · ' : '' ); ?><?php echo esc_html( isset( $row['detail'] ) ? $row['detail'] : '' ); ?></span>
					<?php if ( ! empty( $row['signals'] ) && is_array( $row['signals'] ) ) : ?>
						<details>
							<summary><?php echo esc_html( count( $row['signals'] ) ); ?> signals inside this process</summary>
							<ul class="lxc-sub">
								<?php foreach ( $row['signals'] as $signal ) : ?>
									<li>
										<span class="lxc-pip lxc-pip-<?php echo esc_attr( isset( $signal['level'] ) ? $signal['level'] : 'yellow' ); ?>"><i></i><?php echo esc_html( strtoupper( isset( $signal['level'] ) ? $signal['level'] : 'yellow' ) ); ?></span>
										<?php echo esc_html( ( isset( $signal['label'] ) ? $signal['label'] : '' ) . ' — ' . ( isset( $signal['detail'] ) ? $signal['detail'] : '' ) ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( isset( $row['lane'] ) ? $row['lane'] : '' ); ?></td>
				<td>
					<?php if ( ! empty( $row['menu'] ) ) : ?>
						<a class="lxc-open" href="<?php echo esc_url( admin_url( $row['menu'] ) ); ?>">Open</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="lxc-grid">
		<section class="lxc-card">
			<h2>Safety lock</h2>
			<ul>
				<li>Never publishes and never changes post status</li>
				<li>Never rewrites Amazon or affiliate URLs</li>
				<li>Never deactivates, deletes, or quarantines another plugin</li>
				<li>Never imports products or sends posts to trash</li>
				<li>Public page filters stay where they are. Only learning jobs are serialized</li>
				<li>WooCommerce, WZone, Rank Math, LiteSpeed, and Wordfence cron are never postponed</li>
			</ul>
		</section>
		<section class="lxc-card">
			<h2>24/7 learning</h2>
			<p>One read-only snapshot every 5 minutes. The cap starts at 1 heavy job. After three cron hits under 8 seconds it steps up, to a maximum of 3. A hit over 12 seconds drops the cap back to 1.</p>
			<p class="lxc-note">Hostinger still needs a real cron job on <code>wp-cron.php</code> every 5 minutes. Opening this screen wakes one cron call when the snapshot is late. A green queue signal does not repaint Mission Control, WZone, or Keyword Intelligence evidence.</p>
		</section>
	</div>

	<?php if ( $log ) : ?>
		<section class="lxc-card" style="margin-top:12px">
			<h2>Learning log</h2>
			<table class="lxc-table">
				<thead>
					<tr>
						<th>When</th>
						<th>Green</th>
						<th>Review</th>
						<th>Red</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $log as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( ! empty( $entry['at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $entry['at'] ) : '' ); ?></td>
						<td><?php echo esc_html( ( isset( $entry['green'] ) ? (string) $entry['green'] : '0' ) . ' / ' . ( isset( $entry['total'] ) ? (string) $entry['total'] : '0' ) ); ?></td>
						<td><?php echo esc_html( isset( $entry['yellow'] ) ? (string) $entry['yellow'] : '0' ); ?></td>
						<td><?php echo esc_html( isset( $entry['red'] ) ? (string) $entry['red'] : '0' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
<script>
(function () {
	var q = document.getElementById('lxc-q');
	var level = document.getElementById('lxc-level');
	var rows = document.querySelectorAll('#lxc-table tbody tr');
	function apply() {
		var query = (q.value || '').toLowerCase();
		var want = level.value;
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			var show = (!want || row.getAttribute('data-level') === want) && (!query || row.getAttribute('data-name').indexOf(query) !== -1);
			row.style.display = show ? '' : 'none';
		}
	}
	q.addEventListener('input', apply);
	level.addEventListener('change', apply);
})();
</script>
