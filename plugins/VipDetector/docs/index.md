# VipDetector

## Quick start

1. Install the plugin
2. Create your Json file
   1. If you don't have one, you can use the one I [created](https://ranges.vikoe.eu/) with Austrian Government Agencies
3. Import it
   1. Either on the console ```./console vipdetector:import-data /path/to/file.json```
   2. Or use the Matomo scheduler via the Web Interface

### Json File Structure

```json
[
 {
  "name": "Example Org 1",
  "ranges": [
   "192.0.2.0/24",
   "198.51.100.0/24"
  ]
 },
 {
  "name": "Example Org 2",
  "ranges": [
   "203.0.113.0/24",
   "2001:db8::/32"
  ]
 }
]
```
