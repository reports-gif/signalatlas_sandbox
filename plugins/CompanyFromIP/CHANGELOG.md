## Changelog

### 1.0.0

- Initial release
- Feature: IP-to-company resolution via ipinfo.io on every new visit
- Feature: Company Name stored as a Visit Dimension in log_visit
- Feature: Companies report under Visitors (visits, unique visitors, actions per company)
- Feature: Dashboard widget showing visits by company
- Feature: Company Name segment for filtering any Matomo report
- Feature: DB-backed lookup cache with configurable TTL to minimize API calls
- Feature: Daily scheduled task to prune expired cache entries
- Feature: Private/reserved IPs skipped automatically (no wasted API calls)
- Feature: Configurable API token, cache duration, request timeout, and master on/off switch
