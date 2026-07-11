# HubSpot Private App Scopes

## Required

```text
crm.objects.contacts.read
crm.objects.companies.read
crm.objects.deals.read
crm.objects.owners.read
```

## Optional

```text
sales-email-read
crm.objects.calls.read
crm.objects.meetings.read
crm.objects.notes.read
crm.objects.tasks.read
```

If some activity scopes do not appear in your HubSpot private app UI, skip them. This plugin treats activity data as optional and will not crash when those permissions are unavailable.

## Do not add write scopes

Avoid write scopes such as contacts write, companies write, deals write, workflow write, marketing email write, files write, or ticket write. This dashboard is designed as read-only.
