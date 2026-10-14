<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$notices = array(
	'scanned'         => 'Scan saved. Nothing was deleted.',
	'deleted'         => 'The installed matches were deactivated and deleted.',
	'delete-partial'  => 'Some folders could not be deleted. The red rows say why. The others were removed.',
	'backup-required' => 'Nothing was deleted. Confirm the Snapshot backup first.',
);
?>
<div class="wrap luor-wrap">
	<div class="luor-hero">
		<p class="luor-kicker">Luxe Overlap Remover</p>
		<h1><?php echo esc_html( $tally['green'] . ' / ' . $tally['total'] . ' processes green' ); ?></h1>
		<p class="luor-lead"><?php esc_html_e( 'Green on a plugin row means that folder is already gone. Yellow means it is still installed. The safety rows stay green because those locks do not change.', 'luxe-overlap-remover' ); ?></p>
	</div>

	<?php if ( $notice && isset( $notices[ $notice ] ) ) : ?>
		<div class="notice notice-info"><p><?php echo esc_html( $notices[ $notice ] ); ?></p></div>
	<?php endif; ?>

	<section class="luor-panel">
		<?php
		$group = '';
		foreach ( $signals as $signal ) :
			if ( $group !== $signal['group'] ) :
				$group = $signal['group'];
				?>
				<h2><?php echo esc_html( $group ); ?></h2>
			<?php endif; ?>
			<div class="luor-signal">
				<span class="luor-pill luor-<?php echo esc_attr( $signal['level'] ); ?>"><?php echo esc_html( strtoupper( $signal['level'] ) ); ?></span>
				<div>
					<strong><?php echo esc_html( $signal['label'] ); ?></strong>
					<p><?php echo esc_html( $signal['detail'] ); ?></p>
				</div>
			</div>
		<?php endforeach; ?>
	</section>

	<section class="luor-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luor-actions">
			<?php wp_nonce_field( 'luor_scan' ); ?>
			<input type="hidden" name="action" value="luor_scan" />
			<button class="button" type="submit"><?php esc_html_e( 'Scan the 18 folders', 'luxe-overlap-remover' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="luor-actions">
			<?php wp_nonce_field( 'luor_delete' ); ?>
			<input type="hidden" name="action" value="luor_delete" />
			<label><input type="checkbox" name="luor_confirm_backup" value="1" /> <?php esc_html_e( 'I have a current Snapshot Pro backup.', 'luxe-overlap-remover' ); ?></label>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Deactivate and delete the 18 folders', 'luxe-overlap-remover' ); ?></button>
		</form>
	</section>
</div>
