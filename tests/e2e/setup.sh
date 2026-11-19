#!/usr/bin/env bash
# Activates the plugin and seeds WooCommerce inside the wp-env site.
set -euo pipefail

cd "$(dirname "$0")/../.."
plugin=$(basename "$PWD")

pnpm exec wp-env run cli wp plugin activate woocommerce.latest-stable
pnpm exec wp-env run cli wp plugin activate "$plugin"
pnpm exec wp-env run cli wp eval-file "wp-content/plugins/${plugin}/tests/e2e/seed.php"
