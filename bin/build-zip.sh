#!/usr/bin/env bash
# Builds dist/ariadne-ai-assistant.zip, the file owners download from ariadnebot.com:
# one top folder ariadne-ai-assistant/, the plugin's own files and its built .mo files,
# no dev files (those live outside the plugin folder). Used by ci.yml and release.yml.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
slug=ariadne-ai-assistant
cd "$root"

"$root/bin/build-mo.sh"

# Nothing hidden or built may sit inside the plugin folder.
extra="$(find "$slug" \( -name '.*' -o -name '*.zip' -o -name node_modules -o -name vendor \) -print)"
if [ -n "$extra" ]; then
  echo "dev files inside $slug/, remove them first:" >&2
  echo "$extra" >&2
  exit 1
fi

# A working Python, for machines without zip/unzip (Git Bash on Windows); the Windows Store
# stub named python3 answers but cannot run anything, so each candidate is tried.
find_python() {
  for cand in python3 python; do
    if command -v "$cand" >/dev/null 2>&1 && "$cand" -c 'import zipfile' >/dev/null 2>&1; then
      echo "$cand"
      return
    fi
  done
  echo "need zip/unzip or Python 3" >&2
  exit 1
}

rm -rf dist
mkdir -p dist
if command -v zip >/dev/null 2>&1; then
  zip -rqX "dist/$slug.zip" "$slug"
else
  # Python's zipfile writes the same layout.
  py="$(find_python)"
  "$py" -m zipfile -c "dist/$slug.zip" "$slug"
fi

# One top folder, the main file in it.
if command -v unzip >/dev/null 2>&1; then
  entries="$(unzip -Z1 "dist/$slug.zip")"
else
  py="$(find_python)"
  entries="$("$py" -c 'import sys, zipfile; print("\n".join(zipfile.ZipFile(sys.argv[1]).namelist()))' "dist/$slug.zip")"
fi
if printf '%s\n' "$entries" | grep -v "^$slug/" | grep -q .; then
  echo "the zip has entries outside $slug/" >&2
  exit 1
fi
printf '%s\n' "$entries" | grep -qx "$slug/$slug.php" || { echo "the zip has no $slug/$slug.php" >&2; exit 1; }
printf '%s\n' "$entries" | grep -qx "$slug/languages/$slug-el.mo" || { echo "the zip has no Greek .mo" >&2; exit 1; }
echo "built dist/$slug.zip ($(wc -c < "dist/$slug.zip") bytes, $(printf '%s\n' "$entries" | grep -vc '/$') files)"
