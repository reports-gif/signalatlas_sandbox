HubSpotDashboard Matomo Plugin

Upload folder:
- Upload the HubSpotDashboard folder to Matomo's plugins/ directory.

Config:
Add this to config/config.ini.php:

[HubSpotDashboard]
hubspot_token = "YOUR_HUBSPOT_PRIVATE_APP_TOKEN"

Activation without SSH:
Option A:
- Matomo Admin > System > Plugins > find HubSpotDashboard > Activate

Option B if plugin does not appear:
- Add this to config/config.ini.php under [Plugins]:
Plugins[] = "HubSpotDashboard"

- Add this to [PluginsInstalled]:
PluginsInstalled[] = "HubSpotDashboard"

Then clear Matomo cache from UI if available, or delete tmp/cache/tracker and tmp/cache/templates_c through File Manager.
