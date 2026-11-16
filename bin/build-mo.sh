#!/usr/bin/env bash
# Builds ariadne-ai-assistant/languages/*.mo from the .po files with msgfmt (GNU gettext,
# free). The .mo files are not committed: build-zip.sh and the release make them.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
shopt -s nullglob
for po in "$root"/ariadne-ai-assistant/languages/*.po; do
  msgfmt --check --output-file="${po%.po}.mo" "$po"
  echo "built ${po%.po}.mo"
done
