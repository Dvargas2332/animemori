# Animemori (Core Plugin + Theme)

This repository contains:
- `animemori-core` (WordPress plugin): data model, importer, scheduling, routes, SEO helpers.
- `animemori-theme` (WordPress theme): templates and UI for schedule/upcoming/anime pages.

The repo is safe to publish: it does not include credentials, tokens, IPs, ad network IDs, or hard-coded private domain dependencies.

## Requirements
- WordPress 6.0+
- PHP 8.0+
- MySQL/MariaDB (standard WordPress DB)

## What The Core Plugin Provides
- Custom tables (anime, seasons, episodes, broadcast schedule, characters, relations).
- Importer and refresh jobs (uses a public API).
- Episode scheduler and cron automation (keeps a future buffer for the calendar).
- Public pages and routes:
  - `/schedule`
  - `/schedule/{monday|tuesday|...}`
  - `/upcoming`
  - `/anime/{slug}`
- JSON-LD output for key pages.
- A basic sitemap endpoint:
  - `/animemori-sitemap.xml`

## Build ZIPs (Windows -> Linux-safe)
Important: if you deploy ZIPs to a Linux server, build them using `tar -a` so the archive contains normalized `/` paths.
This prevents the known failure mode where files are created with literal `\\` in their names (example: `includes\\db.php`) and the plugin cannot activate.

Build both plugin + theme ZIPs:

```powershell
powershell -ExecutionPolicy Bypass -File .\\scripts\\build-release.ps1
```

Outputs:
- `animemori-core.zip` (contains folder `animemori-core/`)
- `animemori-theme.zip` (contains folder `animemori-theme/`)

## Install (WP Admin)
1. Upload `animemori-core.zip` in WP Admin -> Plugins -> Add New -> Upload Plugin.
2. Activate **Animemori Core**.
3. Upload `animemori-theme.zip` in WP Admin -> Appearance -> Themes -> Add New -> Upload Theme.
4. Activate the theme.
5. In WP Admin, open the **Animemori** menu to import/schedule content.

## Install / Update (Bitnami / SSH)
Use WP-CLI to avoid folder renames like `-1`, `-update`, and broken activation links.

```bash
sudo -i
WP_PATH=/opt/bitnami/wordpress
WP=/opt/bitnami/wp-cli/bin/wp

# Plugin
rm -rf "$WP_PATH/wp-content/plugins/animemori-core"*
$WP plugin install /tmp/animemori-core.zip --force --activate --path="$WP_PATH" --allow-root

# Theme
rm -rf "$WP_PATH/wp-content/themes/animemori-theme"*
$WP theme install /tmp/animemori-theme.zip --force --activate --path="$WP_PATH" --allow-root

$WP rewrite flush --hard --path="$WP_PATH" --allow-root
$WP cache flush --path="$WP_PATH" --allow-root
```

## Configuration Notes
- Import endpoints are configurable in the admin screens/settings and/or by WordPress filters.
- Do not commit API tokens to this repo. Use environment variables or WordPress options stored on the server.

## Troubleshooting
### “Plugin file does not exist”
This usually means WordPress is trying to activate the wrong folder slug (for example `animemori-core-update-*`).
The correct file path must be:

`wp-content/plugins/animemori-core/animemori-core.php`

### “Failed opening required …/includes/db.php”
This indicates the plugin was unpacked with broken paths (files containing `\\` in the filename).
Rebuild the ZIP with `scripts/build-release.ps1` and reinstall using WP-CLI `--force`.

## Tests (CI)
This repo includes lightweight automated checks:
- PHP syntax lint for all PHP files (`php -l`).
- Sensitive string scan (ad IDs, vendor script URLs, and other accidental leaks).

See `.github/workflows/ci.yml`.

