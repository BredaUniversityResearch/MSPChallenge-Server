<?php

use App\Domain\API\v1\Router;
use App\Domain\Common\MessageJsonResponse;

header('Content-Type: application/json');
    
if (version_compare(PHP_VERSION, '7.1.0') < 0) {
    throw new Exception("Required at least php version 7.1.0 for ReflectionNamedType");
}

//unlimited memory usage on the server
ini_set('memory_limit', '-1');

ob_start();

/** @var MessageJsonResponse $reponse */
$response = new MessageJsonResponse(status: 404);
if (!empty($_GET['query'])) {
    $response = Router::RouteApiCall($_GET['query'], $_POST);
}

$pageOutput = ob_get_flush();
if (!empty($pageOutput)) {
    $response->setMessage(($response->getMessage() ?? '').PHP_EOL."Additional Debug Information: ".$pageOutput);
    $response->setStatusCode(500);
}

return $response;
