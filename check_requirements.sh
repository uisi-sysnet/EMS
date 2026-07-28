#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REQ_FILE="${SCRIPT_DIR}/requirements.txt"
PYTHON_BIN="${PYTHON_BIN:-python3}"

if ! command -v "${PYTHON_BIN}" >/dev/null 2>&1; then
    echo "[check] ${PYTHON_BIN} not found" >&2
    exit 2
fi

if [[ ! -f "$REQ_FILE" ]]; then
    echo "[check] requirements file not found: $REQ_FILE" >&2
    exit 2
fi

echo "[check] Checking Python packages from $REQ_FILE"

"${PYTHON_BIN}" - <<'PY' "$REQ_FILE"
import importlib.metadata as im
import re
import sys
from pathlib import Path

req_file = Path(sys.argv[1])
missing = []
installed = []

for raw_line in req_file.read_text(encoding="utf-8").splitlines():
    line = raw_line.split("#", 1)[0].strip()
    if not line:
        continue

    # Keep only the package name before version specifiers/extras
    name = re.split(r"[<>=!~\[]", line, 1)[0].strip()
    name = name.split(";", 1)[0].strip()
    if not name:
        continue

    # Normalize common pip naming differences
    name = name.replace("_", "-")

    try:
        version = im.version(name)
    except im.PackageNotFoundError:
        missing.append(name)
    else:
        installed.append((name, version))

for pkg_name, version in sorted(installed):
    print(f"OK {pkg_name} {version}")

if missing:
    print("MISSING:")
    for pkg_name in missing:
        print(f" MISSING {pkg_name}")
    sys.exit(1)

print("All required Python packages are installed.")
PY
