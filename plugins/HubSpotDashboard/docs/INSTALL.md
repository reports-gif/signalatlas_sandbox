# HubSpotDashboard v9 - Advanced Revenue Intelligence

## What this plugin shows

This Matomo / SignalAtlas plugin is a read-only HubSpot revenue intelligence dashboard.
It includes:

- Executive KPI cards
- MQL / SQL visibility
- High-intent account scoring
- Source-to-revenue attribution
- Top intent pages
- CRM data quality gaps
- HubSpot company records
- Contact journey drill-down by email
- Optional activity timeline when activity permissions are available

## Required HubSpot private app scopes

Use minimum read-only scopes first:

```text
crm.objects.contacts.read
crm.objects.companies.read
crm.objects.deals.read
crm.objects.owners.read
```

Optional only if visible in your HubSpot account:

```text
sales-email-read
crm.objects.calls.read
crm.objects.meetings.read
crm.objects.notes.read
crm.objects.tasks.read
```

If activity scopes are not visible, skip them. The dashboard will still work with contact, company, deal, and source data.

## Matomo config

Add this to `config/config.ini.php`:

```ini
[HubSpotDashboard]
hubspot_token = "YOUR_HUBSPOT_PRIVATE_APP_TOKEN"
cache_ttl_seconds = 900
max_hubspot_records = 500
matomo_visit_limit = 100
hubspot_timeout_seconds = 25
```

## Install

Extract the ZIP so the folder is exactly:

```text
plugins/HubSpotDashboard
```

Then run:

```bash
cd /path/to/matomo
rm -rf tmp/cache/*
php console plugin:activate HubSpotDashboard
rm -rf tmp/cache/*
```

## Widget

Add widget:

```text
Dashboard -> Add a widget -> Dashboard -> SignalAtlas Revenue Intelligence
```

For best visual result, place this widget in a full-width dashboard row and remove long raw widgets such as real-time visits, top IPs, channel tables, and visit logs from the executive dashboard.

## Contact journey URL

Example:

```text
/index.php?module=HubSpotDashboard&action=journey&email=name@example.com
```
