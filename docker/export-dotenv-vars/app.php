<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(getcwd()."/.env");
unset ($argv[0]);
foreach ($argv as $env) {
    if (!array_key_exists($env, $_ENV)) {
        continue;
    }
    echo $env."=".$_ENV[$env]." ";
}
return 0;
