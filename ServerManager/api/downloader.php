<?php

use ServerManager\FileDownloader;
use ServerManager\User;

// setup custom error handling while downloading the file.
// Any error/exception will lead to "die", so there is no need call restore_error_handler()/restore_exception_handler()
set_exception_handler(function ($e) {
    $msg = $e->getMessage();
    if (is_a($e, \ErrorException::class)) {
        $msg = $e->getSeverity().": ".$msg." - on line ".$e->getLine()." of file ".
            $e->getFile();
    }
    die($msg);
});
set_error_handler(function ($errno, $errMsg, $errFile, $errLine) {
    throw new \ErrorException($errMsg, 0, $errno, $errFile, $errLine);
});

require __DIR__ . '/../init.php';
$user = new User();
$user->hasToBeLoggedIn();

$allowed = array(
    // to extend this, just create a method that returns either a file path or an array of two values:
    //   1) filename 2) file content
    "gamesession/getArchive",
    "gamesession/getConfigWithPlans",
    "gameconfig/getFile",
    "gamesave/getFullZipPath"
);

if (!isset($_GET["request"])) {
    throw new Exception("Request not received."); // will be caught by custom error/exception handler, see above
}
if (!isset($_GET['id'])) {
    throw new Exception("ID not received."); // will be caught by custom error/exception handler, see above
}
if (!in_array($_GET['request'], $allowed)) {
    throw new Exception("Request not allowed."); // will be caught by custom error/exception handler, see above
}

$id = intval($_GET['id']);
$request_array = explode("/", $_GET['request']);
$object = new $request_array[0];
$object->id = $id;
$object->get();
$method = $request_array[1];

$fileDownloader = new FileDownloader($object->$method());
$fileDownloader->Return();
