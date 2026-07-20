#!/usr/bin/env bash
# Fail if README.md contains classic UTF-8 mojibake / BOM / box-drawing trees.
# Source is ASCII-only (hex patterns) so this file cannot itself be mojibake'd.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FILES=("README.md")
if [[ $# -gt 0 ]]; then
  FILES=("$@")
fi

fail=0
for f in "${FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "ERROR: missing $f" >&2
    fail=1
    continue
  fi

  # Reject UTF-8 BOM
  if [[ "$(head -c 3 "$f" | od -An -tx1 | tr -d ' \n')" == "efbbbf" ]]; then
    echo "ERROR: $f has UTF-8 BOM (save as UTF-8 without BOM)" >&2
    fail=1
  fi

  # Reject U+FFFD replacement character
  if grep -q $'\xef\xbf\xbd' "$f"; then
    echo "ERROR: $f contains U+FFFD replacement characters (encoding corruption)" >&2
    fail=1
  fi

  # Mojibake of common PT/punct: Ã£ Ã§ Ã© Ã¡ Ã³ Ã­ Ãº Ãª Ãµ Ã  Ã‰ Ã‡ Â« Â»
  # plus â€ (cp1252 misread of UTF-8 punctuation) and â” (box-drawing misread)
  if grep -a -q \
    -e $'\xc3\x83\xc2\xa3' \
    -e $'\xc3\x83\xc2\xa7' \
    -e $'\xc3\x83\xc2\xa9' \
    -e $'\xc3\x83\xc2\xa1' \
    -e $'\xc3\x83\xc2\xb3' \
    -e $'\xc3\x83\xc2\xad' \
    -e $'\xc3\x83\xc2\xba' \
    -e $'\xc3\x83\xc2\xaa' \
    -e $'\xc3\x83\xc2\xb5' \
    -e $'\xc3\x83\xc2\xa0' \
    -e $'\xc3\x83\xc2\x89' \
    -e $'\xc3\x83\xc2\x87' \
    -e $'\xc3\x82\xc2\xab' \
    -e $'\xc3\x82\xc2\xbb' \
    -e $'\xc3\xa2\xe2\x82\xac' \
    -e $'\xc3\xa2\xe2\x80\x9d' \
    "$f"; then
    echo "ERROR: $f contains mojibake (e.g. Relat[mojibake]rios). Re-save as UTF-8; use ASCII tree (|--, \\--)." >&2
    fail=1
  fi

  # Unicode box-drawing block starts with UTF-8 E2 94 ..
  if [[ "$(basename "$f")" == "README.md" ]] && grep -a -q $'\xe2\x94' "$f"; then
    echo "ERROR: $f uses Unicode box-drawing. Use ASCII tree: |-- | \\--" >&2
    fail=1
  fi
done

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

echo "README encoding OK: ${FILES[*]}"
