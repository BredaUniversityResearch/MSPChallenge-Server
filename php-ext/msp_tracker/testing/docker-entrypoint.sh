#!/usr/bin/env sh
set -eu

echo "[msp_tracker-test] PHP: $(php -v | head -n 1)"

if ! php -m | grep -q '^msp_tracker$'; then
  echo "[msp_tracker-test] ERROR: msp_tracker extension is not loaded"
  exit 1
fi

echo "[msp_tracker-test] msp_tracker extension loaded"

exec php /app/run-tests.php

