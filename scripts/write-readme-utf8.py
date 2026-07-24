#!/usr/bin/env python3
"""Validate hangar-connect README.md branding (UTF-8, no stale product names)."""
from pathlib import Path

root = Path(__file__).resolve().parents[1]
text = (root / "README.md").read_text(encoding="utf-8")

assert "Hangar Connect" in text, "README must name Hangar Connect"
assert "File: `hangar-connect.zip`" in text or "File: hangar-connect.zip" in text
assert "Barbas Central" not in text, "stale product name Barbas Central"
assert "# Barbas Connect" not in text, "stale product title Barbas Connect"
print("OK: README.md Hangar branding")
