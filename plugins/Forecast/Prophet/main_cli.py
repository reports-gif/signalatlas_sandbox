#!/usr/bin/env python3
"""
Prophet-based time series forecasting script.

Reads JSON from stdin, trains or loads a Prophet model,
and outputs a JSON forecast to stdout.

Exit codes:
    0  – success
    1  – invalid / missing arguments
    2  – bad / unreadable input data
    3  – model I/O error
    4  – forecasting error
    5  – unexpected internal error
"""

from __future__ import annotations

import argparse
import json
import logging
import os
import sys
import tempfile
from pathlib import Path
from typing import Any

import joblib
import pandas as pd
from prophet import Prophet

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

LOG_LEVEL = os.environ.get("LOG_LEVEL", "INFO").upper()

logging.basicConfig(
    stream=sys.stderr,
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)-8s %(name)s – %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S",
)
logger = logging.getLogger("forecast")


# ---------------------------------------------------------------------------
# Exit-code constants
# ---------------------------------------------------------------------------

EXIT_OK = 0
EXIT_BAD_ARGS = 1
EXIT_BAD_INPUT = 2
EXIT_MODEL_IO = 3
EXIT_FORECAST = 4
EXIT_INTERNAL = 5


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Prophet time-series forecaster. Reads JSON from stdin, writes JSON to stdout.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    parser.add_argument(
        "--days",
        type=int,
        default=30,
        help="Number of future days to forecast.",
    )
    parser.add_argument(
        "--model-path",
        required=True,
        help="Path to persist / load the trained model (joblib format).",
    )
    parser.add_argument(
        "--retrain",
        type=lambda v: v.strip().lower() in {"true", "1", "yes"},
        default=False,
        metavar="BOOL",
        help="Force model retraining even if a saved model exists (true/false).",
    )
    parser.add_argument(
        "--country",
        default="DE",
        help="ISO 3166-1 alpha-2 country code for holiday calendar.",
    )
    parser.add_argument(
        "--interval-width",
        type=float,
        default=0.80,
        help="Width of the uncertainty interval (0 < value < 1).",
    )
    args = parser.parse_args(argv)

    # --- post-parse validation ---
    if args.days < 1:
        parser.error("--days must be a positive integer.")
    if not (0.0 < args.interval_width < 1.0):
        parser.error("--interval-width must be strictly between 0 and 1.")

    return args


def _read_stdin() -> list[dict[str, Any]]:
    """Read and parse the JSON payload from stdin."""
    raw = sys.stdin.read()
    if not raw.strip():
        raise ValueError("stdin was empty – expected a JSON array or object.")
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ValueError(f"Invalid JSON on stdin: {exc}") from exc
    if isinstance(data, dict):
        data = [data]
    if not isinstance(data, list):
        raise ValueError("JSON input must be an array of records or a single object.")
    return data


def _build_dataframe(records: list[dict[str, Any]]) -> pd.DataFrame:
    """Construct and validate a Prophet-compatible DataFrame."""
    df = pd.DataFrame(records)

    missing = {"ds", "y"} - set(df.columns)
    if missing:
        raise ValueError(f"Input JSON is missing required column(s): {missing}")

    df["ds"] = pd.to_datetime(df["ds"], utc=False, errors="coerce")
    n_bad_ds = df["ds"].isna().sum()
    if n_bad_ds:
        raise ValueError(
            f"{n_bad_ds} row(s) have unparseable 'ds' values. "
            "Expected ISO-8601 date strings (e.g. '2024-01-15')."
        )

    df["y"] = pd.to_numeric(df["y"], errors="coerce")
    n_bad_y = df["y"].isna().sum()
    if n_bad_y:
        raise ValueError(f"{n_bad_y} row(s) have non-numeric 'y' values.")

    if df.duplicated("ds").any():
        n_dupes = df.duplicated("ds", keep=False).sum()
        df = df.groupby("ds", as_index=False)["y"].sum()
        logger.warning(
            "%d rows with duplicate timestamps aggregated (summed) into %d unique dates.",
            n_dupes,
            len(df),
        )

    df = df.sort_values("ds").reset_index(drop=True)
    logger.info("DataFrame ready: %d rows, range %s → %s", len(df), df["ds"].min().date(), df["ds"].max().date())
    return df[["ds", "y"]]


def _load_or_train(
    df: pd.DataFrame,
    model_path: Path,
    retrain: bool,
    country: str,
    interval_width: float,
) -> Prophet:
    """Return a fitted Prophet model – loaded from disk or freshly trained."""
    if not retrain and model_path.exists():
        logger.info("Loading model from %s", model_path)
        try:
            model: Prophet = joblib.load(model_path)
        except Exception as exc:
            raise OSError(f"Failed to load model from {model_path}: {exc}") from exc
        logger.info("Model loaded successfully.")
        return model

    action = "Retraining" if retrain else "No saved model found – training"
    logger.info("%s Prophet model …", action)
    model = Prophet(
        daily_seasonality=True,
        yearly_seasonality=True,
        interval_width=interval_width,
    )
    model.add_country_holidays(country_name=country)
    model.fit(df)
    logger.info("Training complete.")

    # Atomic write: write to a temp file first, then rename.
    model_path.parent.mkdir(parents=True, exist_ok=True)
    tmp_fd, tmp_path = tempfile.mkstemp(
        dir=model_path.parent, prefix=".tmp_model_", suffix=".pkl"
    )
    try:
        os.close(tmp_fd)
        joblib.dump(model, tmp_path)
        os.replace(tmp_path, model_path)
        logger.info("Model saved to %s", model_path)
    except Exception as exc:
        # Best-effort cleanup of the temp file.
        try:
            os.unlink(tmp_path)
        except OSError:
            pass
        raise OSError(f"Failed to save model to {model_path}: {exc}") from exc

    return model


def _run_forecast(
    model: Prophet,
    df: pd.DataFrame,
    days: int,
) -> list[dict[str, Any]]:
    """Produce a forecast and return it as a list of dicts."""
    future = model.make_future_dataframe(periods=days, freq="D")
    forecast = model.predict(future)

    cols = ["ds", "yhat", "yhat_lower", "yhat_upper"]
    result = forecast[cols].copy()

    cutoff = df["ds"].max()
    result = result[result["ds"] > cutoff].tail(days)

    result["ds"] = result["ds"].dt.strftime("%Y-%m-%d")
    for col in ("yhat", "yhat_lower", "yhat_upper"):
        result[col] = result[col].round(0).astype(int)

    records = result.to_dict("records")
    logger.info("Forecast produced: %d records", len(records))
    return records


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main(argv: list[str] | None = None) -> int:
    # ---- args ----
    try:
        args = _parse_args(argv if argv is not None else sys.argv[1:])
    except SystemExit as exc:
        # argparse already printed its error to stderr.
        return EXIT_BAD_ARGS if exc.code != 0 else EXIT_OK

    model_path = Path(args.model_path)

    # ---- read stdin ----
    try:
        records = _read_stdin()
        df = _build_dataframe(records)
    except ValueError as exc:
        logger.error("Input error: %s", exc)
        return EXIT_BAD_INPUT

    # ---- model ----
    try:
        model = _load_or_train(
            df,
            model_path,
            retrain=args.retrain,
            country=args.country,
            interval_width=args.interval_width,
        )
    except OSError as exc:
        logger.error("Model I/O error: %s", exc)
        return EXIT_MODEL_IO
    except Exception as exc:  # noqa: BLE001
        logger.exception("Unexpected error during model training/loading: %s", exc)
        return EXIT_INTERNAL

    # ---- forecast ----
    try:
        forecast_records = _run_forecast(model, df, days=args.days)
    except Exception as exc:  # noqa: BLE001
        logger.exception("Forecast failed: %s", exc)
        return EXIT_FORECAST

    # ---- output ----
    sys.stdout.write(json.dumps(forecast_records, separators=(",", ":"), ensure_ascii=False))
    sys.stdout.write("\n")
    sys.stdout.flush()

    return EXIT_OK


if __name__ == "__main__":
    sys.exit(main())