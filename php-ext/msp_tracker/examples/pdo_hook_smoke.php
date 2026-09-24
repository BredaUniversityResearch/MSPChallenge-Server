<?php

declare(strict_types=1);

if (!extension_loaded('msp_tracker')) {
    fwrite(STDOUT, "SKIP: msp_tracker extension is not loaded\n");
    exit(0);
}

if (!class_exists(PDO::class)) {
    fwrite(STDOUT, "SKIP: PDO extension is not available\n");
    exit(0);
}

$captured = [];

msp_tracker_register_connection_callback(static function (array $payload) use (&$captured): void {
    if (($payload['event'] ?? null) === 'pdo_connect_opened') {
        $captured[] = $payload;
    }
});

$dsn = null;
$user = null;
$pass = null;
$options = [];

$drivers = PDO::getAvailableDrivers();
if (in_array('sqlite', $drivers, true)) {
    $dsn = 'sqlite::memory:';
} elseif (($envDsn = getenv('TEST_PDO_DSN')) !== false && $envDsn !== '') {
    $dsn = $envDsn;
    $user = getenv('TEST_PDO_USER') !== false ? (string) getenv('TEST_PDO_USER') : null;
    $pass = getenv('TEST_PDO_PASS') !== false ? (string) getenv('TEST_PDO_PASS') : null;
} else {
    fwrite(STDOUT, "SKIP: no sqlite driver and TEST_PDO_DSN not set\n");
    exit(0);
}

try {
    new PDO((string) $dsn, $user, $pass, $options);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: PDO connect failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

if ($captured === []) {
    fwrite(STDERR, "FAIL: no pdo_connect_opened payload captured\n");
    exit(1);
}

fwrite(STDOUT, json_encode($captured[0], JSON_UNESCAPED_SLASHES) . PHP_EOL);
fwrite(STDOUT, "PASS: PDO hook emitted callback payload\n");

