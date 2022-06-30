<?php

use App\Domain\WsServer\WsServerOutput;

function wdo(
    string $message,
    int $verbosity = WsServerOutput::VERBOSITY_DEFAULT_MESSAGE
) {
    WsServerOutput::output($message, $verbosity);
}
