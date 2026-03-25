# CLAUDE.md — ap.ece.moe.edu.tw

## Project overview

This is the **data repo** for the Taiwan MOE preschool map project. It contains raw scraped data, processed JSON/CSV, and the static frontend site served from `docs/`.

All data in this repo is generated automatically by scripts in the sibling repo `ap.ece.moe.edu.tw_scripts`. Do not manually edit generated files.

## Repo structure

```
docs/                   ← static site root (GitHub Pages)
  preschools.json       ← GeoJSON FeatureCollection of all preschools
  punish_all.json       ← all punishment records indexed by person
  kids_vehicles.json    ← shuttle vehicle data matched to preschools
  data/
    *.csv               ← preschool basic info by city (from crawler)
    features/           ← individual GeoJSON Feature per preschool
    punish/             ← punishment JSON by city/school
    slip114/            ← structured fee data (current year)
    summary1/           ← monthly fee summaries by city/age
    id/id.csv           ← persistent UUID ↔ preschool mapping

raw/                    ← raw scraped/downloaded source data
  map/                  ← MOE map API JSON by area code
  punish/               ← raw HTML punishment pages by city
  slip114/              ← downloaded fee slip HTML pages
  geocoding/            ← cached geocoding results
  kids_vehicles.csv     ← raw vehicle open data

git_preschools/         ← external preschool reference data
_config.php             ← local config (TGOS API credentials, gitignored)
```

## Important files

- `docs/preschools.json` — the main output file; a GeoJSON FeatureCollection rebuilt by `03_geocoding.php` on every pipeline run.
- `docs/data/id/id.csv` — maps persistent UUIDs to preschools. Never delete this file — UUIDs are stable identifiers used by external systems.
- `_config.php` — holds TGOS geocoding API credentials. **Not tracked in git.** Must exist locally for geocoding to work.

## `_config.php` format

```php
<?php
return [
    'tgos' => [
        'url' => 'https://addr.tgos.tw/addrws/v40/QueryAddr.asmx/QueryAddr',
        'APPID' => '',   // fill in
        'APIKey' => '',  // fill in
    ],
];
```

## Automated updates

This repo is updated automatically by `cron.php` in the scripts repo:
- Daily `git pull` → data collection → `git add -A` → `git commit` → `git push`
- Commit author is `auto commit <noreply@localhost>`

Do not manually push to `master` during a pipeline run.

## What NOT to do

- Do not manually edit files under `docs/data/`, `docs/preschools.json`, `docs/punish_all.json`, `raw/` — these are fully generated.
- Do not delete `docs/data/id/id.csv` — it contains persistent UUIDs.
- Do not add scripts here — scripts belong in `ap.ece.moe.edu.tw_scripts`.
