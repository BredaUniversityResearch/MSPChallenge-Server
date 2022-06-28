<?php

use App\Domain\WsServer\WsServerOutput;
use Symfony\Component\Console\Output\OutputInterface;

function wdo(
    string $message,
    int $verbosity = OutputInterface::VERBOSITY_NORMAL
) {
    WsServerOutput::output($message, $verbosity);
}
