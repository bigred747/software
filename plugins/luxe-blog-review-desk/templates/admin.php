<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$live    = isset( $live ) && is_array( $live ) ? $live : array();
$drafts  = isset( $drafts ) && is_array( $drafts ) ? $drafts : array();
$ready   = isset( $ready ) && is_array( $ready ) ? $ready : array();
$can_pub = current_user_can( 'publish_posts' );

/**
 * @param array $rows Rows.
 * @param bool  $can_pub Can publish.
 * @param bool  $show_pub Show publish column.
 */
function lbrd_print_table( $rows, $can_pub, $show_pub ) {
	if ( ! $rows ) {
		echo '<p>None in this list.</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>ID</th><th>Guide</th><th>Status</th><th>Product</th><th>Score</th><th>View</th>';
	if ( $show_pub ) {
		echo '<th>Publish</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		echo '<tr>';
		echo '<td>' . esc_html( (string) $id ) . '</td>';
		echo '<td>' . esc_html( isset( $row['title'] ) ? $row['title'] : '' ) . '</td>';
		echo '<td>' . esc_html( isset( $row['status'] ) ? $row['status'] : '' ) . '</td>';
		echo '<td>' . esc_html( isset( $row['product'] ) ? (string) (int) $row['product'] : '0' ) . '</td>';
		$score_label = isset( $row['score'] ) ? (string) (int) $row['score'] : '0';
		if ( 'publish' === ( isset( $row['status'] ) ? $row['status'] : '' ) && (int) $score_label < 1 ) {
			$score_label = 'live';
		}
		echo '<td>' . esc_html( $score_label ) . '</td>';
		echo '<td>';
		if ( ! empty( $row['view'] ) ) {
			echo '<a class="button button-primary" href="' . esc_url( $row['view'] ) . '" target="_blank" rel="noopener noreferrer">View</a>';
		}
		echo '</td>';
		if ( $show_pub ) {
			echo '<td>';
			$ok = ! empty( $row['gate']['ok'] );
			if ( $ok && $can_pub && 'publish' !== $row['status'] ) {
				echo '<form method="post" class="lbrd-inline" onsubmit="return confirm(\'Publish this guide to the live site?\');">';
				wp_nonce_field( 'lbrd_publish' );
				echo '<input type="hidden" name="lbrd_post_id" value="' . esc_attr( (string) $id ) . '" />';
				echo '<button type="submit" name="lbrd_publish" value="1" class="button">Publish</button>';
				echo '</form>';
			} else {
				echo '<span class="lbrd-why">' . esc_html( isset( $row['gate']['reason'] ) ? $row['gate']['reason'] : '' ) . '</span>';
			}
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}
?>
<div class="wrap lsr-wrap lbrd-wrap">
	<header class="lsr-hero">
		<p class="lsr-kicker">LuxeTrendsetters · review desk</p>
		<h1>View, then Publish</h1>
		<p class="lsr-lead">Reads the same Command Center product lock, last score, and Approval Ready flags as Blog Master. Live guides have View only. Drafts get a Publish button only after Command Center marks them Approval Ready or 95–100 with a real product. This plugin never auto-publishes, never deletes, and never rewrites Amazon URLs.</p>
	</header>

	<section class="laps-counters">
		<span>Live now: <strong><?php echo esc_html( (string) count( $live ) ); ?></strong></span>
		<span>Ready to publish: <strong><?php echo esc_html( (string) count( $ready ) ); ?></strong></span>
		<span>Drafts in review: <strong><?php echo esc_html( (string) count( $drafts ) ); ?></strong></span>
		<span>Master #10833 locked</span>
	</section>

	<section class="lsr-card">
		<h2>Ready to publish</h2>
		<?php if ( $ready ) : ?>
			<p class="lsr-note">Click <strong>View</strong> first. If the guide looks right, click <strong>Publish</strong>.</p>
			<?php lbrd_print_table( $ready, $can_pub, true ); ?>
		<?php else : ?>
			<p>None yet. In Command Center repair a draft to 95–100, then click FINAL VERIFY. Come back here. Product #0 rows stay blocked.</p>
		<?php endif; ?>
	</section>

	<section class="lsr-card">
		<h2>Already live — View only</h2>
		<?php lbrd_print_table( $live, false, false ); ?>
	</section>

	<section class="lsr-card">
		<h2>Drafts not ready</h2>
		<p class="lsr-note">View is always available. Publish stays off until the gate turns green.</p>
		<?php lbrd_print_table( $drafts, $can_pub, true ); ?>
	</section>
</div>
