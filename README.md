# Animemori Core (v0.3.1)

This plugin turns WordPress into a structured anime tracking database.

## What you get (MVP)
- Custom DB tables for Anime, Broadcast schedule, Seasons, Episodes, Characters (relations ready)
- Free API importer (Jikan for MyAnimeList IDs): Animemori -> Import Anime
- Episode generator based on JST broadcast schedule: Animemori -> Generate Episodes
- Daily cron job keeps an 8-week buffer for schedule
- Daily auto-import (Jikan): upcoming, in-season, finished, cancelled (best-effort)
- Upcoming page: /upcoming
- SEO: JSON-LD for schedule, upcoming, and anime pages
- Sitemap: /animemori-sitemap.xml
- Pages:
  - /schedule
  - /schedule/{monday|tuesday|...}
  - /upcoming
  - /anime/{slug}

## Install
1) Upload ZIP in WP Admin -> Plugins -> Add New -> Upload Plugin
2) Activate
3) Go to WP Admin -> Animemori -> Import Anime
4) Import by MAL ID (example: 5114)
5) Generate episodes by internal anime_id (shown after import)

## Release / ZIP Build (Windows -> Linux-safe)
If you deploy to Linux (Bitnami, etc.), build the ZIPs with `tar -a` to ensure the archive contains normalized `/` paths.

```powershell
powershell -ExecutionPolicy Bypass -File .\\scripts\\build-release.ps1
```

## Notes / Limits
- Jikan is rate-limited. If imports fail, retry later.
- Auto-import limits (defaults): 20 per category, 1 page. Filter via `animemori_auto_import_settings`.
- ?Full minimal info? depends on what the free APIs expose. The plugin stores the entire raw payload JSON (am_anime.source_payload_json) to keep all available fields for future expansion without re-importing.
- Characters: automatic importer using AniList (admin: Animemori -> Characters). Includes daily auto-import for anime missing characters.
