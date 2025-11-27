#!/bin/sh
set -e
SIMULATIONS_CONTAINER_ID=$(docker ps --filter "name=simulations" --format "{{.ID}}")
echo "SIMULATIONS_CONTAINER_ID: $SIMULATIONS_CONTAINER_ID"
if [ -z "$SIMULATIONS_CONTAINER_ID" ]; then
  echo "Container simulations not found!"
  exit 1
fi
SIMULATIONS_LOG_PATH=$(docker inspect $SIMULATIONS_CONTAINER_ID --format '{{.LogPath}}')
echo "SIMULATIONS_LOG_PATH: $SIMULATIONS_LOG_PATH"
if [ -z "$SIMULATIONS_LOG_PATH" ]; then
  echo "Container simulations log path not found!"
  exit 1
fi
sed "s|__SIMULATIONS_LOG_PATH__|$SIMULATIONS_LOG_PATH|g" /fluent-bit/etc/fluent-bit.tpl.yaml > /fluent-bit/etc/fluent-bit.yaml
exec /usr/bin/fluent-bit -c /fluent-bit/etc/fluent-bit.yaml
exit 0
