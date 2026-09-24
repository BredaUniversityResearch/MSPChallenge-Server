<?php

if (!extension_loaded('msp_tracker')) {
    fwrite(STDERR, "msp_tracker extension is not loaded\n");
    exit(1);
}

msp_tracker_register_connection_callback(static function (array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
});

msp_tracker_emit_connection_event([
    'source' => 'smoke-test',
    'driver' => 'pdo_mysql',
    'db_host' => 'database',
    'db_port' => 3306,
    'db_name' => 'msp_server_manager',
    'connection_id' => 123,
]);

