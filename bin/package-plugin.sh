#!/usr/bin/env bash
# Assembles the plugin directory that Plugin Check and a release zip should contain.
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
dest="$root/build/ean-to-pos"

if [ ! -f "$root/dist/js/admin.js" ] || [ ! -f "$root/dist/css/admin.css" ] || [ ! -f "$root/dist/js/admin.asset.php" ]; then
	echo "Compiled assets are missing. Run pnpm build first." >&2
	exit 1
fi

rm -rf "$dest"
mkdir -p "$dest/dist/js" "$dest/dist/css"

cp "$root/ean-to-pos.php" "$root/readme.txt" "$root/LICENSE" "$dest/"
cp -R "$root/includes" "$dest/includes"
cp -R "$root/languages" "$dest/languages"
cp "$root/dist/js/admin.js" "$root/dist/js/admin.asset.php" "$dest/dist/js/"
cp "$root/dist/css/admin.css" "$dest/dist/css/"

echo "Packaged plugin in build/ean-to-pos"
