# msp_tracker isolated Docker test

This folder provides an isolated test setup for the `msp_tracker` extension, without the full MSP application stack.

It builds a minimal FrankenPHP-based image that compiles `msp_tracker`, starts a local MariaDB in Docker Compose, and runs a small PHP test runner.

## What it validates

- extension is loaded and callable
- `PDO::__construct` hook emits `pdo_connect_opened`

## Run

```bash
docker compose -f php-ext/msp_tracker/testing/docker-compose.yml up --build --abort-on-container-exit --exit-code-from msp-tracker-test
```

## Cleanup

```bash
docker compose -f php-ext/msp_tracker/testing/docker-compose.yml down -v
```

## Notes

- This setup intentionally avoids supervisor and the full MSP runtime.

