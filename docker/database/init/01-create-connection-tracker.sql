-- Connection tracker table for identifying which process owns each DB connection
CREATE DATABASE IF NOT EXISTS msp_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS msp_tracker.`connection` (
    connection_id BIGINT PRIMARY KEY,
    user VARCHAR(32) NOT NULL,
    process_name VARCHAR(128) NOT NULL,
    db_name VARCHAR(64),
    call_stack LONGTEXT NULL,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_process_name (process_name),
    INDEX idx_user (user),
    INDEX idx_last_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create event to remove disconnected rows from the tracker table.
-- A row is considered disconnected when its connection_id is no longer present in PROCESSLIST.
CREATE EVENT IF NOT EXISTS msp_tracker.cleanup_stale_connections
ON SCHEDULE EVERY 10 MINUTE
DO
  DELETE ct
  FROM msp_tracker.`connection` ct
  LEFT JOIN information_schema.PROCESSLIST pl ON pl.ID = ct.connection_id
  WHERE pl.ID IS NULL;
