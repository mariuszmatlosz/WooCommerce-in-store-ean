#!/usr/bin/env bash
# Builds the release directory and runs Plugin Check against that directory only.
set -euo pipefail

cd "$(dirname "$0")/.."

pnpm run build
pnpm exec wp-env start

# wp-env mounts the checkout under its directory name, which is not always ean-to-pos.
mount=$(basename "$PWD")
slug=ean-to-pos
target="wp-content/plugins/${mount}/build/${slug}"
output=$(pnpm exec wp-env run cli wp plugin install plugin-check --activate 2>&1)
printf '%s\n' "$output"
output=$(pnpm exec wp-env run cli wp plugin check "$target" --slug="$slug" 2>&1)
printf '%s\n' "$output"

if ! printf '%s\n' "$output" | grep -q "Checks complete. No errors found."; then
	exit 1
fi
