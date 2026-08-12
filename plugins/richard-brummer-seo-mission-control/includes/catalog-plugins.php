<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility catalog: 38 named plugins from the LuxeTrendsetters stack.
 *
 * @return array
 */
function rbsmc_plugin_catalog() {
	return array(
		array( 'slug' => 'woocommerce/woocommerce.php', 'name' => 'WooCommerce', 'owner' => 'woocommerce' ),
		array( 'slug' => 'seo-by-rank-math/rank-math.php', 'name' => 'Rank Math SEO', 'owner' => 'rank-math' ),
		array( 'slug' => 'google-site-kit/google-site-kit.php', 'name' => 'Site Kit by Google', 'owner' => 'site-kit' ),
		array( 'slug' => 'wordfence/wordfence.php', 'name' => 'Wordfence Security', 'owner' => 'wordfence' ),
		array( 'slug' => 'litespeed-cache/litespeed-cache.php', 'name' => 'LiteSpeed Cache', 'owner' => 'litespeed' ),
		array( 'slug' => 'nextend-facebook-connect/nextend-facebook-connect.php', 'name' => 'Nextend Social Login', 'owner' => 'flatsome-optional' ),
		array( 'slug' => 'luxe-hard-rescue-admin-cleaner/luxe-hard-rescue-admin-cleaner.php', 'name' => 'Luxe Hard Rescue Admin Cleaner', 'owner' => 'luxe-rescue' ),
		array( 'slug' => 'luxe-reader-love-unified/luxe-reader-love-unified.php', 'name' => 'Luxe Reader-Love Unified 95–100 Prep & Score', 'owner' => 'luxe-reader-love' ),
		array( 'slug' => 'luxe-keyword-intelligence-autopilot/luxe-keyword-intelligence-autopilot.php', 'name' => 'Luxe Keyword Intelligence Autopilot', 'owner' => 'luxe-keywords' ),
		array( 'slug' => 'luxe-performance-link-guardian-suite/luxe-performance-link-guardian-suite.php', 'name' => 'Luxe Performance Link Guardian Suite', 'owner' => 'luxe-links' ),
		array( 'slug' => 'luxe-unified-site-guardian/luxe-unified-site-guardian.php', 'name' => 'Luxe Unified Site Guardian', 'owner' => 'luxe-guardian' ),
		array( 'slug' => 'luxe-ai-readiness-autopilot/luxe-ai-readiness-autopilot.php', 'name' => 'Luxe AI Readiness Autopilot', 'owner' => 'luxe-ai' ),
		array( 'slug' => 'luxe-amazon-bridge/luxe-amazon-bridge.php', 'name' => 'Luxe Amazon Bridge', 'owner' => 'luxe-amazon' ),
		array( 'slug' => 'luxe-rank-math-schema-guard/luxe-rank-math-schema-guard.php', 'name' => 'Luxe Rank Math Schema Guard', 'owner' => 'luxe-schema' ),
		array( 'slug' => 'luxe-amazon-disclosure/luxe-amazon-disclosure.php', 'name' => 'Luxe Amazon Disclosure', 'owner' => 'luxe-amazon' ),
		array( 'slug' => 'yith-woocommerce-points-and-rewards/init.php', 'name' => 'YITH WooCommerce Points and Rewards', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-coupon-email-system/init.php', 'name' => 'YITH WooCommerce Coupon Email System', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-product-add-ons/init.php', 'name' => 'YITH WooCommerce Product Add-ons', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-minimum-maximum-quantity/init.php', 'name' => 'YITH WooCommerce Minimum Maximum Quantity', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-custom-order-status/init.php', 'name' => 'YITH WooCommerce Custom Order Status', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-product-countdown/init.php', 'name' => 'YITH WooCommerce Product Countdown', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-color-label-variations/init.php', 'name' => 'YITH WooCommerce Color, Image Label Variation Swatches', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-frequently-bought-together/init.php', 'name' => 'YITH WooCommerce Frequently Bought Together', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-product-image-zoom/init.php', 'name' => 'YITH WooCommerce Product Gallery & Image Zoom', 'owner' => 'yith' ),
		array( 'slug' => 'yith-woocommerce-compare/init.php', 'name' => 'YITH WooCommerce Compare', 'owner' => 'yith' ),
		array( 'slug' => 'woocommerce-paypal-payments/woocommerce-paypal-payments.php', 'name' => 'WooCommerce PayPal Payments', 'owner' => 'woocommerce' ),
		array( 'slug' => 'jetpack/jetpack.php', 'name' => 'Jetpack', 'owner' => 'manual-review' ),
		array( 'slug' => 'contact-form-7/wp-contact-form-7.php', 'name' => 'Contact Form 7', 'owner' => 'forms' ),
		array( 'slug' => 'easy-table-of-contents/easy-table-of-contents.php', 'name' => 'Easy Table of Contents', 'owner' => 'content' ),
		array( 'slug' => 'wordpress-seo/wp-seo.php', 'name' => 'Yoast SEO', 'owner' => 'overlap-with-rank-math' ),
		array( 'slug' => 'redirection/redirection.php', 'name' => 'Redirection', 'owner' => 'overlap-with-rank-math' ),
		array( 'slug' => 'duplicate-post/duplicate-post.php', 'name' => 'Yoast Duplicate Post', 'owner' => 'editorial' ),
		array( 'slug' => 'classic-editor/classic-editor.php', 'name' => 'Classic Editor', 'owner' => 'editorial' ),
		array( 'slug' => 'regenerate-thumbnails/regenerate-thumbnails.php', 'name' => 'Regenerate Thumbnails', 'owner' => 'media' ),
		array( 'slug' => 'safe-svg/safe-svg.php', 'name' => 'Safe SVG', 'owner' => 'media' ),
		array( 'slug' => 'query-monitor/query-monitor.php', 'name' => 'Query Monitor', 'owner' => 'diagnostics' ),
		array( 'slug' => 'wp-crontrol/wp-crontrol.php', 'name' => 'WP Crontrol', 'owner' => 'diagnostics' ),
		array( 'slug' => 'richard-brummer-seo-mission-control/richard-brummer-seo-mission-control.php', 'name' => 'Richard Brummer SEO Mission Control', 'owner' => 'self' ),
	);
}
