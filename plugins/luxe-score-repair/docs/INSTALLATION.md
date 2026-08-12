# Luxe Score Repair 1.1.0

Safe public-output plugin for https://luxetrendsetters.com/ with process learning and a green-signal board.

## Install

1. Download `dist/Luxe-Score-Repair-v1.1.0.zip`.
2. Plugins → Add Plugin → Upload Plugin. Upload **over** 1.0.0 (same folder `luxe-score-repair`).
3. Open **Tools → Luxe Score Repair**.
4. Click **Run process learning now**.

Do **not** upload this zip over Mission Control or Stay Repair.

## What 1.1.0 does automatically

- Writes a short `robots.txt` to disk (Hostinger serves the physical file)
- 301s `/blog/` on `init` before Rank Math
- Purges LiteSpeed after heals
- Rechecks every 15 minutes
- Shows GREEN / RED for each process

## Safety

- Never writes `post_content`
- Never changes post status
- Never rewrites Amazon or product permalinks
- Never creates Rank Math redirect rows
