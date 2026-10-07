# MauticSmartDelayBundle

**Smart Delay (Send-Time Optimization) for Mautic 7+**

A production-ready Mautic 7 plugin that adds a **Smart Delay** campaign action, a **GrapesJS A/B subject-line block**, and a **daily engagement analysis CLI command**. When a contact reaches the Smart Delay node, the plugin looks up their historical peak engagement hour and defers the rest of the campaign until that hour — so emails and follow-ups land when the contact is most likely to interact.

**Current version: 1.0.0** · **License: MIT** · **PHP 8.1+** · **Mautic 7.x (Symfony 7)**

---

## Table of contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Campaign usage](#campaign-usage)
5. [How the algorithm works](#how-the-algorithm-works)
6. [GrapesJS A/B Subject block](#grapesjs-ab-subject-block)
7. [Daily batch analysis (CLI)](#daily-batch-analysis-cli)
8. [Optional summary table](#optional-summary-table)
9. [Performance & production tips](#performance--production-tips)
10. [Architecture](#architecture)
11. [Testing & CI](#testing--ci)
12. [Troubleshooting](#troubleshooting)
13. [Changelog](#changelog)
14. [Support the project](#support-the-project)
15. [License & author](#license--author)

---

## Features

| Feature | Description |
|--------|-------------|
| **Smart Delay campaign action** | Defers campaign execution to the contact’s historically most engaged hour (UTC) |
| **Configurable form** | Fallback hour (default 09:00 UTC) and minimum-interactions hint |
| **Live query fallback** | Works out of the box against `audit_log`; no extra schema required |
| **Batch analyser CLI** | `mautic:smartdelay:analyze` pre-computes optimal hours for high-volume installs |
| **GrapesJS A/B Subject block** | Alternative subject-line variant block for email-html / email-mjml builders |
| **Asset auto-injection** | `AssetSubscriber` loads the GrapesJS script into the admin UI |
| **Symfony 7 / Mautic 7** | Autowiring, `services.php`, Extension class — no legacy service arrays |
| **PHPUnit 11.5 suite** | Executor, subscriber, form type, command, bundle, asset subscriber |
| **GitHub Actions** | Matrix: **PHP 8.2 / 8.5 / 8.6** |

---

## Requirements

- **Mautic 7.x** (tested against the 7.x Symfony 7 stack)
- **PHP 8.1+** (CI covers 8.2, 8.5, 8.6)
- MySQL / MariaDB (or compatible) with the standard Mautic `audit_log` table
- Write access to `plugins/` and ability to clear the Symfony cache

---

## Installation

### 1. Place the plugin

Copy the repository contents into Mautic’s plugins directory under the exact folder name expected by Composer’s `extra.mautic-plugin-name`:

```text
plugins/
└── MauticSmartDelayBundle/
    ├── Assets/
    ├── Command/
    ├── Config/
    ├── DependencyInjection/
    ├── EventListener/
    ├── Execution/
    ├── Form/
    ├── Translations/
    ├── MauticSmartDelayBundle.php
    ├── composer.json
    └── …
```

Alternatively, clone directly:

```bash
cd /path/to/mautic/plugins
git clone https://github.com/wieslawgolec/plugin-smart-delay.git MauticSmartDelayBundle
```

### 2. Install via the UI

1. Log in to Mautic admin → **Settings → Plugins**
2. Click **Install/Upgrade Plugins**
3. Locate **Smart Delay Bundle for Mautic 7+** and install/publish it

### 3. Clear cache

```bash
php bin/console cache:clear
# or, if you prefer a hard clear:
rm -rf var/cache/*
php bin/console cache:warmup
```

### 4. Verify the command is registered

```bash
php bin/console list mautic:smartdelay
# Expected: mautic:smartdelay:analyze
```

---

## Campaign usage

1. Open **Campaigns → New / Edit → Builder**.
2. Drag a new **Action** onto the canvas.
3. Choose **Smart Delay (Send-Time Optimization)**.
4. (Optional) set:
   - **Fallback hour (UTC)** — used when the contact has no engagement history (default `09:00`).
   - **Minimum interactions** — reserved for future filtering (default `3`).
5. Connect any subsequent actions (e.g. **Send Email**, webhook, tag update).

When a contact reaches the Smart Delay node during `mautic:campaigns:trigger`:

1. The executor queries `audit_log` for that contact’s peak interaction hour.
2. It computes the next occurrence of that hour in **UTC**.
3. It calls `$event->deferExecution($targetDateTime)` so Mautic moves the log entry into the scheduled/background queue.
4. The campaign resumes at the deferred time and continues with the next connected actions.

> **Tip:** Place Smart Delay *immediately before* the action you want time-optimised (usually Send Email). Multiple Smart Delay nodes in one campaign are supported.

---

## How the algorithm works

```text
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│ Contact reaches │────▶│ Query audit_log for  │────▶│ Peak hour found?    │
│ Smart Delay node│     │ HOUR(date_added) of  │     │                     │
└─────────────────┘     │ email + page events  │     └──────────┬──────────┘
                        └──────────────────────┘                │
                                                    yes ┌───────┴───────┐ no
                                                        ▼               ▼
                                              Use peak hour      Use fallback
                                              (0–23)             (default 9)
                                                        │               │
                                                        └───────┬───────┘
                                                                ▼
                                              target = today at hour:00 UTC
                                              if target ≤ now → +1 day
                                                                │
                                                                ▼
                                              event->deferExecution(target)
                                              event->setResult(true)
```

**Details:**

- Source table: `{prefix}audit_log`
- Filters: `lead_id = :id` and `bundle IN ('email', 'page')`
- Aggregation: `GROUP BY HOUR(date_added) ORDER BY COUNT(*) DESC LIMIT 1`
- Timezone for scheduling: **UTC** (Mautic’s campaign scheduler converts to the system / contact timezone as usual)
- On any DB error or empty result → fallback hour (9 by default)
- Invalid hour values outside 0–23 are clamped back to the fallback

---

## GrapesJS A/B Subject block

File: `Assets/js/grapesjs-ab-subject.js`

The script registers itself on `window.MauticGrapesJsPlugins` (the extension point introduced for custom GrapesJS plugins in Mautic). The `AssetSubscriber` injects the script into the admin UI automatically.

| Property | Value |
|----------|--------|
| Block id | `smartdelay-ab-subject` |
| Label | A/B Subject Line |
| Category | Smart Delay |
| Contexts | `email-html`, `email-mjml` |
| Trait | `data-variant` (default `B`) |

Editors can drop the block into an email and set the variant label. The block content is a lightweight HTML comment placeholder that can be expanded into a full subject-line A/B workflow in a future release.

---

## Daily batch analysis (CLI)

For high-throughput installations (tens/hundreds of thousands of contacts per trigger cycle), a live multi-row `audit_log` query on every contact can become a bottleneck. The analyser pre-computes the top hour per contact once per day.

```bash
# Dry-run — compute only, print summary
php bin/console mautic:smartdelay:analyze --dry-run

# Full run — look back 90 days, batch writes of 500
php bin/console mautic:smartdelay:analyze --days=90 --batch-size=500

# Short options
php bin/console mautic:smartdelay:analyze -d 30 -b 200 --dry-run
```

| Option | Short | Default | Description |
|--------|-------|---------|-------------|
| `--days` | `-d` | `90` | Look-back window in days |
| `--batch-size` | `-b` | `500` | Contacts per write batch |
| `--dry-run` | | off | Compute but do not persist |

**Cron example (03:00 server time daily):**

```cron
0 3 * * * php /path/to/mautic/bin/console mautic:smartdelay:analyze --no-interaction >> /var/log/mautic-smartdelay.log 2>&1
```

If the optional summary table does not exist, the command still succeeds and reports that results were computed but not persisted. The live-query path in `SmartDelayExecutor` remains the default and requires no schema change.

---

## Optional summary table

Create this table if you want the analyser to persist results (future executor versions can read from it for O(1) lookups):

```sql
CREATE TABLE IF NOT EXISTS smartdelay_optimal_hours (
    lead_id       INT UNSIGNED NOT NULL PRIMARY KEY,
    optimal_hour  TINYINT UNSIGNED NOT NULL,
    updated_at    DATETIME NOT NULL,
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

If you use a non-empty `MAUTIC_TABLE_PREFIX`, prefix the table name accordingly (e.g. `mau_smartdelay_optimal_hours`).

**Recommended index on `audit_log` for the live query path:**

```sql
-- Speeds up the per-contact peak-hour lookup
ALTER TABLE audit_log
  ADD INDEX idx_smartdelay_lead_bundle_date (lead_id, bundle, date_added);
```

(Adjust for your actual prefix and existing indexes.)

---

## Performance & production tips

1. **Prefer the batch analyser** on instances processing > ~50k campaign events/hour. Run it off-peak.
2. **Add the composite index** above so the live path stays cheap for contacts without a pre-computed row.
3. **Monitor** `mautic:campaigns:trigger` duration after enabling Smart Delay; deferral itself is lightweight (one datetime write).
4. **Timezone:** scheduling is computed in UTC. If your contacts are concentrated in one region, consider aligning the fallback hour with that region’s morning.
5. **Cold contacts:** contacts with no `email`/`page` audit entries always get the fallback hour — that is intentional so the campaign never blocks.
6. **Cache:** always `cache:clear` after install/upgrade so the new event subscriber and command are discovered.

---

## Architecture

```text
MauticSmartDelayBundle/
├── Assets/js/
│   └── grapesjs-ab-subject.js          # GrapesJS plugin registration
├── Command/
│   └── AnalyzeEngagementCommand.php    # mautic:smartdelay:analyze
├── Config/
│   ├── config.php                      # Plugin name, version, author
│   └── services.php                    # Autowire + autoconfigure
├── DependencyInjection/
│   └── MauticSmartDelayExtension.php   # Loads services.php
├── EventListener/
│   ├── AssetSubscriber.php             # Injects GrapesJS script
│   └── CampaignSubscriber.php          # CAMPAIGN_ON_BUILD + trigger
├── Execution/
│   └── SmartDelayExecutor.php          # Peak-hour math + deferExecution
├── Form/Type/
│   └── SmartDelayType.php              # Fallback hour + min interactions
├── Translations/en_US/
│   └── messages.ini
├── tests/
│   ├── Stub/                           # Mautic/Symfony stubs for CI
│   └── Unit/                           # PHPUnit 11.5 tests
├── MauticSmartDelayBundle.php
├── composer.json
├── phpunit.xml.dist
└── .github/workflows/tests.yml
```

### Mautic 7 modernisations (vs legacy plugins)

| Legacy approach | This plugin |
|-----------------|-------------|
| Service definitions in `config.php` arrays | `services.php` + Extension + autowiring |
| Raw `callback` on campaign action | Dedicated `eventName` + subscriber method |
| Blocking `sleep()` / busy-wait | `CampaignExecutionEvent::deferExecution()` |
| Manual asset includes | `AssetSubscriber` on `VIEW_INJECT_CUSTOM_ASSETS` |
| Ad-hoc test scripts | PHPUnit 11.5 + GitHub Actions matrix |

### Key classes

- **`CampaignSubscriber`** — listens to `CampaignEvents::CAMPAIGN_ON_BUILD` and `mautic.smartdelay.on_campaign_trigger_action`.
- **`SmartDelayExecutor`** — pure calculation + DB lookup; injectable and unit-tested in isolation.
- **`AnalyzeEngagementCommand`** — Symfony Console attribute command (`#[AsCommand]`).
- **`AssetSubscriber`** — ensures the GrapesJS block is available without theme edits.

---

## Testing & CI

### Local

```bash
cd plugins/MauticSmartDelayBundle   # or a clone of this repo
composer install
vendor/bin/phpunit
# or
composer test
```

Tests use lightweight stubs under `tests/Stub/` so they run **without a full Mautic installation**. Covered behaviour:

| Test class | What it verifies |
|------------|------------------|
| `SmartDelayExecutorTest` | Peak hour, fallback, exception path, same-day / next-day scheduling, `deferExecution` + `setResult` |
| `CampaignSubscriberTest` | Action registration, context guard, end-to-end trigger |
| `AnalyzeEngagementCommandTest` | Dry-run, top-hour aggregation, query failure, CLI options |
| `SmartDelayTypeTest` | Form fields, block prefix, options |
| `BundleTest` | `getPath()`, namespace |
| `AssetSubscriberTest` | Subscribed events, instantiability |

### Continuous integration

`.github/workflows/tests.yml` runs on every push / PR to `main` or `mautic7.x`:

- **OS:** `ubuntu-latest`
- **PHP matrix:** `8.2`, `8.5`, `8.6`
- **PHPUnit:** `^11.5`
- **fail-fast:** `false` (all matrix cells report independently)

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Action not listed in Campaign Builder | Plugin not installed or cache stale | Re-run Install/Upgrade Plugins + `cache:clear` |
| Command not found | Cache / autoconfigure | `php bin/console cache:clear` then `list mautic:smartdelay` |
| Always falls back to 09:00 | No `email`/`page` rows in `audit_log` for that contact | Expected for cold contacts; check tracking is enabled |
| Slow campaign trigger | Live query without index | Add the composite index; enable nightly `analyze` |
| GrapesJS block missing | Asset not loaded | Confirm `AssetSubscriber` is registered; hard-refresh browser |
| Table prefix mismatch | Custom `MAUTIC_TABLE_PREFIX` | Prefix is read from the constant; ensure it matches your DB |

---

## Changelog

### 1.0.0

- Initial public release for Mautic 7 / Symfony 7
- Smart Delay campaign action with `deferExecution()`
- Configurable fallback hour form type
- GrapesJS A/B subject-line block + AssetSubscriber
- `mautic:smartdelay:analyze` daily batch command
- PHPUnit 11.5 suite and GitHub Actions (PHP 8.2 / 8.5 / 8.6)
- Sponsorship links aligned with `plugin-filesystem-queue`

---

## Support the project

If this plugin saves you time, you can support development:

- **GitHub Sponsors:** [github.com/sponsors/wieslawgolec](https://github.com/sponsors/wieslawgolec)
- **Buy Me a Coffee:** [buymeacoffee.com/wieslawgolec](https://buymeacoffee.com/wieslawgolec)

Use the **Sponsor** button on this repository for the same links.

---

## License & author

**License:** MIT  
**Author:** Wieslaw Golec  

Issues and pull requests are welcome at [github.com/wieslawgolec/plugin-smart-delay](https://github.com/wieslawgolec/plugin-smart-delay).
