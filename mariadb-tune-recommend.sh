#!/usr/bin/env bash
#
# mariadb-tune-recommend.sh
#
# Run from the HOST machine (not inside the container). Auto-detects a running
# mariadb/mysql container, reads its LIVE global variables plus available
# memory (container cgroup limit if set, else host RAM), and prints a
# current-vs-recommended settings table.
#
# These are heuristic starting points, not guarantees. Validate under real
# load (Threads_connected, Max_used_connections) and watch for OOM after
# applying anything.

set -euo pipefail

# ---------------- Defaults (override via flags) ----------------
CONTAINER=""
MYSQL_USER="root"
MYSQL_PASS=""
MEM_OVERRIDE_MB=""
PER_CONN_MEM_MB=12    # rough per-connection memory budget (thread_stack + buffers)
BUFFER_POOL_PCT=70    # % of available memory to allocate to innodb_buffer_pool_size
APPLY=0               # --apply: actually change settings, not just report
ASSUME_YES=0          # -y / --yes: skip confirmation prompt before applying
CNF_FILENAME="99-tuning.cnf"
CNF_PATH_IN_CONTAINER="/etc/mysql/mariadb.conf.d/${CNF_FILENAME}"

usage() {
  cat <<EOF
Usage: $0 [-c container] [-u user] [-p password] [-m mem_override_mb] [-b buffer_pool_pct] [-e per_conn_mb] [--apply] [-y]

  -c   Container name or ID (skips auto-detection)
  -u   MySQL user (default: root)
  -p   MySQL password (default: tries container env vars, else prompts)
  -m   Memory budget in MB (skips the interactive prompt if provided)
  -b   % of available memory to assign to innodb_buffer_pool_size (default: 70)
  -e   Estimated per-connection memory in MB, used to size max_connections (default: 12)
  --apply   Actually apply recommendations: SET GLOBAL for dynamic vars now, plus
            write a persistent .cnf file to ${CNF_PATH_IN_CONTAINER}
            (requires that path to be a mounted volume to survive container recreation)
  -y, --yes  Skip the confirmation prompt when using --apply

Examples:
  $0
  $0 -c my_mariadb -u root -p secret
  $0 -m 4096 -b 60
  $0 --apply
  $0 --apply -y
EOF
  exit 1
}

# Pre-scan for long options (--apply, --yes) since getopts only handles short flags
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --apply) APPLY=1 ;;
    --yes)   ASSUME_YES=1 ;;
    *)       ARGS+=("$arg") ;;
  esac
done
set -- "${ARGS[@]}"

while getopts "c:u:p:m:b:e:yh" opt; do
  case "$opt" in
    c) CONTAINER="$OPTARG" ;;
    u) MYSQL_USER="$OPTARG" ;;
    p) MYSQL_PASS="$OPTARG" ;;
    m) MEM_OVERRIDE_MB="$OPTARG" ;;
    b) BUFFER_POOL_PCT="$OPTARG" ;;
    e) PER_CONN_MEM_MB="$OPTARG" ;;
    y) ASSUME_YES=1 ;;
    h) usage ;;
    *) usage ;;
  esac
done

# ---------------- 1. Detect container ----------------
if [[ -z "$CONTAINER" ]]; then
  CONTAINER=$(docker ps --format '{{.ID}} {{.Image}}' | grep -Ei 'mariadb|mysql' | head -n1 | awk '{print $1}') || true
  if [[ -z "$CONTAINER" ]]; then
    echo "ERROR: No running mariadb/mysql container found. Use -c <name|id> to specify one." >&2
    exit 1
  fi
fi

CONTAINER_NAME=$(docker inspect --format '{{.Name}}' "$CONTAINER" 2>/dev/null | sed 's#^/##') || {
  echo "ERROR: Could not inspect container '$CONTAINER'. Is it running?" >&2
  exit 1
}
echo "Using container: ${CONTAINER_NAME} (${CONTAINER})"

# ---------------- 2. Resolve credentials ----------------
if [[ -z "$MYSQL_PASS" ]]; then
  MYSQL_PASS=$(docker exec "$CONTAINER" printenv MARIADB_ROOT_PASSWORD 2>/dev/null || true)
  if [[ -z "$MYSQL_PASS" ]]; then
    MYSQL_PASS=$(docker exec "$CONTAINER" printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
  fi
fi

run_mysql() {
  if [[ -n "$MYSQL_PASS" ]]; then
    docker exec "$CONTAINER" mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" -N -B -e "$1" 2>/dev/null
  else
    docker exec "$CONTAINER" mysql -u "$MYSQL_USER" -N -B -e "$1" 2>/dev/null
  fi
}

if ! run_mysql "SELECT 1;" >/dev/null 2>&1; then
  read -rsp "Could not auto-authenticate. Enter password for MySQL user '${MYSQL_USER}': " MYSQL_PASS
  echo
  if ! run_mysql "SELECT 1;" >/dev/null 2>&1; then
    echo "ERROR: Authentication failed." >&2
    exit 1
  fi
fi

# ---------------- 3. Pull current settings ----------------
declare -A CUR
VARS=(
  max_connections
  innodb_buffer_pool_size
  key_buffer_size
  thread_stack
  sort_buffer_size
  join_buffer_size
  read_buffer_size
  read_rnd_buffer_size
  open_files_limit
  table_open_cache
  tmp_table_size
  max_heap_table_size
  innodb_log_file_size
)

for v in "${VARS[@]}"; do
  CUR[$v]=$(run_mysql "SHOW GLOBAL VARIABLES LIKE '${v}';" | awk '{print $2}')
  CUR[$v]=${CUR[$v]:-"?"}
done

# ---------------- 3b. Read the container's ACTUAL file-descriptor ulimit ----------------
# mysqld runs as PID 1 in the official mariadb/mysql images (the entrypoint execs it,
# replacing the shell), so /proc/1/limits reflects the limit mysqld itself is running
# under -- more reliable than a fresh `docker exec ... ulimit -n`, which only reflects
# a new exec session and doesn't tell you what the already-running mysqld process got.
PID1_LIMITS=$(docker exec "$CONTAINER" sh -c "cat /proc/1/limits" 2>/dev/null || true)
SOFT_NOFILE=$(echo "$PID1_LIMITS" | awk '/^Max open files/ {print $4}')
HARD_NOFILE=$(echo "$PID1_LIMITS" | awk '/^Max open files/ {print $5}')
SOFT_NOFILE=${SOFT_NOFILE:-"?"}
HARD_NOFILE=${HARD_NOFILE:-"?"}

# ---------------- 4. Determine available memory ----------------
HOST_MEM_TOTAL_KB=$(awk '/^MemTotal:/ {print $2}' /proc/meminfo)
HOST_MEM_AVAIL_KB=$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)
# Fallback for older kernels without MemAvailable
if [[ -z "$HOST_MEM_AVAIL_KB" ]]; then
  HOST_MEM_AVAIL_KB=$(awk '/^MemFree:/ {print $2}' /proc/meminfo)
fi

HOST_MEM_TOTAL_MB=$(( HOST_MEM_TOTAL_KB / 1024 ))
HOST_MEM_AVAIL_MB=$(( HOST_MEM_AVAIL_KB / 1024 ))
HOST_MEM_TOTAL_GB=$(( HOST_MEM_TOTAL_MB / 1024 ))
HOST_MEM_AVAIL_GB=$(( HOST_MEM_AVAIL_MB / 1024 ))

echo
echo "Host total memory:     ${HOST_MEM_TOTAL_MB} MB (~${HOST_MEM_TOTAL_GB} GB)"
echo "Host available memory: ${HOST_MEM_AVAIL_MB} MB (~${HOST_MEM_AVAIL_GB} GB)"

# Compute a sane power-of-two-GB default (2, 4, 8, 16, ...) that's still below
# available memory, used only if the user just hits Enter at the prompt.
compute_default_mb() {
  local avail_gb="$1"
  if (( avail_gb < 2 )); then
    # Not much to work with; fall back to half the available MB instead of GB steps
    echo $(( HOST_MEM_AVAIL_MB / 2 ))
    return
  fi
  local candidate=2
  while (( candidate * 2 < avail_gb )); do
    candidate=$(( candidate * 2 ))
  done
  echo $(( candidate * 1024 ))
}

DEFAULT_MEM_MB=$(compute_default_mb "$HOST_MEM_AVAIL_GB")

if [[ -n "$MEM_OVERRIDE_MB" ]]; then
  TOTAL_MEM_MB="$MEM_OVERRIDE_MB"
  echo "Using memory budget passed via -m: ${TOTAL_MEM_MB} MB"
else
  read -rp "Enter memory budget for MariaDB tuning in MB [default: ${DEFAULT_MEM_MB} MB]: " USER_MEM_MB
  if [[ -z "$USER_MEM_MB" ]]; then
    TOTAL_MEM_MB="$DEFAULT_MEM_MB"
    echo "Using default: ${TOTAL_MEM_MB} MB"
  else
    TOTAL_MEM_MB="$USER_MEM_MB"
  fi
fi

# ---------------- 5. Compute recommendations ----------------
BUFFER_POOL_MB=$(( TOTAL_MEM_MB * BUFFER_POOL_PCT / 100 ))
REMAINING_MB=$(( TOTAL_MEM_MB - BUFFER_POOL_MB ))

REC_MAX_CONN=$(( REMAINING_MB / PER_CONN_MEM_MB ))
(( REC_MAX_CONN < 50 ))   && REC_MAX_CONN=50
(( REC_MAX_CONN > 2000 )) && REC_MAX_CONN=2000

REC_OPEN_FILES=$(( REC_MAX_CONN * 3 + 500 ))
REC_TABLE_OPEN_CACHE=$(( REC_MAX_CONN * 2 ))

# Compare mysqld's actual ulimit (from /proc/1/limits, section 3b) against what we'd recommend
if [[ "$HARD_NOFILE" =~ ^[0-9]+$ ]]; then
  if (( HARD_NOFILE >= REC_OPEN_FILES )); then
    OPEN_FILES_NOTE="container ulimit hard=${HARD_NOFILE} already covers this, no ulimit change needed"
    OPEN_FILES_ULIMIT_OK=1
  else
    OPEN_FILES_NOTE="container ulimit hard=${HARD_NOFILE} is BELOW this -- needs raising in compose + recreate"
    OPEN_FILES_ULIMIT_OK=0
  fi
else
  OPEN_FILES_NOTE="could not read container ulimit (checked /proc/1/limits)"
  OPEN_FILES_ULIMIT_OK=0
fi

REC_INNODB_BUFFER_POOL_BYTES=$(( BUFFER_POOL_MB * 1048576 ))
REC_SORT_BUFFER_BYTES=$(( 2 * 1048576 ))      # 2M
REC_JOIN_BUFFER_BYTES=$(( 2 * 1048576 ))      # 2M
REC_READ_BUFFER_BYTES=$(( 128 * 1024 ))       # 128K
REC_READ_RND_BUFFER_BYTES=$(( 256 * 1024 ))   # 256K
REC_TMP_TABLE_BYTES=$(( 64 * 1048576 ))       # 64M

# ---------------- 6. Formatting helpers ----------------
human() {
  local val="$1"
  if [[ "$val" =~ ^[0-9]+$ ]]; then
    if (( val >= 1073741824 )); then
      awk "BEGIN{printf \"%.1fG\", ${val}/1073741824}"
    elif (( val >= 1048576 )); then
      awk "BEGIN{printf \"%.1fM\", ${val}/1048576}"
    elif (( val >= 1024 )); then
      awk "BEGIN{printf \"%.1fK\", ${val}/1024}"
    else
      echo "${val}B"
    fi
  else
    echo "$val"
  fi
}

row() {
  local name="$1" cur="$2" rec="$3" note="$4"
  printf "%-26s %-14s %-14s %s\n" "$name" "$(human "$cur")" "$(human "$rec")" "$note"
}

# ---------------- 7. Report ----------------
echo
printf "%-26s %-14s %-14s %s\n" "Setting" "Current" "Recommended" "Notes"
printf '%s\n' "--------------------------------------------------------------------------------------------"

row "max_connections"            "${CUR[max_connections]}"            "$REC_MAX_CONN"                  "sized from ${REMAINING_MB}MB @ ~${PER_CONN_MEM_MB}MB/conn"
row "innodb_buffer_pool_size"    "${CUR[innodb_buffer_pool_size]}"    "$REC_INNODB_BUFFER_POOL_BYTES"  "${BUFFER_POOL_PCT}% of ${TOTAL_MEM_MB}MB available"
row "sort_buffer_size"           "${CUR[sort_buffer_size]}"           "$REC_SORT_BUFFER_BYTES"          "per-connection; avoid oversizing"
row "join_buffer_size"           "${CUR[join_buffer_size]}"           "$REC_JOIN_BUFFER_BYTES"          "per-connection; avoid oversizing"
row "read_buffer_size"           "${CUR[read_buffer_size]}"           "$REC_READ_BUFFER_BYTES"          ""
row "read_rnd_buffer_size"       "${CUR[read_rnd_buffer_size]}"       "$REC_READ_RND_BUFFER_BYTES"      ""
row "open_files_limit"           "${CUR[open_files_limit]}"           "$REC_OPEN_FILES"                 "$OPEN_FILES_NOTE"
row "table_open_cache"           "${CUR[table_open_cache]}"           "$REC_TABLE_OPEN_CACHE"           ""
row "tmp_table_size"             "${CUR[tmp_table_size]}"             "$REC_TMP_TABLE_BYTES"            "keep equal to max_heap_table_size"
row "max_heap_table_size"        "${CUR[max_heap_table_size]}"        "$REC_TMP_TABLE_BYTES"            "keep equal to tmp_table_size"
row "thread_stack"               "${CUR[thread_stack]}"               "${CUR[thread_stack]}"            "default is generally fine, left as-is"
row "key_buffer_size"            "${CUR[key_buffer_size]}"            "${CUR[key_buffer_size]}"         "only matters for MyISAM tables"
row "innodb_log_file_size"       "${CUR[innodb_log_file_size]}"       "${CUR[innodb_log_file_size]}"    "review manually; changing requires care/restart sequence"

echo
echo "Container file-descriptor ulimit (mysqld, PID 1): soft=${SOFT_NOFILE} hard=${HARD_NOFILE}"
echo "Basis: ${TOTAL_MEM_MB}MB available memory, ${BUFFER_POOL_PCT}% to InnoDB buffer pool,"
echo "       ~${PER_CONN_MEM_MB}MB budgeted per connection for max_connections sizing."
echo
echo "These are starting-point heuristics, not guarantees. After applying changes, watch:"
echo "  SHOW GLOBAL STATUS LIKE 'Threads_connected';"
echo "  SHOW GLOBAL STATUS LIKE 'Max_used_connections';"
echo "  SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_read_requests';"
echo "and monitor for OOM kills (dmesg / docker logs) for a day or two."

# ---------------- 8. Apply (optional) ----------------
if [[ "$APPLY" -eq 1 ]]; then
  echo
  echo "=============================== APPLY MODE ==============================="

  # Vars that can be changed live with SET GLOBAL (no restart needed)
  declare -A DYNAMIC_SET=(
    [max_connections]="$REC_MAX_CONN"
    [innodb_buffer_pool_size]="$REC_INNODB_BUFFER_POOL_BYTES"
    [sort_buffer_size]="$REC_SORT_BUFFER_BYTES"
    [join_buffer_size]="$REC_JOIN_BUFFER_BYTES"
    [read_buffer_size]="$REC_READ_BUFFER_BYTES"
    [read_rnd_buffer_size]="$REC_READ_RND_BUFFER_BYTES"
    [table_open_cache]="$REC_TABLE_OPEN_CACHE"
    [tmp_table_size]="$REC_TMP_TABLE_BYTES"
    [max_heap_table_size]="$REC_TMP_TABLE_BYTES"
  )

  # Vars that require a restart to take effect (written to the .cnf but not SET GLOBAL'd)
  declare -A STATIC_SET=(
    [open_files_limit]="$REC_OPEN_FILES"
  )

  echo "Will SET GLOBAL immediately (no restart needed):"
  for v in "${!DYNAMIC_SET[@]}"; do
    echo "  $v = ${DYNAMIC_SET[$v]}"
  done
  echo
  echo "Will write to persistent config (needs a container restart to take effect):"
  for v in "${!STATIC_SET[@]}"; do
    echo "  $v = ${STATIC_SET[$v]}"
  done
  echo

  # ---- Check whether the config dir is actually a mounted volume ----
  MOUNT_INFO=$(docker inspect --format '{{range .Mounts}}{{.Destination}}|{{.Type}}|{{.Name}}{{"\n"}}{{end}}' "$CONTAINER" 2>/dev/null | grep -F "/etc/mysql/mariadb.conf.d" || true)

  if [[ -n "$MOUNT_INFO" ]]; then
    echo "Confirmed: /etc/mysql/mariadb.conf.d is a mounted volume (${MOUNT_INFO}) — changes will survive container recreation."
  else
    echo "WARNING: /etc/mysql/mariadb.conf.d does not appear to be a mounted volume on this container."
    echo "         The .cnf file will still be written, but it will be LOST if the container is recreated"
    echo "         (docker compose up -d --force-recreate, image update, docker rm + up, etc)."
  fi
  echo

  if [[ "$ASSUME_YES" -ne 1 ]]; then
    read -rp "Proceed with applying these changes to ${CONTAINER_NAME}? [y/N] " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
      echo "Aborted. No changes made."
      exit 0
    fi
  fi

  # ---- 8a. Apply dynamic vars live ----
  echo
  echo "Applying dynamic settings via SET GLOBAL..."
  for v in "${!DYNAMIC_SET[@]}"; do
    val="${DYNAMIC_SET[$v]}"
    if run_mysql "SET GLOBAL ${v} = ${val};" >/dev/null 2>&1; then
      echo "  OK   $v = $val"
    else
      echo "  FAIL $v = $val (check server logs / permissions)"
    fi
  done

  # ---- 8b. Write persistent .cnf file into the container ----
  echo
  echo "Writing persistent config to ${CNF_PATH_IN_CONTAINER}..."
  {
    echo "# Generated by mariadb-tune-recommend.sh on $(date -u +%FT%TZ)"
    echo "# Basis: ${TOTAL_MEM_MB}MB available memory, ${BUFFER_POOL_PCT}% to InnoDB buffer pool"
    echo "[mysqld]"
    for v in "${!DYNAMIC_SET[@]}"; do
      echo "${v} = ${DYNAMIC_SET[$v]}"
    done
    for v in "${!STATIC_SET[@]}"; do
      echo "${v} = ${STATIC_SET[$v]}"
    done
  } | docker exec -i "$CONTAINER" sh -c "cat > ${CNF_PATH_IN_CONTAINER}"

  if [[ $? -eq 0 ]]; then
    echo "  OK   wrote ${CNF_PATH_IN_CONTAINER} inside the container"
  else
    echo "  FAIL could not write config file — check container permissions"
  fi

  echo
  if [[ -n "$MOUNT_INFO" ]]; then
    echo "Config is persisted (mounted volume). Restart the container to apply the"
    echo "restart-only settings (open_files_limit):"
    echo "  docker restart ${CONTAINER_NAME}"
  else
    echo "Config was written but is NOT on a mounted volume — it will not survive"
    echo "container recreation. Consider adding a volume for /etc/mysql/mariadb.conf.d"
    echo "in your compose file, then re-run with --apply."
  fi

  echo
  if [[ "$OPEN_FILES_ULIMIT_OK" -eq 1 ]]; then
    echo "Container ulimit check: hard=${HARD_NOFILE} already covers the recommended"
    echo "open_files_limit (${REC_OPEN_FILES}) -- no ulimit change needed."
  else
    echo "NOTE on open_files_limit: the container's current file-descriptor hard limit"
    echo "(${HARD_NOFILE}) is below the recommended open_files_limit (${REC_OPEN_FILES})."
    echo "This can only be raised by recreating the container with an explicit ulimit,"
    echo "e.g. in compose:"
    echo "  ulimits:"
    echo "    nofile:"
    echo "      soft: ${REC_OPEN_FILES}"
    echo "      hard: ${REC_OPEN_FILES}"
  fi
  echo "============================================================================="
fi
