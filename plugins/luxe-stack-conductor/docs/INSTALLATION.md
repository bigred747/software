# Luxe Stack Conductor 1.0.0

Upload the zip from the repository `dist/` folder:

`dist/Luxe-Stack-Conductor-v1.0.0.zip`

Plugins → Add Plugin → Upload Plugin → Install Now → Activate.

Then open **Luxe Conductor**. Leave the other plugins active. This plugin does not publish, does not change post status, does not rewrite Amazon URLs, and does not deactivate anything.

On Hostinger, add a cron job every 5 minutes that requests `https://luxetrendsetters.com/wp-cron.php`. Without that, WordPress only learns when a visitor or an admin screen wakes it.

A green row means that process passed. Yellow and red stay visible until the underlying check passes.
