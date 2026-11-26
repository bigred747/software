# Luxe Mission Score Repair 1.0.0

Upload `dist/Luxe-Mission-Score-Repair-v1.0.0.zip` in Plugins → Add Plugin → Upload Plugin. Activate it. Open Tools → Luxe Mission Score.

The plugin does not publish, does not change post status, and does not rewrite `amazon.com` or `amzn.to` URLs.

## What the live 93 was

Mission Control 1.8.0 counted 38 verified greens, 2 yellows, and 2 reds. Manual and info rows are excluded. Half credit for a yellow and none for a red is 93/100: `(38 + 1) / 42 * 100 = 92.86`, which rounds to 93.

The four scored rows are:

1. Rendered homepage output budget — yellow. The live homepage was 260,204 bytes, 44 scripts, 2 stylesheets.
2. WooCommerce template overrides — yellow. WooCommerce 11.2.0 changed exactly five templates that Flatsome 3.20.11 still overrides from the 11.1 generation: `cart/cart.php`, `cart/mini-cart.php`, `cart/cart-shipping.php`, `checkout/thankyou.php`, `single-product-reviews.php`.
3. Amazon affiliate batch — red. One rendered post or product fails because a Pinterest share URL embeds `m.media-amazon.com`, and product galleries link the full image to that CDN. The real `amazon.com/dp` links already have `sponsored`, `nofollow`, and `tag=luxetrendse0f-20`.
4. Dashboard automation — red. That worker stays red while a verified red or yellow check remains. This plugin does not set it green by itself.

## After activation

Measured on the saved homepage HTML: 197,719 bytes and 39 scripts, still 2 stylesheets, still 16 affiliate links, still 2 JSON-LD blocks. A review page goes from 1 bad Amazon-looking link to 0. A product page goes from 3 to 0, and its affiliate links stay.

If a theme override does not contain the WooCommerce lines this merge knows, that file is listed as left unchanged and its `@version` is not bumped.
