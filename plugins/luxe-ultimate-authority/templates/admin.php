<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$notices = array(
	'scanned'         => 'Scanned the next 10 guides. Nothing was published.',
	'scan-wrapped'    => 'Reached the end of the guides. The next scan starts again at the oldest ID.',
	'sampled'         => 'Homepage sample stored. Speed signals now use that sample.',
	'noindex'         => 'Noindex list updated. Other pages were not changed.',
	'settings'        => 'Settings saved.',
	'retired'         => 'Overlapping plugins were deactivated. Their files are still on disk.',
	'retire-none'     => 'No overlapping plugins were active.',
	'backup-required' => 'Retire was not run. Confirm the Snapshot backup first.',
);
?>
<div class="wrap luae-wrap">
	<div class="luae-hero">
		<p class="luae-kicker">Luxe Ultimate Authority &amp; Revenue Engine</p>
		<h1><?php echo esc_html( $tally['green'] . ' / ' . $tally['total'] . ' processes green' ); ?></h1>
		<p class="luae-lead"><?php echo $scanned ? esc_html__( 'These signals are from the last scan. Yellow means a guide or archive still needs you. Red means a check failed.', 'luxe-ultimate-authority' ) : esc_html__( 'Safety locks are green now. Run a scan before reading the content signals as results.', 'luxe-ultimate-authority' ); ?></p>
	</div>

	<?php if ( $notice && isset( $notices[ $notice ] ) ) : ?>
		<div class="notice notice-info"><p><?php echo esc_html( $notices[ $notice ] ); ?></p></div>
	<?php endif; ?>

	<section class="luae-panel">
		<h2><?php esc_html_e( 'Process signals', 'luxe-ultimate-authority' ); ?></h2>
		<?php
		$group = '';
		foreach ( $signals as $signal ) :
			if ( $group !== $signal['group'] ) :
				$group = $signal['group'];
				?>
				<h3><?php echo esc_html( $group ); ?></h3>
			<?php endif; ?>
			<div class="luae-signal">
				<span class="luae-pill luae-<?php echo esc_attr( $signal['level'] ); ?>"><?php echo esc_html( strtoupper( $signal['level'] ) ); ?></span>
				<div>
					<strong><?php echo esc_html( $signal['label'] ); ?></strong>
					<p><?php echo esc_html( $signal['detail'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luae-actions">
			<?php wp_nonce_field( 'luae_scan' ); ?>
			<input type="hidden" name="action" value="luae_scan" />
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Scan next 10 guides', 'luxe-ultimate-authority' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luae-actions">
			<?php wp_nonce_field( 'luae_sample' ); ?>
			<input type="hidden" name="action" value="luae_sample" />
			<button class="button" type="submit"><?php esc_html_e( 'Refresh homepage speed sample', 'luxe-ultimate-authority' ); ?></button>
		</form>
	</section>

	<section class="luae-panel">
		<h2><?php esc_html_e( 'Where to spend time', 'luxe-ultimate-authority' ); ?></h2>
		<p><?php esc_html_e( 'High means the guide is missing several human sections. Views stay blank unless another plugin has already stored a count. This is not a dollar forecast.', 'luxe-ultimate-authority' ); ?></p>
		<table class="widefat luae-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Guide', 'luxe-ultimate-authority' ); ?></th>
					<th><?php esc_html_e( 'Score', 'luxe-ultimate-authority' ); ?></th>
					<th><?php esc_html_e( 'Opportunity', 'luxe-ultimate-authority' ); ?></th>
					<th><?php esc_html_e( 'Views', 'luxe-ultimate-authority' ); ?></th>
					<th><?php esc_html_e( 'Problems', 'luxe-ultimate-authority' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $reports ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No guides scanned yet.', 'luxe-ultimate-authority' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $reports as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
						<td><?php echo esc_html( (string) ( isset( $row['report']['score'] ) ? $row['report']['score'] : 0 ) ); ?> / 100</td>
						<td><?php echo esc_html( isset( $row['report']['opportunity'] ) ? (string) $row['report']['opportunity'] : '' ); ?></td>
						<td><?php echo ! isset( $row['report']['views'] ) || null === $row['report']['views'] ? esc_html__( 'Not recorded', 'luxe-ultimate-authority' ) : esc_html( (string) $row['report']['views'] ); ?></td>
						<td><?php echo esc_html( isset( $row['report']['problems'] ) && is_array( $row['report']['problems'] ) ? implode( ' ', $row['report']['problems'] ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</section>

	<section class="luae-panel">
		<h2><?php esc_html_e( 'Index recommendations', 'luxe-ultimate-authority' ); ?></h2>
		<p><?php esc_html_e( 'Approving noindex does not redirect the URL and does not delete it. Shopping categories are listed only as pages to improve.', 'luxe-ultimate-authority' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'luae_noindex' ); ?>
			<input type="hidden" name="action" value="luae_noindex" />
			<table class="widefat luae-table">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e( 'Path', 'luxe-ultimate-authority' ); ?></th>
						<th><?php esc_html_e( 'Action', 'luxe-ultimate-authority' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$buckets = array(
					'weight' => 'noindex',
					'tags'   => 'noindex',
					'empty'  => 'noindex',
					'thin'   => 'improve',
				);
				$seen = array();
				if ( is_array( $paths ) ) :
					foreach ( $buckets as $bucket => $action ) :
						if ( empty( $paths[ $bucket ] ) || ! is_array( $paths[ $bucket ] ) ) {
							continue;
						}
						foreach ( $paths[ $bucket ] as $path ) :
							$path = (string) $path;
							if ( isset( $seen[ $path ] ) ) {
								continue;
							}
							$seen[ $path ] = true;
							$on = is_array( $approved ) && LUAE_Junk::matches_approved( $path, $approved );
							?>
							<tr>
								<td><?php if ( 'noindex' === $action ) : ?><input type="checkbox" name="luae_paths[]" value="<?php echo esc_attr( $path ); ?>" /><?php endif; ?></td>
								<td><code><?php echo esc_html( $path ); ?></code></td>
								<td><?php echo 'improve' === $action ? esc_html__( 'Improve. Leave it indexed.', 'luxe-ultimate-authority' ) : esc_html( $on ? 'Noindex approved' : 'Recommend noindex' ); ?></td>
							</tr>
							<?php
						endforeach;
					endforeach;
				endif;
				if ( ! $seen ) :
					?>
					<tr><td colspan="3"><?php esc_html_e( 'Run a scan to list archives.', 'luxe-ultimate-authority' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<p class="luae-actions">
				<button class="button button-primary" name="luae_noindex_mode" value="approve" type="submit"><?php esc_html_e( 'Approve noindex for selected', 'luxe-ultimate-authority' ); ?></button>
				<button class="button" name="luae_noindex_mode" value="remove" type="submit"><?php esc_html_e( 'Remove noindex', 'luxe-ultimate-authority' ); ?></button>
			</p>
		</form>
	</section>

	<section class="luae-panel">
		<h2><?php esc_html_e( 'Overlapping plugins', 'luxe-ultimate-authority' ); ?></h2>
		<p><?php esc_html_e( 'Rank Math, LiteSpeed, Wordfence, WooCommerce, WZone, Link Whisper, Amazon Bridge, and Product Scout stay on. This button only deactivates the overlap list, and only after you confirm a Snapshot backup. It does not delete files.', 'luxe-ultimate-authority' ); ?></p>
		<?php if ( $split['retire'] ) : ?>
			<ul>
				<?php foreach ( $split['retire'] as $name ) : ?>
					<li><?php echo esc_html( $name ); ?></li>
				<?php endforeach; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'luae_retire' ); ?>
				<input type="hidden" name="action" value="luae_retire" />
				<label><input type="checkbox" name="luae_confirm_backup" value="1" /> <?php esc_html_e( 'I have a current Snapshot Pro backup.', 'luxe-ultimate-authority' ); ?></label>
				<button class="button" type="submit"><?php esc_html_e( 'Deactivate overlapping plugins', 'luxe-ultimate-authority' ); ?></button>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'None of the overlapping plugins are active.', 'luxe-ultimate-authority' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="luae-panel">
		<h2><?php esc_html_e( 'Settings', 'luxe-ultimate-authority' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'luae_settings' ); ?>
			<input type="hidden" name="action" value="luae_settings" />
			<p>
				<label for="luae-tag"><?php esc_html_e( 'Amazon Associates tag', 'luxe-ultimate-authority' ); ?></label><br />
				<input id="luae-tag" name="expected_tag" type="text" value="<?php echo esc_attr( $settings['expected_tag'] ); ?>" />
			</p>
			<p><label><input type="checkbox" name="show_front" value="1" <?php checked( ! empty( $settings['show_front'] ) ); ?> /> <?php esc_html_e( 'Show approved blocks on the public guide.', 'luxe-ultimate-authority' ); ?></label></p>
			<p><label><input type="checkbox" name="hourly" value="1" <?php checked( ! empty( $settings['hourly'] ) ); ?> /> <?php esc_html_e( 'Hourly scan of one guide. Flags only. Never approves or publishes.', 'luxe-ultimate-authority' ); ?></label></p>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'luxe-ultimate-authority' ); ?></button>
		</form>
	</section>
</div>
