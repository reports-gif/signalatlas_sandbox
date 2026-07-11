# Matomo Future Visits Forecast

## Description

This Matomo plugin adds a **future visits forecast** based on the
[`prophet`](https://facebook.github.io/prophet/) time-series library.
It predicts future visitor numbers for each of your sites and makes
the results available as a dashboard widget, a dedicated report, and
a public API method.

It supports two execution modes:

- **Local mode**: Matomo calls a local Python script that runs Prophet.
- **Remote API mode**: Matomo sends anonymised time-series data to a remote
  service that returns the forecast.

The resulting forecast is stored in a dedicated table and exposed via:

- a Matomo **widget** (`ForecastWidget`)
- a Matomo **report** (`ForecastReport`)
- a public **API method** (`Forecast.getForecastReport`)

---

## Requirements

| Dependency | Minimum version             |
|-----------|-----------------------------|
| Matomo    | 5.0.0                       |
| PHP       | 7.2+ (PHP 8.1+ recommended) |
| Python    | 3.8+ (local mode only)      |

---

## 1. Installation

1. Copy the `Forecast` directory into Matomo's `plugins/` directory:

   ```
   matomo/plugins/Forecast
   ```

2. Activate the plugin via Matomo UI or CLI:

   ```bash
   ./console plugin:activate Forecast
   ./console core:update
   ```

The plugin creates the `forecast_access_count` table on install/activate.

---

## 2. Configuration

Open **Administration → System → General settings** (or the plugin's settings
page) and configure the following values:

| Setting | Key | Description                                                                                           |
|---------|-----|-------------------------------------------------------------------------------------------------------|
| Python binary path | `pythonBinPath` | Path to the Python executable, e.g. `/usr/bin/python3` or `.venv/bin/python`. Required for local mode. |
| API Key | `apiKey` | API key for the remote forecast service. Required for remote mode.       |
| API Hostname | `apiHostname` | Base URL of the remote forecast service, e.g. `https://forecast.example.com`. Required for remote mode. |

If you only use **local mode**, you can leave the API fields empty.

---

## 3. Local Forecast Execution

### 3.1 Python Requirements

Install the Python dependencies used by `Prophet/main_cli.py` on the Matomo
server:

```bash
cd matomo/plugins/Forecast/Prophet
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

Required packages:

- `prophet >= 1.1.5`
- `pandas >= 1.5.0`
- `joblib >= 1.2.0`

Update the `pythonBinPath` setting to point to the correct interpreter (e.g.
the one from the virtual environment).

### 3.2 Running Locally (CLI)

Run the local forecast command as the web server user (usually `www-data`):

```bash
su www-data -s /bin/bash -c "php -d memory_limit=-1 ./console forecast:local"
```

The command:

1. Fetches the last year of visit data for each site.
2. Calls the local Prophet script via stdin/stdout.
3. Writes the forecast into the `forecast_access_count` table.

### 3.3 Cron Setup (Local Mode)

```bash
0 2 * * * www-data PHP_MEMORY_LIMIT=-1 /path/to/matomo/console forecast:local
```

---

## 4. Remote API Forecast Execution

### 4.1 Requirements

- A valid **API key** (`apiKey`).
- A valid **API hostname** (`apiHostname`).

Both values must be set in the plugin's **System Settings**. Contact
via `https://matomo.menotec.de` to obtain credentials.

### 4.2 Commands

Two separate commands handle the remote workflow. The persist (write) command
must run **before** the fetch (read) command.

| Command | Description |
|---------|-------------|
| `./console forecast:remotePersist` | Sends historical visit data to the remote API. |
| `./console forecast:remoteFetch`   | Retrieves the forecast and stores it locally. |

### 4.3 Cron Setup (Remote Mode)

```bash
0 2 * * * www-data /path/to/matomo/console forecast:remotePersist
0 4 * * * www-data /path/to/matomo/console forecast:remoteFetch
```

---

## 5. Using the Forecast in Matomo

Once forecasts are generated (locally or via API), you can:

- Add the **Forecast** widget (`General_Visitors` category) to any dashboard.
- View the **Forecast Report** under the `Forecast` reports category.
- Access the data programmatically via the Matomo HTTP API:

  ```
  ?module=API&method=Forecast.getForecastReport&idSite=1
  ```

Optional global query parameters such as `period` and `date` may still appear on the URL (Matomo strips them before invoking the method); they do not filter this report. The API returns a `DataTable` with:

| Column | Description |
|--------|-------------|
| `label` | Forecast date (`YYYY-MM-DD`) |
| `nb_visits` | Forecasted unique visitor count for that date |

---

## 6. Development & Testing

### Running Tests

Install dev dependencies and run the unit test suite from inside the plugin
directory:

```bash
cd matomo/plugins/Forecast
composer install
vendor/bin/phpunit
```

PHPUnit 8.5 or 9.6 is supported. All tests are located under `tests/Unit/`.

### Running Tests in Docker

If the project uses Docker, execute the command inside the container:

```bash
docker exec -it <container> bash -c "cd /var/www/html/plugins/Forecast && vendor/bin/phpunit"
```

---

## 7. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Python not installed` error | Wrong `pythonBinPath` or Python missing | Install Python and update the plugin setting to the correct binary path. |
| `prophet not installed` error | Missing Python package | Run `pip install prophet pandas joblib` in the Python environment pointed to by `pythonBinPath`. |
| `Check if API key and API hostname is set!` | Remote credentials not configured | Set `apiKey` and `apiHostname` in **Administration → Plugins → Forecast** settings. |
| Forecast widget shows no data | Forecast has not been generated yet | Run `forecast:local` or `forecast:remoteFetch` at least once. |
| `Prophet forecast returned empty output` | Python script crashed silently | Check the Matomo `tmp/logs/` directory and run the Python script manually with sample input for debugging. |

---

## 8. License

This plugin is licensed under **GPL-3.0+** as declared in `plugin.json`.
