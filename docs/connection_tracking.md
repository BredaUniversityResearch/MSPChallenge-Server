# Connection Process Tracking

Track which PHP process owns each database connection, including idle/sleeping connections.

## How It Works

1. **`ProcessNameDetector`** intelligently detects the process name:
   - Environment variable `DB_PROCESS_NAME` (explicit override)
   - Supervisor process name (for messenger workers, etc.)
   - PHP SAPI + command name (e.g., `messenger_consume`, `chat_server`)
   - Falls back to `php_unknown`

2. **Connection initialization** inserts into `connection` table via `INIT_COMMAND`:
   ```sql
   INSERT INTO msp_tracker.connection 
   (connection_id, user, process_name, db_name) 
   VALUES (CONNECTION_ID(), USER(), 'web', 'msp_server_manager')
   ON DUPLICATE KEY UPDATE
   user = USER(), process_name = 'web', db_name = 'msp_server_manager',
   last_heartbeat = NOW();
   ```

3. **Sleeping connections** remain tracked until automatic cleanup removes stale entries

## Table Size Management

### Why the Table Grows

- Each `connection_id` can only appear once (PRIMARY KEY constraint)
- **At any given moment:** max rows = `max_connections` setting (typically 151)
- **But:** When connections close, rows remain unless cleaned up
- Over time with many connection cycles: table can grow unbounded

**Example:**
```
1000 connections open/close over time
→ Connection IDs 1-1000 all in tracker (even though only ~100 active now)
→ Without cleanup: table grows to thousands of rows
```

### Automatic Cleanup (Enabled by Default)

A MySQL event automatically runs every 10 minutes:
```sql
DELETE FROM msp_tracker.connection 
WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
```

This removes entries for connections closed 30+ minutes ago.

**Result:**
- Table stays small (roughly = `max_connections`)
- No manual maintenance needed
- Old stale entries automatically cleaned

## Setup

### 1. Docker Initialization (automatic)

The `docker/database/init/01-create-connection-tracker.sql` creates:
1. The tracker table
2. Automatic cleanup event

If your database already exists, run manually:

```bash
docker compose exec database mariadb -uroot -p"$env:DATABASE_PASSWORD" < docker/database/init/01-create-connection-tracker.sql
```

### 2. Configure Process Names

Set process names for different services in `docker-compose.yml`:

```yaml
php:
  environment:
    DB_PROCESS_NAME: web

# For messenger workers (if containerized)
messenger_1:
  environment:
    DB_PROCESS_NAME: messenger_1
```

Or for CLI commands:
```bash
DB_PROCESS_NAME=messenger_consume bin/console messenger:consume
DB_PROCESS_NAME=chat_server bin/chat-server.php
```

### 3. Query the Tracker

See all connections with their process names:

```sql
SELECT ct.connection_id, ct.user, ct.process_name, ct.db_name, 
       pl.COMMAND, pl.TIME, pl.INFO
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
ORDER BY ct.last_heartbeat DESC;
```

See connection summary by process:

```sql
SELECT ct.process_name, COUNT(*) as total, 
       SUM(CASE WHEN pl.COMMAND = 'Sleep' THEN 1 ELSE 0 END) as idle
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
GROUP BY ct.process_name;
```

Monitor table size (should stay small):

```sql
SELECT 
    COUNT(*) as total_tracker_rows,
    SUM(CASE WHEN pl.ID IS NULL THEN 1 ELSE 0 END) as stale_disconnected,
    SUM(CASE WHEN pl.ID IS NOT NULL THEN 1 ELSE 0 END) as active_connected
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id;
```

## Automatic Cleanup Details

**Event Name:** `cleanup_stale_connections`
- **Frequency:** Every 10 minutes
- **Removes:** Entries not updated in 30+ minutes
- **Why 30 min?** Balances between cleanup frequency and keeping short-lived connection history

### Adjusting Cleanup Thresholds

If you want more aggressive cleanup (or less), modify `01-create-connection-tracker.sql`:

```sql
-- Cleanup every 5 minutes, remove entries older than 5 minutes
ALTER EVENT msp_tracker.cleanup_stale_connections 
ON SCHEDULE EVERY 5 MINUTE 
DO DELETE FROM msp_tracker.connection 
WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
```

Or disable it entirely:
```sql
ALTER EVENT msp_tracker.cleanup_stale_connections DISABLE;
```

## Troubleshooting

**Table growing too fast?**
- Check if `cleanup_stale_connections` event is enabled:
  ```sql
  SELECT STATUS FROM information_schema.EVENTS 
  WHERE EVENT_SCHEMA = 'msp_tracker'
    AND EVENT_NAME = 'cleanup_stale_connections';
  ```
- If disabled, enable it: `ALTER EVENT msp_tracker.cleanup_stale_connections ENABLE;`
- Manually clean old entries: 
  ```sql
  DELETE FROM msp_tracker.connection 
  WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
  ```

**Connection leak detection?**
- Look for processes with stale entries (connected but no heartbeat):
  ```sql
  SELECT ct.process_name, COUNT(*) as stale_count
  FROM msp_tracker.connection ct
  LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
  WHERE pl.ID IS NULL
  GROUP BY ct.process_name
  ORDER BY stale_count DESC;
  ```

**Table not found error?**
- Ensure database is initialized: `docker compose exec database mariadb -uroot -p"$password" -e "SELECT * FROM msp_tracker.connection LIMIT 1;"`
- Recreate manually from `docker/database/init/01-create-connection-tracker.sql`

**Process name not showing?**
- Check `DB_PROCESS_NAME` environment variable is set
- Verify process is using Doctrine connections (not raw PDO)
- Check logs for connection initialization errors


