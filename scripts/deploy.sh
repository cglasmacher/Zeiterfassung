#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BIN:-php}"
# Run after Plesk has deployed the selected Git revision into this directory.
"$PHP_BIN" artisan optimize:clear
npm ci
npm run build
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan app:check-deployment
