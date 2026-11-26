"""IANA zones for the desk. Works on Windows without the tzdata package."""

from __future__ import annotations

from functools import lru_cache
from io import BytesIO
from pathlib import Path
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

_ZONE_ROOT = Path(__file__).resolve().parent / "zoneinfo"


@lru_cache(maxsize=8)
def zone(key: str) -> ZoneInfo:
    """America/Denver · America/New_York, even on embeddable Windows Python."""
    try:
        return ZoneInfo(key)
    except ZoneInfoNotFoundError:
        pass
    except ModuleNotFoundError:
        # CPython on Windows tries `import tzdata` before raising ZoneInfoNotFoundError.
        pass
    path = _ZONE_ROOT.joinpath(*key.split("/"))
    if not path.is_file():
        raise ZoneInfoNotFoundError(f"No time zone found with key {key}")
    payload = path.read_bytes()
    return ZoneInfo.from_file(BytesIO(payload), key=key)
