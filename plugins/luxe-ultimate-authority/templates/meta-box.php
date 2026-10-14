<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$approved = isset( $modules['approved'] ) && is_array( $modules['approved'] ) ? $modules['approved'] : array();
$scores   = isset( $modules['scores'] ) && is_array( $modules['scores'] ) ? $modules['scores'] : array();
$compare  = isset( $modules['compare'] ) && is_array( $modules['compare'] ) ? $modules['compare'] : array();
$prompts  = isset( $report['prompts'] ) && is_array( $report['prompts'] ) ? $report['prompts'] : LUAE_Post::prompts( array( 'title' => $post->post_title ) );
$errors   = get_transient( 'luae_errors_' . $post->ID );
if ( ! is_array( $errors ) ) {
	$errors = array();
}
delete_transient( 'luae_errors_' . $post->ID );
$score_keys = array(
	'design'      => 'Design',
	'performance' => 'Performance',
	'battery'     => 'Battery',
	'value'       => 'Value',
	'overall'     => 'Overall',
);
?>
<div class="luae-guide">
	<?php if ( $errors ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' ', $errors ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( $report ) : ?>
		<p class="luae-scoreline">
			<span class="luae-pill <?php echo isset( $report['score'] ) && (int) $report['score'] >= 80 ? 'luae-green' : 'luae-yellow'; ?>">
				<?php echo esc_html( ( isset( $report['score'] ) ? (int) $report['score'] : 0 ) . ' / 100' ); ?>
			</span>
			<?php echo esc_html( isset( $report['opportunity'] ) ? (string) $report['opportunity'] : '' ); ?>
			<?php if ( isset( $report['views'] ) && null !== $report['views'] ) : ?>
				<?php echo esc_html( ' · ' . (int) $report['views'] . ' views' ); ?>
			<?php else : ?>
				<?php esc_html_e( ' · Views not recorded', 'luxe-ultimate-authority' ); ?>
			<?php endif; ?>
		</p>
		<ul class="luae-problems">
			<?php foreach ( LUAE_Post::signals( $report ) as $signal ) : ?>
				<li>
					<span class="luae-pill luae-<?php echo esc_attr( $signal['level'] ); ?>"><?php echo esc_html( strtoupper( $signal['level'] ) ); ?></span>
					<span><strong><?php echo esc_html( $signal['label'] ); ?></strong> <?php echo esc_html( $signal['detail'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php esc_html_e( 'Save this guide or run a scan to fill the signals.', 'luxe-ultimate-authority' ); ?></p>
	<?php endif; ?>

	<p class="luae-prompt"><?php echo esc_html( $prompts['verdict'] ); ?></p>
	<p><label><input type="checkbox" name="luae_approve[]" value="verdict" <?php checked( ! empty( $approved['verdict'] ) ); ?> /> <?php esc_html_e( "Approve Rick's Verdict", 'luxe-ultimate-authority' ); ?></label></p>
	<textarea name="luae_verdict" rows="4" class="large-text"><?php echo esc_textarea( isset( $modules['verdict'] ) ? (string) $modules['verdict'] : '' ); ?></textarea>

	<div class="luae-grid">
		<p>
			<label><?php esc_html_e( 'Pros, one per line', 'luxe-ultimate-authority' ); ?></label>
			<span class="luae-prompt"><?php echo esc_html( $prompts['pros'] ); ?></span>
			<textarea name="luae_pros" rows="5" class="large-text"><?php echo esc_textarea( isset( $modules['pros'] ) ? (string) $modules['pros'] : '' ); ?></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'Cons, one per line', 'luxe-ultimate-authority' ); ?></label>
			<span class="luae-prompt"><?php echo esc_html( $prompts['cons'] ); ?></span>
			<textarea name="luae_cons" rows="5" class="large-text"><?php echo esc_textarea( isset( $modules['cons'] ) ? (string) $modules['cons'] : '' ); ?></textarea>
		</p>
	</div>
	<p><label><input type="checkbox" name="luae_approve[]" value="pros_cons" <?php checked( ! empty( $approved['pros_cons'] ) ); ?> /> <?php esc_html_e( 'Approve pros and cons', 'luxe-ultimate-authority' ); ?></label></p>

	<div class="luae-grid">
		<p>
			<label><?php esc_html_e( 'Who should buy this', 'luxe-ultimate-authority' ); ?></label>
			<span class="luae-prompt"><?php echo esc_html( $prompts['buyers'] ); ?></span>
			<textarea name="luae_buy" rows="4" class="large-text"><?php echo esc_textarea( isset( $modules['buy'] ) ? (string) $modules['buy'] : '' ); ?></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'Who should skip it', 'luxe-ultimate-authority' ); ?></label>
			<span class="luae-prompt"><?php echo esc_html( $prompts['skip'] ); ?></span>
			<textarea name="luae_skip" rows="4" class="large-text"><?php echo esc_textarea( isset( $modules['skip'] ) ? (string) $modules['skip'] : '' ); ?></textarea>
		</p>
	</div>
	<p><label><input type="checkbox" name="luae_approve[]" value="buyers" <?php checked( ! empty( $approved['buyers'] ) ); ?> /> <?php esc_html_e( 'Approve buyer types', 'luxe-ultimate-authority' ); ?></label></p>

	<p class="luae-prompt"><?php echo esc_html( $prompts['scores'] ); ?></p>
	<div class="luae-scores">
		<?php foreach ( $score_keys as $key => $label ) : ?>
			<label><?php echo esc_html( $label ); ?>
				<input type="text" name="luae_score_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( isset( $scores[ $key ] ) ? (string) $scores[ $key ] : '' ); ?>" />
			</label>
		<?php endforeach; ?>
	</div>
	<p><label><input type="checkbox" name="luae_approve[]" value="scorecard" <?php checked( ! empty( $approved['scorecard'] ) ); ?> /> <?php esc_html_e( 'Approve scorecard', 'luxe-ultimate-authority' ); ?></label></p>

	<p class="luae-prompt"><?php echo esc_html( $prompts['compare'] ); ?></p>
	<?php if ( ! empty( $report['siblings'] ) && is_array( $report['siblings'] ) ) : ?>
		<ul>
			<?php foreach ( $report['siblings'] as $sibling ) : ?>
				<li><?php echo esc_html( isset( $sibling['title'] ) ? $sibling['title'] : '' ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p><label><input type="checkbox" name="luae_use_siblings" value="1" /> <?php esc_html_e( 'Copy these real guides into the comparison fields', 'luxe-ultimate-authority' ); ?></label></p>
	<?php endif; ?>
	<?php for ( $i = 0; $i < 3; $i++ ) : ?>
		<p class="luae-compare">
			<input type="text" class="regular-text" name="luae_compare_title_<?php echo esc_attr( (string) $i ); ?>" value="<?php echo esc_attr( isset( $compare[ $i ]['title'] ) ? (string) $compare[ $i ]['title'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Guide title', 'luxe-ultimate-authority' ); ?>" />
			<input type="url" class="regular-text" name="luae_compare_url_<?php echo esc_attr( (string) $i ); ?>" value="<?php echo esc_attr( isset( $compare[ $i ]['url'] ) ? (string) $compare[ $i ]['url'] : '' ); ?>" placeholder="https://luxetrendsetters.com/..." />
		</p>
	<?php endfor; ?>
	<p><label><input type="checkbox" name="luae_approve[]" value="compare" <?php checked( ! empty( $approved['compare'] ) ); ?> /> <?php esc_html_e( 'Approve comparison', 'luxe-ultimate-authority' ); ?></label></p>

	<p class="luae-prompt"><?php echo esc_html( $prompts['faq'] ); ?></p>
	<textarea name="luae_faq" rows="5" class="large-text"><?php echo esc_textarea( isset( $modules['faq'] ) ? (string) $modules['faq'] : '' ); ?></textarea>
	<p><label><input type="checkbox" name="luae_approve[]" value="faq" <?php checked( ! empty( $approved['faq'] ) ); ?> /> <?php esc_html_e( 'Approve FAQ', 'luxe-ultimate-authority' ); ?></label></p>
</div>
