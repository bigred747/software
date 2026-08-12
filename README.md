# LuxeTrendsetters website software

Admin plugins for [luxetrendsetters.com](https://luxetrendsetters.com/). They do not auto-publish, rewrite stored posts, or run content cron.

## Fix the 1-second look

Site Kit 1s on `/product-tag/apple/` and `/brand/dji/` is Guest Mode reload plus thin archives. Stay Repair 1.9.5 stops the reload and adds a real five-minute compare hub on those pages. Site Kit is a 28-day average and will not show 5 minutes today.

### Pack (one download)

File: `dist/Luxe-Dashboard-Fix-v1.1.1.zip`

SHA-256: `47591c8d6504c556138981508736e321849c4bd57a6ee4ffcc889c3573155a1d`

Direct download: https://github.com/bigred747/software/raw/cursor/operator-dashboard-9b7a/dist/Luxe-Dashboard-Fix-v1.1.1.zip

Unzip, then upload in **Plugins → Add Plugin → Upload Plugin**:

1. `Luxe-Operator-Dashboard-v1.1.1.zip` — SHA-256 `51e1dcd21921a32dab40b43ae4374ad01f33bfd1cef1471795398ca83417cabc`
2. `Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.5.zip` — SHA-256 `75e4ae63b8423d831b3b7fe218dd90c85fe74f781aeca087c07642047356eaf3` (over Stay Repair, not Mission Control)

Then: LiteSpeed Guest Mode OFF, Crawler OFF, Purge All. Open `/product-tag/apple/` logged out and browse the compare hub.

Do not connect AdSense or Reader Revenue Manager. Do not use back-button traps.
