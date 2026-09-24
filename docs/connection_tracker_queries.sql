-- RAW processlist
SHOW PROCESSLIST;

-- Query to see live connections (active and idle) with their assigned process names

SELECT
    ct.connection_id,
    ct.user,
    ct.process_name,
    ct.db_name,
    pl.ID as processlist_id,
    pl.COMMAND,
    pl.TIME as duration_seconds,
    pl.STATE,
    pl.INFO as current_query,
    ct.connected_at,
    ct.last_heartbeat,
    CASE
        WHEN pl.COMMAND = 'Sleep' THEN 'IDLE'
        ELSE 'ACTIVE'
    END as connection_status
FROM msp_tracker.connection ct
INNER JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
ORDER BY ct.last_heartbeat DESC;

-- ============================================================
-- Quick view: Current active queries by process
-- ============================================================
SELECT
    ct.process_name,
    COUNT(*) as total_connections,
    SUM(CASE WHEN pl.COMMAND = 'Sleep' THEN 1 ELSE 0 END) as idle_connections,
    SUM(CASE WHEN pl.COMMAND != 'Sleep' THEN 1 ELSE 0 END) as active_connections,
    GROUP_CONCAT(DISTINCT ct.user SEPARATOR ', ') as users
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
GROUP BY ct.process_name
ORDER BY active_connections DESC, total_connections DESC;

-- ============================================================
-- View active queries (non-idle) by process with details
-- ============================================================
SELECT
    ct.process_name,
    ct.user,
    pl.TIME as duration_seconds,
    pl.STATE,
    SUBSTRING(pl.INFO, 1, 100) as query_snippet
FROM msp_tracker.connection ct
INNER JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
WHERE pl.COMMAND != 'Sleep'
ORDER BY pl.TIME DESC;

-- ============================================================
-- Monitor tracker table growth and disconnected entries
-- ============================================================
SELECT
    COUNT(*) as total_tracker_entries,
    SUM(CASE WHEN pl.ID IS NULL THEN 1 ELSE 0 END) as disconnected_entries,
    SUM(CASE WHEN pl.ID IS NOT NULL THEN 1 ELSE 0 END) as connected_entries,
    ROUND(COUNT(*) / (SELECT COUNT(*) FROM information_schema.PROCESSLIST) * 100, 2) as overhead_percent,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as table_size_mb
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id,
information_schema.TABLES t
WHERE t.TABLE_SCHEMA = 'msp_tracker'
  AND t.TABLE_NAME = 'connection';

-- ============================================================
-- Find potentially leaking connections (not heartbeating)
-- If you see many rows older than your expected connection lifetime, investigate leaks
-- ============================================================
SELECT
    ct.process_name,
    ct.user,
    COUNT(*) as stale_connection_count,
    MIN(ct.last_heartbeat) as oldest_heartbeat,
    TIMESTAMPDIFF(MINUTE, MAX(ct.last_heartbeat), NOW()) as max_age_minutes
FROM msp_tracker.connection ct
LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
WHERE pl.ID IS NULL  -- disconnected/stale
GROUP BY ct.process_name, ct.user
ORDER BY max_age_minutes DESC;

-- ============================================================
-- Manual cleanup: Remove entries older than 30 minutes
-- ============================================================
DELETE FROM msp_tracker.connection
WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 30 MINUTE);

-- ============================================================
-- Check if automatic cleanup event is enabled and working
-- ============================================================
SELECT
    EVENT_SCHEMA,
    EVENT_NAME,
    STATUS,
    EVENT_DEFINITION,
    EXECUTE_AT,
    INTERVAL_VALUE,
    INTERVAL_FIELD,
    LAST_EXECUTED,
    LAST_ALTERED
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = 'msp_tracker'
  AND EVENT_NAME = 'cleanup_stale_connections';


