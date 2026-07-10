<?php

declare(strict_types=1);

$requiredFunctions = [
    'msp_tracker_register_connection_callback',
    'msp_tracker_is_enabled',
];

if (!extension_loaded('msp_tracker')) {
    fwrite(STDERR, "FAIL: msp_tracker extension is not loaded\n");
    exit(1);
}

foreach ($requiredFunctions as $functionName) {
    if (!function_exists($functionName)) {
        fwrite(STDERR, "FAIL: missing extension function {$functionName}\n");
        exit(1);
    }
}

$host = getenv('TEST_DB_HOST') ?: 'mariadb';
$port = (int) (getenv('TEST_DB_PORT') ?: '3306');
$dbName = getenv('TEST_DB_NAME') ?: 'msp_tracker_test';
$user = getenv('TEST_DB_USER') ?: 'root';
$password = getenv('TEST_DB_PASSWORD') ?: 'root';

$events = [];
msp_tracker_register_connection_callback(static function (array $payload) use (&$events): void {
    $events[] = $payload;
});

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$connected = false;
$lastError = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        $pdo->query('SELECT 1');
        $connected = true;
        break;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        usleep(500000);
    }
}

if (!$connected) {
    fwrite(STDERR, 'FAIL: could not connect to MariaDB: ' . ($lastError ?? '<unknown>') . PHP_EOL);
    exit(1);
}

$pdoEvents = array_values(array_filter($events, static fn (array $event): bool => ($event['event'] ?? null) === 'pdo_connect_opened'));

if ($pdoEvents === []) {
    fwrite(STDERR, "FAIL: expected at least one pdo_connect_opened event\n");
    exit(1);
}

$summary = [
    'total_events' => count($events),
    'pdo_events' => count($pdoEvents),
    'first_pdo_event' => $pdoEvents[0],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "PASS: msp_tracker callback hook captured PDO DB events\n";

