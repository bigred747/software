# Luxe Google Presence 1.2.0

Install beside Luxe Score Repair, Luxe Affiliate Product Scout, and Luxe Product Integrity Repair.

## Install

The WordPress plugin name is **Luxe Google Presence**.

1. Download `luxe-google-presence.zip` from the top of the repository. It is the 145KB package WordPress can unpack. Do not unzip it.
2. On an iPhone, open the zip link in Safari and save the file to Files. Do not unzip it. Then open the site’s browser admin (the WordPress app’s plugin screen often has no zip upload): Plugins → Add New → Upload Plugin → choose that zip → Install Now → Activate.
3. Open **Tools → Luxe Google Presence**.
4. Confirm every plugin-contract row is green and the contract score is 100.
5. Purge the LiteSpeed cache once so `/item-weight/` pages pick up the redirect and the next anonymous visit can be stored.
6. Click **Scan homepage on computer and mobile**. Required rows should be green. The full price disclaimer turns green after the compare block is on the public page, and the live score moves from 95 to 100.

If the upload is refused, delete a leftover `wp-content/plugins/luxe-google-presence` folder and upload `luxe-google-presence.zip` again.

Do not upload this zip over Score Repair, Product Scout, or Product Integrity. It uses its own folder, `luxe-google-presence`.

## After activation

- Computer and mobile use the same HTML. The stylesheet only changes layout.
- Product pages show compare links and the Associate disclosure just before the existing Amazon button.
- Amazon hrefs are not rewritten. `rel="sponsored"` is added for Google.
- In Google Search Console, submit `https://luxetrendsetters.com/sitemap_index.xml`.

## Safety

- Never writes `post_content` or post status
- Never writes Product Scout scores or Product Integrity taxonomies
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirects or a replacement sitemap
- Never buys clicks, opens pop-unders, or redirects to Amazon without a click
- Never flushes LiteSpeed on a public page view
