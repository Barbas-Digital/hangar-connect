#!/usr/bin/env bash
# Builds a WordPress-compatible plugin zip for GitHub Releases / Plugin Update Checker.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="barbas-connect"
OUT_DIR="${1:-$ROOT}"
STAGING="$(mktemp -d)"
TARGET="$STAGING/$SLUG"
ZIP_PATH="$OUT_DIR/$SLUG.zip"

cleanup() { rm -rf "$STAGING"; }
trap cleanup EXIT

mkdir -p "$TARGET"
rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='scripts/' \
  --exclude='dist/' \
  --exclude='*.zip' \
  --exclude='.DS_Store' \
  "$ROOT"/ "$TARGET"/

rm -f "$ZIP_PATH"
(cd "$STAGING" && zip -r -X -q "$ZIP_PATH" "$SLUG")

echo "=== unzip -l (first entries) ==="
unzip -l "$ZIP_PATH" | head -n 12

FIRST="$(unzip -Z1 "$ZIP_PATH" | head -n 1)"
echo "First entry: $FIRST"
if [[ "$FIRST" != "$SLUG/" && "$FIRST" != "$SLUG/"* ]]; then
  echo "ERROR: root folder must be '$SLUG/', got '$FIRST'" >&2
  exit 1
fi
if [[ "$FIRST" == *"-1.0"* ]]; then
  echo "ERROR: version suffix -1.0 in zip root: $FIRST" >&2
  exit 1
fi
if unzip -Z1 "$ZIP_PATH" | grep -q '\\'; then
  echo "ERROR: backslash path separators found (Windows-style zip)." >&2
  exit 1
fi
if unzip -Z1 "$ZIP_PATH" | grep -qE "^${SLUG}-[0-9]"; then
  echo "ERROR: versioned slug folder entries found." >&2
  exit 1
fi

echo "Created: $ZIP_PATH"
