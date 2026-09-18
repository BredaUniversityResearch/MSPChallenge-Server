#!/usr/bin/env bash
#
# inject_blackfire.sh
#
# Injects Blackfire into an already-running MSPChallenge-Server dev/staging stack,
# without touching your docker-compose files or restarting the whole stack:
#
#   1. Installs the Blackfire PHP probe into the running php container
#   2. Asks for any missing Blackfire credentials (env vars take priority)
#   3. Starts a Blackfire agent container (`docker run`) on the same network as php
#   4. Restarts the php container so the newly installed probe actually loads
#   5. Prints a couple of tips to get you profiling right away
#
# Usage:
#   ./inject_blackfire.sh [--force] [--container NAME] [--network NAME] [--agent-name NAME]
#
#   --force            Reinstall the probe even if it looks already installed
#   --container NAME   PHP container name (default: auto-detected)
#   --network NAME     Docker network name (default: auto-detected, *_msp_network)
#   --agent-name NAME  Name for the Blackfire agent container
#                       (default: ${COMPOSE_PROJECT_NAME}-blackfire-1, matching the
#                       `dlb` alias in docker-aliases.sh)
#
# Note: this installs the probe into the container's writable layer only. If the
# php container ever gets recreated (docker compose down / up --force-recreate),
# the probe is gone and you'll need to run this script again.

set -euo pipefail

FORCE=false
PHP_CONTAINER="${PHP_CONTAINER:-}"
NETWORK_NAME="${NETWORK_NAME:-}"
AGENT_NAME="${AGENT_NAME:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --force) FORCE=true; shift ;;
    --container) PHP_CONTAINER="$2"; shift 2 ;;
    --network) NETWORK_NAME="$2"; shift 2 ;;
    --agent-name) AGENT_NAME="$2"; shift 2 ;;
    -h|--help)
      grep '^#' "$0" | sed -n '2,26p' | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) echo "Unknown argument: $1" >&2; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# 1. Figure out which php container and network to use
# ---------------------------------------------------------------------------

detect_php_container() {
  if [[ -n "${COMPOSE_PROJECT_NAME:-}" ]] \
      && docker ps --format '{{.Names}}' | grep -qx "${COMPOSE_PROJECT_NAME}-php-1"; then
    echo "${COMPOSE_PROJECT_NAME}-php-1"
    return
  fi

  local matches
  matches="$(docker ps --format '{{.Names}}' | grep -E '(^|-)php-1$' || true)"
  local count
  count="$(echo "$matches" | grep -c . || true)"

  if [[ "$count" -eq 1 ]]; then
    echo "$matches"
  elif [[ "$count" -gt 1 ]]; then
    echo "Multiple running php containers found, please pick one with --container:" >&2
    echo "$matches" >&2
    exit 1
  else
    echo "Could not find a running php container. Is the stack up (dcu / docker compose up)?" >&2
    exit 1
  fi
}

detect_network() {
  local matches
  matches="$(docker network ls --format '{{.Name}}' | grep '_msp_network$' || true)"
  local count
  count="$(echo "$matches" | grep -c . || true)"

  if [[ "$count" -eq 1 ]]; then
    echo "$matches"
  elif [[ "$count" -gt 1 ]]; then
    echo "Multiple matching networks found, please pick one with --network:" >&2
    echo "$matches" >&2
    exit 1
  else
    echo "Could not find a *_msp_network Docker network. Is the stack up?" >&2
    exit 1
  fi
}

[[ -n "$PHP_CONTAINER" ]] || PHP_CONTAINER="$(detect_php_container)"
[[ -n "$NETWORK_NAME" ]] || NETWORK_NAME="$(detect_network)"
[[ -n "$AGENT_NAME" ]] || AGENT_NAME="${COMPOSE_PROJECT_NAME:-mspchallenge}-blackfire-1"

echo "PHP container : ${PHP_CONTAINER}"
echo "Network       : ${NETWORK_NAME}"
echo "Agent name    : ${AGENT_NAME}"
echo

# ---------------------------------------------------------------------------
# 2. Install the Blackfire probe into the php container
# ---------------------------------------------------------------------------

get_php_ini_scan_dir() {
  local dir
  dir="$(docker exec "$PHP_CONTAINER" sh -c 'echo "$PHP_INI_DIR"' 2>/dev/null | tr -d '\r')"
  if [[ -n "$dir" ]]; then
    echo "${dir}/conf.d"
    return
  fi
  # Fallback for images that don't expose $PHP_INI_DIR: parse `php --ini`
  docker exec "$PHP_CONTAINER" php --ini 2>/dev/null \
    | awk -F': ' '/Scan for additional \.ini files in/ {print $2}'
}

install_probe() {
  local ini_dir
  ini_dir="$(get_php_ini_scan_dir)"
  if [[ -z "$ini_dir" ]]; then
    echo "Could not determine the PHP ini scan directory inside ${PHP_CONTAINER}." >&2
    exit 1
  fi

  if [[ "$FORCE" != "true" ]] \
      && docker exec "$PHP_CONTAINER" test -f "${ini_dir}/blackfire.ini" 2>/dev/null; then
    echo "Blackfire probe already installed in ${PHP_CONTAINER}, skipping (use --force to reinstall)."
    return
  fi

  echo "Installing Blackfire probe into ${PHP_CONTAINER} (ini dir: ${ini_dir})..."
  docker exec -i \
    -e INI_DIR="$ini_dir" \
    -e AGENT_NAME="$AGENT_NAME" \
    "$PHP_CONTAINER" sh -c "$(cat <<'EOF'
set -e
version=$(php -r "echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION.(PHP_ZTS ? '-zts' : '');")
architecture=$(uname -m)
curl -A "Docker" -o /tmp/blackfire-probe.tar.gz -D - -L -s \
  "https://blackfire.io/api/v1/releases/probe/php/linux/${architecture}/${version}"
mkdir -p /tmp/blackfire
tar zxpf /tmp/blackfire-probe.tar.gz -C /tmp/blackfire
mv /tmp/blackfire/blackfire-*.so "$(php -r "echo ini_get('extension_dir');")/blackfire.so"
printf "extension=blackfire.so\nblackfire.agent_socket=tcp://%s:8307\n" "$AGENT_NAME" \
  > "${INI_DIR}/blackfire.ini"
rm -rf /tmp/blackfire /tmp/blackfire-probe.tar.gz
EOF
)"
  echo "Probe installed."
}

install_probe

# ---------------------------------------------------------------------------
# 3. Ask for any missing Blackfire credentials
# ---------------------------------------------------------------------------

prompt_if_missing() {
  local var_name="$1" prompt_text="$2" secret="${3:-false}" current
  current="${!var_name:-}"
  if [[ -z "$current" ]]; then
    if [[ "$secret" == "true" ]]; then
      read -rsp "${prompt_text}: " current; echo
    else
      read -rp "${prompt_text}: " current
    fi
    printf -v "$var_name" '%s' "$current"
  fi
}

echo
echo "Blackfire credentials (find these under 'Account settings' / 'Credentials' on blackfire.io):"
prompt_if_missing BLACKFIRE_SERVER_ID     "Blackfire Server ID"
prompt_if_missing BLACKFIRE_SERVER_TOKEN  "Blackfire Server Token" true
prompt_if_missing BLACKFIRE_CLIENT_ID     "Blackfire Client ID"
prompt_if_missing BLACKFIRE_CLIENT_TOKEN  "Blackfire Client Token" true
echo

# ---------------------------------------------------------------------------
# 3b. Warn if the *php container's own* Blackfire env vars are missing/stale.
#     These are only read by in-process uses of the Blackfire SDK (e.g. the
#     websocket server's BlackfireWsServerPlugin, gated behind
#     BLACKFIRE_APM_ENABLED) -- NOT by the probe, and NOT by the 'blackfire
#     curl' profiling this script sets up. A running container's env can't be
#     updated by a restart, only by recreating it, so just flag it here.
# ---------------------------------------------------------------------------

check_php_blackfire_env() {
  local php_client_id php_client_token php_apm_enabled
  php_client_id="$(docker exec "$PHP_CONTAINER" sh -c 'echo "${BLACKFIRE_CLIENT_ID:-}"' 2>/dev/null)"
  php_client_token="$(docker exec "$PHP_CONTAINER" sh -c 'echo "${BLACKFIRE_CLIENT_TOKEN:-}"' 2>/dev/null)"
  php_apm_enabled="$(docker exec "$PHP_CONTAINER" sh -c 'echo "${BLACKFIRE_APM_ENABLED:-}"' 2>/dev/null)"

  if [[ -z "$php_client_id" || -z "$php_client_token" \
        || "$php_client_id" != "${BLACKFIRE_CLIENT_ID:-}" \
        || "$php_client_token" != "${BLACKFIRE_CLIENT_TOKEN:-}" ]]; then
    cat <<WARN
NOTE: ${PHP_CONTAINER}'s own BLACKFIRE_CLIENT_ID/BLACKFIRE_CLIENT_TOKEN are empty or don't match
      what was just entered above (BLACKFIRE_APM_ENABLED in that container: '${php_apm_enabled:-unset}').
      That's fine for the 'blackfire curl' profiling this script sets up -- the probe doesn't need them.
      It only matters if you want to use the Blackfire SDK directly from PHP code, e.g. the websocket
      server's BlackfireWsServerPlugin. That plugin reads credentials from the php container's OWN
      environment, and a running container's env can't be changed by a restart -- only by recreating it:
        BLACKFIRE_APM_ENABLED=1 BLACKFIRE_CLIENT_ID=... BLACKFIRE_CLIENT_TOKEN=... \\
          docker compose up -d --force-recreate php

WARN
  fi
}

check_php_blackfire_env

# ---------------------------------------------------------------------------
# 4. (Re)create the Blackfire agent container
# ---------------------------------------------------------------------------

if docker ps -a --format '{{.Names}}' | grep -qx "$AGENT_NAME"; then
  echo "Removing existing ${AGENT_NAME} container..."
  docker stop "$AGENT_NAME" >/dev/null 2>&1 || true
  docker rm "$AGENT_NAME" >/dev/null 2>&1 || true
fi

echo "Starting ${AGENT_NAME}..."
docker run -d \
  --name "$AGENT_NAME" \
  --network "$NETWORK_NAME" \
  --restart unless-stopped \
  --log-driver local \
  -e BLACKFIRE_LOG_LEVEL=4 \
  -e BLACKFIRE_SERVER_ID="${BLACKFIRE_SERVER_ID:-}" \
  -e BLACKFIRE_SERVER_TOKEN="${BLACKFIRE_SERVER_TOKEN:-}" \
  -e BLACKFIRE_CLIENT_ID="${BLACKFIRE_CLIENT_ID:-}" \
  -e BLACKFIRE_CLIENT_TOKEN="${BLACKFIRE_CLIENT_TOKEN:-}" \
  --expose 8307 \
  blackfire/blackfire:2 >/dev/null

# ---------------------------------------------------------------------------
# 5. Restart php so the newly installed probe actually loads
# ---------------------------------------------------------------------------

echo "Restarting ${PHP_CONTAINER} so the probe loads (FrankenPHP only loads extensions at startup)..."
docker restart "$PHP_CONTAINER" >/dev/null

# ---------------------------------------------------------------------------
# 6. Tips
# ---------------------------------------------------------------------------

cat <<TIPS

Blackfire is ready.

- Trigger a profile of an API call (run from the agent container, targeting
  the php container/service by its network hostname):
    docker exec ${AGENT_NAME} blackfire curl -X 'GET' \\
      'http://php/1/api/game/IsOnline' \\
      -H 'accept: application/json'

- Tail the agent logs:
    docker logs -f ${AGENT_NAME}
    (or, if you sourced docker-aliases.sh: dlb)

- Remove the agent container later with:
    docker stop ${AGENT_NAME} && docker rm ${AGENT_NAME}
  (the probe inside ${PHP_CONTAINER} stays installed until that container is recreated)
TIPS
