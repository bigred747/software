<?php
/**
 * php plugins/luxe-mission-score-repair/tests/test-repair.php
 */
define( 'ABSPATH', __DIR__ );

require dirname( __DIR__ ) . '/includes/class-repair.php';

$fail = 0;

/**
 * @param bool   $ok Pass.
 * @param string $msg Label.
 */
function expect( $ok, $msg ) {
	global $fail;
	if ( $ok ) {
		echo "OK  $msg\n";
		return;
	}
	$fail++;
	echo "FAIL $msg\n";
}

$dp = 'https://www.amazon.com/dp/B0F7PS35R2?tag=luxetrendse0f-20';
expect( $dp === Luxe_MSR_Repair::repair_href( $dp ), 'amazon.com retail URL is not rewritten' );

$short = 'https://amzn.to/abc123';
expect( $short === Luxe_MSR_Repair::repair_href( $short ), 'amzn.to URL is not rewritten' );

$pin = 'https://pinterest.com/pin/create/button?url=https://luxetrendsetters.com/review/&media=https://m.media-amazon.com/images/I/51Vc6CWcxAL._SS1024_.jpg&description=Buyer%20Guide';
$fixed_pin = Luxe_MSR_Repair::repair_href( $pin );
expect( false === strpos( $fixed_pin, 'amazon' ), 'pinterest share drops the embedded Amazon image' );
expect( false !== strpos( $fixed_pin, 'url=https://luxetrendsetters.com/review/' ), 'pinterest share keeps the site URL' );
expect( false !== strpos( $fixed_pin, 'description=Buyer%20Guide' ), 'pinterest share keeps the description' );

$encoded = 'https://pinterest.com/pin/create/button?url=https%3A%2F%2Fluxetrendsetters.com%2Freview%2F&media=https%3A%2F%2Fm.media-amazon.com%2Fimages%2FI%2F51.jpg';
expect( false === strpos( Luxe_MSR_Repair::repair_href( $encoded ), 'amazon' ), 'encoded Amazon media query is removed' );

$image = 'https://m.media-amazon.com/images/I/41xVsWSQxkL.jpg';
expect( '#' === Luxe_MSR_Repair::repair_href( $image ), 'Amazon image CDN anchor is no longer an Amazon URL' );

$html = '<a href="' . $image . '"><img data-large_image="' . $image . '" alt="Bag"></a>'
	. '<a class="button" href="' . $dp . '" rel="nofollow sponsored">Amazon</a>'
	. '<a href="' . $pin . '" rel="noopener nofollow">Pin</a>';
$out = Luxe_MSR_Repair::repair_links( $html );
expect( false === strpos( $out, 'href="https://m.media-amazon.com' ), 'gallery href is not an Amazon image URL' );
expect( false !== strpos( $out, 'data-large_image="' . $image . '"' ), 'gallery zoom URL stays on the image' );
expect( false !== strpos( $out, 'href="' . $dp . '"' ), 'affiliate button href is unchanged' );
expect( false === strpos( $out, 'rel="nofollow sponsored sponsored"' ), 'affiliate rel is not duplicated' );
expect( false === strpos( $out, 'media=https://m.media-amazon.com' ), 'rendered pin href has no Amazon media URL' );

$cart = <<<'PHP'
<?php
 * @version 11.0.0
$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
echo wc_get_formatted_cart_item_data( $cart_item );
PHP;
$patched = Luxe_MSR_Repair::patch_template( 'cart/cart.php', $cart, '11.2.0' );
expect( ! empty( $patched['ready'] ) && ! empty( $patched['changed'] ), 'cart template merge is applied' );
expect( false !== strpos( $patched['code'], 'get_item_product_name' ), 'cart uses get_item_product_name' );
expect( false !== strpos( $patched['code'], 'wc_get_formatted_cart_item_data( $cart_item, false, $cart_item_name )' ), 'cart item data receives the resolved name' );
expect( false === strpos( $patched['code'], '$_product->get_name()' ), 'old cart name calls are gone' );
expect( false !== strpos( $patched['code'], '@version 11.2.0' ), 'cart @version updates only with the new behavior' );

$again = Luxe_MSR_Repair::patch_template( 'cart/mini-cart.php', $patched['code'], '11.2.0' );
expect( empty( $again['changed'] ), 'a second cart merge does not rewrite the file' );

$custom = <<<'PHP'
<?php
 * @version 8.0.0
echo flatsome_custom_cart_row();
PHP;
$left = Luxe_MSR_Repair::patch_template( 'cart/cart.php', $custom, '11.2.0' );
expect( empty( $left['changed'] ) && false !== strpos( $left['code'], '@version 8.0.0' ), 'divergent cart override is not version-stamped' );

$future = Luxe_MSR_Repair::patch_template( 'cart/cart.php', $cart, '11.3.0' );
expect( ! empty( $future['ready'] ) && false === strpos( $future['code'], '@version 11.3.0' ) && false !== strpos( $future['code'], '@version 11.0.0' ), 'newer core than the known merge is not stamped' );
expect( false !== strpos( $future['code'], 'get_item_product_name' ), '11.2 name behavior is still merged when core is newer' );

$ship = 'printf( \'<label for="shipping_method_%1$s_%2$s">%3$s</label>\', $index );' . "\n * @version 8.8.0\n";
$shipped = Luxe_MSR_Repair::patch_template( 'cart/cart-shipping.php', $ship, '11.2.0' );
expect( false !== strpos( $shipped['code'], 'for="shipping_method_%1$d_%2$s"' ), 'shipping label index is an integer' );
expect( false !== strpos( $shipped['code'], '@version 11.2.0' ), 'shipping @version follows the integer label' );

$thanks = <<<'PHP'
<?php
 * @version 8.1.0
<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Custom decline text.', 'woocommerce' ); ?></p>
PHP;
$thanked = Luxe_MSR_Repair::patch_template( 'checkout/thankyou.php', $thanks, '11.2.0' );
expect( false !== strpos( $thanked['code'], 'woocommerce_thankyou_order_failed_text' ), 'failed-order message is filterable' );
expect( false !== strpos( $thanked['code'], 'Custom decline text.' ), 'existing thank-you copy is preserved' );
expect( false !== strpos( $thanked['code'], '@version 11.2.0' ), 'thank-you @version follows the filter' );

$reviews = <<<'PHP'
<?php
 * @version 9.7.0
$reviews_title = sprintf( esc_html( _n( '%1$s review for %2$s', '%1$s reviews for %2$s', $count, 'woocommerce' ) ), esc_html( $count ), '<span>' . get_the_title() . '</span>' );
PHP;
$reviewed = Luxe_MSR_Repair::patch_template( 'single-product-reviews.php', $reviews, '11.2.0' );
expect( false !== strpos( $reviewed['code'], 'wp_kses_post( get_the_title() )' ), 'review title is escaped' );
expect( false !== strpos( $reviewed['code'], '@version 11.2.0' ), 'reviews @version follows the escape' );

$home = '<html><head><title>Home</title>'
	. '<style id="global-styles-inline-css">.has-black-color{color:#000}</style>'
	. '<style id="wp-block-library-inline-css">.screen-reader-text{clip:rect(1px,1px,1px,1px)}</style>'
	. '<style id="luxe-wow-learning-homepage-inline-css">.luxe-wow{color:#111}</style>'
	. '<script id="jquery-core-js" src="https://luxetrendsetters.com/jquery.js"></script>'
	. '<script id="wflsi18njs-js-extra" src="data:text/javascript;base64,dmFyIFdvcmRmZW5jZQ=="></script>'
	. '<script id="thickbox-js-extra">var thickboxL10n={};</script>'
	. '<script type="text/javascript" src="data:text/javascript;base64,' . base64_encode( "jQuery('.woocommerce-tabs #tab-description .aplus p img[height=1]').css({'height':'1px'})" ) . '" defer></script>'
	. '<script type="application/ld+json">{"@type":"WebSite"}</script>'
	. '</head><body class="home"><a class="skip-link screen-reader-text" href="#main">Skip</a>'
	. '<a href="' . $dp . '" rel="nofollow sponsored">Shop</a></body></html>';
$trimmed = Luxe_MSR_Repair::repair_document( $home, true );
expect( false === strpos( $trimmed, 'wflsi18njs-js-extra' ), 'homepage drops the Wordfence login translation catalog' );
expect( false === strpos( $trimmed, 'thickbox-js-extra' ), 'homepage drops Thickbox' );
expect( false === strpos( $trimmed, 'global-styles-inline-css' ), 'homepage drops unused global styles' );
expect( false === strpos( $trimmed, 'woocommerce-tabs #tab-description' ), 'homepage drops the tab-image no-op' );
expect( false !== strpos( $trimmed, 'jquery-core-js' ), 'homepage keeps jQuery' );
expect( false !== strpos( $trimmed, 'luxe-wow-learning-homepage-inline-css' ), 'homepage keeps the storefront CSS' );
expect( false !== strpos( $trimmed, 'application/ld+json' ), 'homepage keeps JSON-LD' );
expect( false !== strpos( $trimmed, 'id="luxe-msr-skip-link"' ), 'skip link keeps a screen-reader rule' );
expect( false !== strpos( $trimmed, $dp ), 'homepage affiliate URL stays intact' );
$before = Luxe_MSR_Repair::budget( $home );
$after  = Luxe_MSR_Repair::budget( $trimmed );
expect( $after['scripts'] < $before['scripts'], 'homepage script count goes down' );
expect( $after['bytes'] < $before['bytes'], 'homepage HTML bytes go down' );
expect( 1 === $after['styles'] || $after['styles'] === $before['styles'], 'stylesheet links are not added' );

if ( $fail ) {
	echo "$fail failed\n";
	exit( 1 );
}
echo "all passed\n";
exit( 0 );
