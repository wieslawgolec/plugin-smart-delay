# MauticSmartDelayBundle

**Smart Delay (Send-Time Optimization) for Mautic 7+**

Campaign action that defers execution until each contact’s historically most engaged hour of day, plus a GrapesJS block for alternative subject-line A/B testing and a daily batch analysis CLI command.

**Current version: 1.0.0**

## Features

- **Smart Delay campaign action** – calculates optimal send hour from `audit_log` engagement (email opens / page hits) and calls `deferExecution()` so Mautic queues the event for later
- **Configurable fallback hour** and minimum interaction threshold via form type
- **GrapesJS A/B Subject Line block** – registers via `window.MauticGrapesJsPlugins` for email-html / email-mjml builders
- **CLI batch analyser** – `mautic:smartdelay:analyze` pre-computes optimal hours for high-volume installs
- **PHPUnit 11.5 suite** covering executor, subscriber, form type, command and bundle
- **GitHub Actions** matrix: PHP **8.2 / 8.5 / 8.6**

## Requirements

- Mautic **7.x** (Symfony 7)
- PHP **8.1+**

## Installation

```text
1. Copy the plugin folder into `plugins/` as `MauticSmartDelayBundle`:

plugins/
   └── MauticSmartDelayBundle/

2. Log in to Mautic admin → Settings → Plugins
   → Click Install/Upgrade Plugins
   → The bundle should appear → install it

3. Clear cache:
```

```bash
php bin/console cache:clear
```

## Campaign usage

1. Open the Campaign Builder.
2. Add a new **Action** → **Smart Delay (Send-Time Optimization)**.
3. Optionally set:
   - **Fallback hour (UTC)** – used when a contact has no engagement history (default `09:00`).
   - **Minimum interactions** – reserved for future filtering (default `3`).
4. Connect any subsequent actions (e.g. Send Email). When a contact reaches the Smart Delay node the executor:

   - Queries `audit_log` for the hour with the highest interaction count.
   - Computes the next occurrence of that hour in UTC.
   - Calls `$event->deferExecution($target)` so Mautic moves the entry to the scheduled/background queue.

## GrapesJS A/B Subject block

The asset `Assets/js/grapesjs-ab-subject.js` registers a block named **A/B Subject Line** under the “Smart Delay” category for `email-html` and `email-mjml` contexts.

Ensure the script is loaded (e.g. via an AssetSubscriber or by including it in your theme). It pushes itself onto `window.MauticGrapesJsPlugins` following the pattern introduced in Mautic core.

## Daily batch analysis

For high-throughput installations avoid a live multi-row query on every campaign trigger. Run the analyser once per day:

```bash
# Dry-run (compute only)
php bin/console mautic:smartdelay:analyze --dry-run

# Persist results (requires optional table – see below)
php bin/console mautic:smartdelay:analyze --days=90 --batch-size=500
```

Cron example:

```cron
0 3 * * * php /path/to/mautic/bin/console mautic:smartdelay:analyze --no-interaction
```

### Optional summary table

```sql
CREATE TABLE IF NOT EXISTS smartdelay_optimal_hours (
    lead_id       INT UNSIGNED NOT NULL PRIMARY KEY,
    optimal_hour  TINYINT UNSIGNED NOT NULL,
    updated_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

If the table is absent the command still succeeds and the live-query path remains the default.

## Testing

```bash
cd plugins/MauticSmartDelayBundle   # or the cloned repo root
composer install
vendor/bin/phpunit
```

CI runs on **PHP 8.2, 8.5 and 8.6** with PHPUnit **11.5**.

## Architecture (Mautic 7 / Symfony 7)

```text
MauticSmartDelayBundle/
├── Config/
│   ├── config.php          # Plugin metadata
│   └── services.php        # Autowiring + autoconfigure
├── DependencyInjection/
│   └── MauticSmartDelayExtension.php
├── EventListener/
│   └── CampaignSubscriber.php   # CAMPAIGN_ON_BUILD + trigger action
├── Execution/
│   └── SmartDelayExecutor.php   # Optimal-hour math + deferExecution
├── Form/Type/
│   └── SmartDelayType.php
├── Command/
│   └── AnalyzeEngagementCommand.php
├── Assets/js/
│   └── grapesjs-ab-subject.js
├── Translations/en_US/
│   └── messages.ini
└── tests/
```

Key modernisations versus legacy `config.php` service arrays:

- Extension + `services.php` with Symfony autowiring
- `CampaignBuilderEvent` + dedicated `eventName` instead of a raw callback
- `CampaignExecutionEvent::deferExecution()` for queue-friendly delays
- PHPUnit 11 + GitHub Actions matrix matching `plugin-filesystem-queue`

## Support the project

If this plugin saves you time, you can support development:

- **GitHub Sponsors:** [github.com/sponsors/wieslawgolec](https://github.com/sponsors/wieslawgolec)
- **Buy Me a Coffee:** [buymeacoffee.com/wieslawgolec](https://buymeacoffee.com/wieslawgolec)

Use the **Sponsor** button on this repository for the same links.

## License

MIT

## Author

Wieslaw Golec

Feel free to contribute or report issues.
