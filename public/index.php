<?php
declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

//// todo: do this in web server
//$pattern = '#^/(\d+)/api/#';
//$requestUri = $_SERVER['REQUEST_URI'];
//if (preg_match($pattern, $requestUri, $matches)) {
//    $newRequestUri = substr($requestUri, strlen($matches[1]) + 1);
//    $_SERVER['REQUEST_URI'] = $newRequestUri;
//    $_SERVER['PATH_INFO'] = $newRequestUri;
//    $_SERVER['SCRIPT_NAME'] = $newRequestUri;
//    $_SERVER['HTTP_X_SESSION_ID'] = $matches[1];
//}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
