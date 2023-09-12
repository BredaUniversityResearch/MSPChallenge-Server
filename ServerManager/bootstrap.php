<?php
// fail-safe, in-case http server is not setup correctly.
if (!isset($_ENV['SYMFONY_DOTENV_VARS'])) { // detect if Symfony was used to open this php request
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../index.php');
    // always go through the main Symfony index, and let the Symfony router handle it
    require(__DIR__ . '/../index.php');
    return 1; // do not execute any further
}
return 0; // just continue on
