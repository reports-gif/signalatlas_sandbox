# CompanyFromIP

> **Warning**
>
> This plugin is experimental and was coded using [Claude Code](https://claude.ai).
> It is provided without any warranty regarding quality, stability, or performance.
> This is a community project and is not officially supported by Matomo.

## Description

**CompanyFromIP** enriches your Matomo visitor data with company and organization names resolved from visitor IP addresses.

For every new visit, the plugin silently looks up the visitor's IP against [ipinfo.io](https://ipinfo.io) and stores the resolved company name alongside the visit. The result is a **Companies report** under Visitors, a new **Company Name** segment, and the company displayed in the Visitor Log — giving you B2B lead intelligence without any frontend changes.

This is particularly useful for:
- Freelancers and consultants tracking who visits their portfolio or CV site
- B2B businesses wanting to know which companies scout their website
- Sales teams who want to identify warm leads before they make contact

## Features

- Resolves visitor IPs to company/organization names via ipinfo.io
- Stores company name as a Visit Dimension — available in segments, reports, and the Visitor Log
- **Companies report** under Visitors showing visits, unique visitors, and actions per company
- **Dashboard widget** for at-a-glance company intelligence
- **Company Name segment** — filter any Matomo report by company
- IP lookup results are cached in the database to minimize API calls
- Private and reserved IPs (192.168.x.x, 10.x.x.x, etc.) are skipped automatically
- Configurable cache TTL, request timeout, and master on/off switch

## Requirements

- Matomo 5.0 or later
- PHP 8.1 or later
- An [ipinfo.io](https://ipinfo.io) account (free tier: 50,000 lookups/month)

## Installation

### Via Marketplace (recommended)
1. Go to **Administration > Marketplace**
2. Search for **CompanyFromIP**
3. Click **Install**, then **Activate**

### Manual installation
1. Download the latest release ZIP
2. Extract to your Matomo `plugins/` directory so the path is `plugins/CompanyFromIP/`
3. Activate via CLI: `./console plugin:activate CompanyFromIP`
   or via **Administration > Plugins**

## Configuration

Go to **Administration > General Settings > CompanyFromIP** and set:

| Setting | Description |
|---|---|
| **Enable Company Lookup** | Master on/off switch |
| **ipinfo.io API Token** | Your free API token from [ipinfo.io](https://ipinfo.io) |
| **Cache Duration (days)** | How long to cache lookup results (default: 30 days) |
| **Request Timeout (seconds)** | Max wait time per API call (default: 2s — keep low) |

The plugin works without an API token but is limited to 1,000 lookups per day on the free anonymous tier. A free registered token raises this to 50,000/month.

## FAQ

**Does this slow down my tracking?**
Each unique IP triggers one HTTP call to ipinfo.io (max 2 seconds by default). Once cached, repeated visits from the same IP add zero overhead. For low-traffic sites the impact is negligible.

**Does this store IP addresses in the database?**
No. Only the SHA-256 hash of the IP is stored in the cache table. The raw IP is never persisted by this plugin.

**What happens if ipinfo.io is unreachable?**
The lookup fails silently, `company_name` is left empty for that visit, and tracking continues normally.

**Can I use this without an API token?**
Yes, but you are limited to 1,000 lookups per day anonymously. Register for a free ipinfo.io account to get 50,000/month.

## Support

- Issues: [GitHub Issues](https://github.com/Chardonneaur/plugin-CompanyFromIP/issues)
- Forum: [Matomo Community Forum](https://forum.matomo.org)

## License

GPL v3 or later
