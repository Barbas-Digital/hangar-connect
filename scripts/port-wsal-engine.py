#!/usr/bin/env python3
"""Port AR WSAL engine into hangar-connect/includes/wsal (hangar_wsal_ prefix)."""
from pathlib import Path
import re

ar = Path(r"C:\Users\guilh\OneDrive\Documentos\GitHub\barbas-activity-reports\includes")
out = Path(r"C:\Users\guilh\OneDrive\Documentos\GitHub\hangar-connect\includes\wsal")
out.mkdir(parents=True, exist_ok=True)


def transform(text: str) -> str:
    text = text.replace("barbas-activity-reports", "hangar-connect")
    text = text.replace("WSALR_", "HANGAR_WSAL_")
    text = text.replace("wsalr_", "hangar_wsal_")
    return text


banner = (
    "<?php\n"
    "/**\n"
    " * Hangar Connect — native WSAL reader (ported from Activity Reports engine).\n"
    " * Read-only against wsal_occurrences / wsal_metadata. No AR plugin dependency.\n"
    " */\n\n"
)

for name in ("events.php", "data.php", "analytics.php"):
    src = (ar / name).read_text(encoding="utf-8")
    dst = transform(src)
    if dst.lstrip().startswith("<?php"):
        dst = re.sub(r"^<\?php\s*", "", dst.lstrip(), count=1)
    (out / name).write_text(banner + dst, encoding="utf-8")
    print("wrote", name, len(dst))

(out / "index.php").write_text("<?php\n// Silence.\n", encoding="utf-8")
print("done")
