# CompanyFromIP Documentation

## How it works

When a new visit is tracked, CompanyFromIP:

1. Reads the visitor's IP address from the tracking request
2. Checks a local database cache for a previous lookup result
3. If not cached (or expired): calls the ipinfo.io API to resolve the IP to a company name
4. Stores the result in the cache (including null results, to avoid redundant calls)
5. Writes the company name into the `company_name` column of `log_visit`

The resolved company name is then available as:
- A dimension in the **Visitor Log**
- A **Companies report** under Visitors
- A **segment** (`companyName`) for filtering any Matomo report

## Privacy

- Raw IP addresses are **never stored** in the cache table. Only the SHA-256 hash of the IP is used as the cache key.
- The company name stored in `log_visit` is the same data Matomo already exposes in its built-in geolocation features (organization/ISP level, not personal data).
- Lookups are sent to ipinfo.io. Review their [privacy policy](https://ipinfo.io/privacy-policy) for details on data handling.

## Cache table

The plugin creates one additional database table: `matomo_company_from_ip_cache`

| Column | Type | Description |
|---|---|---|
| `ip_hash` | CHAR(64) | SHA-256 hash of the visitor IP (primary key) |
| `company_name` | VARCHAR(255) | Resolved company name, or NULL if unknown |
| `lookup_date` | DATETIME | When the lookup was last performed |

A daily scheduled task automatically removes entries older than the configured cache TTL.

## Performance

- API calls are bounded by the configured timeout (default: 2 seconds)
- Private/reserved IPs (192.168.x.x, 10.x.x.x, ::1, etc.) are skipped without any API call
- Returning visitors from the same IP hit only the local DB cache
- On a typical low-traffic site, the performance impact is negligible
- For high-traffic sites, consider setting a longer cache TTL or pre-warming the cache

## Uninstalling

Deactivating the plugin stops all lookups but retains data.
Uninstalling via the UI or `./console plugin:uninstall CompanyFromIP` drops the cache table and the `company_name` column from `log_visit`.
