<?php

if (!function_exists('msp_tracker_register_connection_callback')) {
    function msp_tracker_register_connection_callback(callable $callback): bool
    {
        return false;
    }
}
if (!function_exists('msp_tracker_emit_connection_event')) {
    function msp_tracker_emit_connection_event(array $payload): bool
    {
        return false;
    }
}

if (!function_exists('msp_tracker_set_enabled')) {
    function msp_tracker_set_enabled(bool $enabled): void
    {
    }
}

if (!function_exists('msp_tracker_is_enabled')) {
    function msp_tracker_is_enabled(): bool
    {
        return false;
    }
}
