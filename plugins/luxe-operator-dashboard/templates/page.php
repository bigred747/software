<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$checks = array(
	'clean_widgets'          => 'Remove YITH news, WordPress news, Quick Draft, empty reviews, and Site Health widgets',
	'hide_dashboard_notices' => 'Hide repeating plugin nags on the Dashboard screen (toggle still available)',
	'hide_rank_math_blog'    => 'Hide “Latest Blog Posts from Rank Math” inside the Overview widget',
	'hide_sitekit_upsells'   => 'Hide Site Kit Reader Revenue Manager, Ads, and AdSense connect upsells',
	'show_operator_widget'   => 'Show the Luxe Operator card at the top of Dashboard',
);
?>
<div class="wrap luxe-op-wrap">
	<h1>Luxe Operator Dashboard 1.1.1</h1>
	<p>Admin-only. It does not auto-write, auto-publish, hook frontend content, or run content cron. Keep <strong>Luxe Hard Rescue Admin Cleaner</strong> active.</p>

	<form method="post">
		<?php wp_nonce_field( 'luxe_op_save' ); ?>
		<input type="hidden" name="luxe_op_save" value="1" />
		<?php foreach ( $checks as $key => $label ) : ?>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label></p>
		<?php endforeach; ?>
		<p><button class="button button-primary" type="submit">Save operator settings</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">Open Dashboard</a></p>
	</form>

	<hr />
	<?php include LUXE_OP_DIR . 'templates/widget.php'; ?>
</div>
