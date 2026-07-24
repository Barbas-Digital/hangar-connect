#!/usr/bin/env python3
"""Deprecated entrypoint — use compile-pt-br-mo.py (Hangar Connect msgids)."""
import runpy
from pathlib import Path

print("build-pt-br-mo.py is deprecated; forwarding to compile-pt-br-mo.py")
runpy.run_path(str(Path(__file__).with_name("compile-pt-br-mo.py")), run_name="__main__")
