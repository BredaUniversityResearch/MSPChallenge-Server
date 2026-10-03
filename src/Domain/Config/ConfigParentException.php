<?php

namespace App\Domain\Config;

/**
 * The parent of a config cannot be used: it does not exist, it is not a generic config, or the parents loop.
 */
final class ConfigParentException extends \RuntimeException
{
}
