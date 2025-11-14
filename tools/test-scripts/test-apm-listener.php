<?php

$host = '127.0.0.1';
$port = 45100; // your listener port
$message = "Hello, APM Listener!\n";

$fp = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30);
if (!$fp) {
    echo "Error: $errstr ($errno)\n";
} else {
    fwrite($fp, $message);
    fclose($fp);
}
