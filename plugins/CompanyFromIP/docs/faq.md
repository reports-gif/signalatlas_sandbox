## FAQ

**Q: Does this plugin slow down tracking?**

A: Each unique IP triggers one HTTPS call to ipinfo.io, capped at the configured timeout (2 seconds by default). Subsequent visits from the same IP use only the local database cache, which adds less than 1 ms. For sites receiving fewer than a few hundred unique IPs per day, the impact is negligible.

---

**Q: Does it work without an API token?**

A: Yes. Without a token, ipinfo.io allows up to 1,000 lookups per day from your server's IP. For most personal or small business sites this is sufficient. Register for a free account at [ipinfo.io](https://ipinfo.io) to raise the limit to 50,000 lookups per month.

---

**Q: What if ipinfo.io is down or unreachable?**

A: The lookup fails silently and `company_name` is left empty for that visit. Tracking is never interrupted. The next visit from the same IP will attempt the lookup again.

---

**Q: Are IP addresses stored in the database?**

A: No. The cache table stores only the SHA-256 hash of the IP, not the IP itself. The raw IP is used transiently during the lookup and never persisted by this plugin.

---

**Q: The company name shows the ISP, not the actual company. Why?**

A: On ipinfo.io's free tier, the `org` field returns the registered network operator (often the ISP for residential visitors, or the actual company for corporate networks). For precise company-level data (e.g. "Capgemini" vs "Orange Business Services"), upgrade to ipinfo.io's Business plan which provides a dedicated `company.name` field. The plugin supports both formats automatically.

---

**Q: How do I see company data in the Visitor Log?**

A: After activation, the `Company` column appears automatically in **Visitors > Visitor Log** for all new visits. Historical visits tracked before activation will have no company data.

---

**Q: Can I segment reports by company?**

A: Yes. The `companyName` segment is available in the segment editor for all reports. Example: filter the Pages report to show only pages visited by employees of a specific company.

---

**Q: How do I clear the cache?**

A: Run `TRUNCATE matomo_company_from_ip_cache;` in your database, or reduce the cache TTL in settings and wait for the daily cleanup task to remove entries.
