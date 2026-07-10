# msp_tracker PHP extension

This extension provides a callback bridge for low-level DB connection tracking.

Current scope:
- register a userland callback (`msp_tracker_register_connection_callback`)
- emit payloads from extension code (`msp_tracker_emit_connection_event`)
- guard against nested callback recursion
- hook successful `PDO::__construct` calls and emit `pdo_connect_opened` payloads automatically

## Coverage model

- `PDO` / Doctrine DBAL / legacy `PDO` paths are detected by this extension.
- Async Drift / React MySQL connections still require the small app-side bridge in
  `src/Drift/Driver/Mysql/TrackingMysqlDriver.php` because they do not expose a PDO object
  the extension can query for `CONNECTION_ID()`.

## Functions

- `msp_tracker_register_connection_callback(callable $callback): bool`
- `msp_tracker_emit_connection_event(array $payload): bool`
- `msp_tracker_set_enabled(bool $enabled): void`
- `msp_tracker_is_enabled(): bool`

## Smoke test

```bash
php -d extension=msp_tracker.so php-ext/msp_tracker/examples/smoke.php
```

## PDO hook smoke test

This is a lightweight test without the full MSP setup. It prefers `sqlite::memory:` when `pdo_sqlite` is available.
If sqlite is unavailable, set a reachable DSN via env vars.

```bash
php -d extension=msp_tracker.so php-ext/msp_tracker/examples/pdo_hook_smoke.php
```

Optional env override when sqlite is unavailable:

```bash
TEST_PDO_DSN='mysql:host=127.0.0.1;port=3306;dbname=test' TEST_PDO_USER='root' TEST_PDO_PASS='' php -d extension=msp_tracker.so php-ext/msp_tracker/examples/pdo_hook_smoke.php
```


## Build notes

The project Docker image builds this extension via:

```dockerfile
COPY --link php-ext/msp_tracker /usr/src/php/ext/msp_tracker
RUN set -eux; docker-php-ext-install msp_tracker
```

