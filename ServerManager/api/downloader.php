<?php
require_once '../init.php'; 
$fileDownloader = new FileDownloader;
$user = new User();
$user->hastobeLoggedIn();

$allowed = array(
    "gamesession/getArchive", // to extend this, just create a method that returns either a file path or an array of two values: 1) filename 2) file content
    "gamesession/getConfigWithPlans",
    "gameconfig/getFile",
    "gamesave/getFullZipPath"
);

if (!isset($_GET["request"])) throw new Exception("Request not received.");
if (!isset($_GET['id'])) throw new Exception("ID not received.");
if (!in_array($_GET['request'], $allowed)) throw new Exception("Request not allowed.");

$id = intval($_GET['id']);
$request_array = explode("/", $_GET['request']);
$object = new $request_array[0];
$object->id = $id;
$object->get();
$method = $request_array[1];

$fileDownloader->file_var = $object->$method();
$fileDownloader->Return();


?>