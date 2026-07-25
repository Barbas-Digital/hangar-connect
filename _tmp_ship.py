# -*- coding: utf-8 -*-
from pathlib import Path
import subprocess
import zipfile

root = Path(r"C:\Users\guilh\OneDrive\Documentos\GitHub\hangar-connect")
old, new = "0.2.17", "0.2.18"

mp = root / "hangar-connect.php"
t = mp.read_text(encoding="utf-8")
t = t.replace(f"Version: {old}", f"Version: {new}")
t = t.replace(f"HANGAR_CONNECT_VERSION', '{old}'", f"HANGAR_CONNECT_VERSION', '{new}'")
mp.write_text(t, encoding="utf-8", newline="\n")

rp = root / "readme.txt"
rt = rp.read_text(encoding="utf-8")
rt = rt.replace(f"Stable tag: {old}", f"Stable tag: {new}")
if f"= {new} =" not in rt:
    rt = rt.replace(
        "== Changelog ==\n\n",
        f"== Changelog ==\n\n= {new} =\n"
        "* Connections list: more space between label and status; ID on its own row.\n"
        "* pt_BR: translate Pending status (was showing English).\n\n",
    )
rp.write_text(rt, encoding="utf-8", newline="\n")

out = Path(r"C:\Users\guilh\OneDrive\Documentos\GitHub\_local\releases\hangar-connect.zip")
exclude = {".git", ".github", "scripts", "dist", "node_modules", "_local", ".idea", ".vscode"}
if out.exists():
    out.unlink()
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
    for p in root.rglob("*"):
        if not p.is_file():
            continue
        rel = p.relative_to(root)
        if any(x in exclude for x in rel.parts):
            continue
        if p.suffix.lower() == ".zip" or p.name.startswith("_tmp"):
            continue
        zf.write(p, f"hangar-connect/{rel.as_posix()}")

subprocess.check_call(["git", "add", "-A"], cwd=root)
subprocess.check_call(
    ["git", "commit", "-m", "Release 0.2.18: connection label spacing + Pending pt_BR."],
    cwd=root,
)
subprocess.check_call(["git", "push", "origin", "HEAD"], cwd=root)
subprocess.check_call(["git", "tag", "-a", "v0.2.18", "-m", "Hangar Connect 0.2.18"], cwd=root)
subprocess.check_call(["git", "push", "origin", "v0.2.18"], cwd=root)
subprocess.check_call(
    [
        "gh",
        "release",
        "create",
        "v0.2.18",
        str(out),
        "--title",
        "0.2.18",
        "--notes",
        "## Changes\n* Connections: space between label and status; ID on own row.\n* pt_BR: Pending -> Pendente.",
        "--latest",
    ],
    cwd=root,
)
print("OK", new)
