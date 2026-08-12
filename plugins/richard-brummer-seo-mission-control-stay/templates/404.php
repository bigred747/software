<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();
$q = new WP_Query(
	array(
		'post_type'           => array( 'product', 'post' ),
		'post_status'         => 'publish',
		'posts_per_page'      => 8,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>
<main class="rbsmc-stay-404" style="max-width:920px;margin:40px auto;padding:0 20px;">
	<p class="rbsmc-stay-kicker">Page not found</p>
	<h1>This URL is gone. Keep shopping on LuxeTrendsetters.</h1>
	<p>The link you used is not a live page. Search or open a current buyer guide instead of leaving after one second.</p>
	<?php get_search_form(); ?>
	<?php if ( $q->have_posts() ) : ?>
		<h2>Open a live guide</h2>
		<ul>
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				?>
				<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
			<?php endwhile; ?>
		</ul>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Return to the homepage</a></p>
</main>
<?php
wp_reset_postdata();
get_footer();
