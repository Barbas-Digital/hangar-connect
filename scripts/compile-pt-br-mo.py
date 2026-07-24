# -*- coding: utf-8 -*-
"""Compile hangar-connect-pt_BR.po -> .mo (UTF-8 safe; no unicode_escape)."""
from __future__ import annotations

import re
import struct
from pathlib import Path

PO = Path(__file__).resolve().parents[1] / "languages" / "hangar-connect-pt_BR.po"
MO = PO.with_suffix(".mo")


def po_unescape(s: str) -> str:
    """Unescape PO quoted-string escapes without latin-1/unicode_escape corruption."""
    out: list[str] = []
    i = 0
    while i < len(s):
        if s[i] == "\\" and i + 1 < len(s):
            n = s[i + 1]
            if n == "n":
                out.append("\n")
            elif n == "t":
                out.append("\t")
            elif n == "r":
                out.append("\r")
            elif n == '"':
                out.append('"')
            elif n == "\\":
                out.append("\\")
            elif n in "01234567":
                j = i + 1
                while j < len(s) and j < i + 4 and s[j] in "01234567":
                    j += 1
                out.append(chr(int(s[i + 1 : j], 8)))
                i = j
                continue
            else:
                out.append(n)
            i += 2
            continue
        out.append(s[i])
        i += 1
    return "".join(out)


def parse_po(text: str) -> list[tuple[str, str]]:
    entries: list[tuple[str, str]] = []
    blocks = re.split(r"\n\n+", text)
    for block in blocks:
        if "msgid" not in block:
            continue
        msgid_m = re.search(r"msgid\s+(.*?)(?=\nmsgstr|\Z)", block, re.S)
        msgstr_m = re.search(r"msgstr\s+(.*)\Z", block, re.S)
        if not msgid_m or not msgstr_m:
            continue

        def unquote(chunk: str) -> str:
            parts = re.findall(r'"((?:\\.|[^"\\])*)"', chunk, re.S)
            return po_unescape("".join(parts))

        mid = unquote(msgid_m.group(1))
        mstr = unquote(msgstr_m.group(1))
        entries.append((mid, mstr))
    return entries


def write_mo(entries: list[tuple[str, str]], path: Path) -> None:
    # Drop empty msgid header duplicate if present; keep one header entry.
    ids = [m.encode("utf-8") for m, _ in entries]
    strs = [s.encode("utf-8") for _, s in entries]
    n = len(ids)
    header_size = 28
    otable = header_size
    ttable = header_size + 8 * n
    kstart = ttable + 8 * n

    data = b""
    orig_index: list[tuple[int, int]] = []
    for b in ids:
        orig_index.append((len(b), kstart + len(data)))
        data += b + b"\x00"

    trans_data_start = kstart + len(data)
    trans_index: list[tuple[int, int]] = []
    tdata = b""
    for b in strs:
        trans_index.append((len(b), trans_data_start + len(tdata)))
        tdata += b + b"\x00"
    data = data + tdata

    out = struct.pack("<Iiiiiii", 0x950412DE, 0, n, otable, ttable, 0, 0)
    for length, offset in orig_index:
        out += struct.pack("<II", length, offset)
    for length, offset in trans_index:
        out += struct.pack("<II", length, offset)
    out += data
    path.write_bytes(out)


def main() -> None:
    text = PO.read_text(encoding="utf-8")
    entries = parse_po(text)
    write_mo(entries, MO)
    # Sanity: Connections must be single UTF-8 õ (C3 B5), not double-encoded.
    sample = next(s for m, s in entries if m == "Connections")
    raw = sample.encode("utf-8")
    assert raw == "Conexões".encode("utf-8"), raw
    assert b"\xc3\x83\xc2" not in MO.read_bytes(), "mo still double-encoded"
    print(f"wrote {MO} ({MO.stat().st_size} bytes, {len(entries)} entries)")
    print("Connections msgstr OK:", sample)


if __name__ == "__main__":
    main()
