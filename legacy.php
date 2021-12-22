<?php
header('Content-Type: application/json');
    
if (version_compare(PHP_VERSION, '7.1.0') < 0) {
    throw new Exception("Required at least php version 7.1.0 for ReflectionNamedType");
}

require_once("api/class.apihelper.php");
require_once("helpers.php");

//unlimited memory usage on the server
ini_set('memory_limit', '-1');

APIHelper::SetupApiLoader();

ob_start();

$outputData = ["success" => false, "message"=> ""];
if (!empty($_GET['query'])) {
    $outputData = Router::RouteApiCall($_GET['query'], $_POST);
} else {
    http_response_code(404);
}

//$outputData["request_url"] = $_SERVER["REQUEST_URI"];
$pageOutput = ob_get_flush();
if (!empty($pageOutput)) {
    $outputData["message"].= PHP_EOL."Additional Debug Information: ".$pageOutput;
    $outputData["success"] = false;
}

echo json_encode($outputData);

// if (!$has_errored)
// {
//  unlink($fname);
// }
