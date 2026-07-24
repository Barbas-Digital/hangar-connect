# -*- coding: utf-8 -*-
"""Compile hangar-connect-pt_BR.po -> .mo and fix a few msgstr."""
from __future__ import annotations

import codecs
import re
import struct
from pathlib import Path

PO = Path(__file__).resolve().parents[1] / "languages" / "hangar-connect-pt_BR.po"
MO = PO.with_suffix(".mo")


def unescape(s: str) -> str:
    return codecs.decode(s, "unicode_escape")


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
            return unescape("".join(parts))

        mid = unquote(msgid_m.group(1))
        mstr = unquote(msgstr_m.group(1))
        entries.append((mid, mstr))
    return entries


def write_mo(entries: list[tuple[str, str]], path: Path) -> None:
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
    text = text.replace(
        'msgstr "Activity Reports"',
        'msgstr "Relatórios de atividade"',
    )
    text = text.replace(
        'msgstr "Conectado ao Central"',
        'msgstr "Conectado ao Hangar"',
    )
    text = text.replace(
        '"Central."\n',
        '"Hangar."\n',
    )
    # Product rename in English msgids (must match PHP after string updates).
    text = text.replace(
        'msgid "Connected to Central"',
        'msgid "Connected to Hangar"',
    )
    text = text.replace(
        "another Central.",
        "another Hangar.",
    )
    PO.write_text(text, encoding="utf-8", newline="\n")
    entries = parse_po(text)
    write_mo(entries, MO)
    print(f"wrote {MO} ({MO.stat().st_size} bytes, {len(entries)} entries)")


if __name__ == "__main__":
    main()
