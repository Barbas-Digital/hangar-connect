#!/usr/bin/env bash
# Pre-release / CI validation for Barbas / Abler WordPress plugins.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# Plugin slug may differ from the git folder name (repo still barbas-connect; product slug hangar-connect).
if [[ -n "${1:-}" ]]; then
  SLUG="$1"
elif [[ -f "$ROOT/hangar-connect.php" ]]; then
  SLUG="hangar-connect"
else
  SLUG="$(basename "$ROOT")"
fi
cd "$ROOT"

echo "== CI validate: $SLUG =="

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php not found in PATH" >&2
  exit 1
fi

echo "-- PHP lint --"
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find . -name '*.php' -not -path './.git/*' -print0)
echo "PHP lint OK"

MAIN="$SLUG.php"
if [[ ! -f "$MAIN" ]]; then
  echo "ERROR: missing main plugin file $MAIN" >&2
  exit 1
fi

HEADER_VER="$(grep -E '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*' "$MAIN" | head -n1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')"

# Prefer plugin-specific *_VERSION constant (BARBAS_*, ABLER_*, etc.).
CONST_VER="$(
  grep -Eo "define\(\s*['\"][A-Z][A-Z0-9_]*_VERSION['\"]\s*,\s*['\"][^'\"]+['\"]" "$MAIN" \
    | head -n1 \
    | sed -E "s/.*,\s*['\"]([^'\"]+)['\"].*/\1/" \
    || true
)"

echo "Header Version: ${HEADER_VER:-?}"
echo "Constant Version: ${CONST_VER:-?}"

if [[ -z "$HEADER_VER" || -z "$CONST_VER" ]]; then
  echo "ERROR: could not parse plugin version" >&2
  exit 1
fi
if [[ "$HEADER_VER" != "$CONST_VER" ]]; then
  echo "ERROR: Version header ($HEADER_VER) != constant ($CONST_VER)" >&2
  exit 1
fi

if [[ -f readme.txt ]]; then
  STABLE="$(grep -E '^Stable tag:[[:space:]]*' readme.txt | head -n1 | sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '[:space:]')"
  echo "Stable tag: ${STABLE:-?}"
  if [[ -n "$STABLE" && "$STABLE" != "$HEADER_VER" ]]; then
    echo "ERROR: Stable tag ($STABLE) != Version ($HEADER_VER)" >&2
    exit 1
  fi
fi

if ! grep -Eq "ABSPATH|WPINC|defined\(\s*['\"]ABSPATH['\"]|defined\(\s*['\"]WPINC['\"]" "$MAIN"; then
  echo "ERROR: main file missing ABSPATH/WPINC guard" >&2
  exit 1
fi

echo "-- Dangerous patterns (plugin code, exclude PUC) --"
EXCLUDE_ARGS=(-path './lib/plugin-update-checker' -prune -o -path './.git' -prune -o)
HITS="$(find "${EXCLUDE_ARGS[@]}" -type f -name '*.php' -print0 | xargs -0 grep -nE '\beval\s*\(|create_function\s*\(|\bassert\s*\(|base64_decode\s*\(\s*\$_' || true)"
if [[ -n "${HITS// }" ]]; then
  echo "$HITS" >&2
  echo "ERROR: forbidden patterns found" >&2
  exit 1
fi
echo "No forbidden patterns"

if [[ ! -f scripts/build-release.sh ]]; then
  echo "ERROR: missing scripts/build-release.sh" >&2
  exit 1
fi

echo "-- Build release zip --"
OUT="$(mktemp -d)"
bash scripts/build-release.sh "$OUT"
ZIP="$OUT/$SLUG.zip"
if [[ ! -f "$ZIP" ]]; then
  echo "ERROR: zip not created at $ZIP" >&2
  exit 1
fi

FIRST="$(unzip -Z1 "$ZIP" | head -n 1)"
echo "First zip entry: $FIRST"
case "$FIRST" in
  "$SLUG/"|"$SLUG"/*) ;;
  *) echo "ERROR: root must be $SLUG/, got: $FIRST" >&2; exit 1 ;;
esac

if unzip -Z1 "$ZIP" | grep -qE '(^|/)\.git(/|$)|(^|/)\.github(/|$)|(^|/)scripts(/|$)'; then
  echo "ERROR: zip contains .git, .github, or scripts/" >&2
  unzip -Z1 "$ZIP" | grep -E '(^|/)\.git(/|$)|(^|/)\.github(/|$)|(^|/)scripts(/|$)' >&2 || true
  exit 1
fi

if unzip -Z1 "$ZIP" | grep -q '\\'; then
  echo "ERROR: zip paths contain backslashes" >&2
  exit 1
fi

rm -rf "$OUT"
echo "== CI validate OK: $SLUG@$HEADER_VER =="
