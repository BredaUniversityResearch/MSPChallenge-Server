<?php
require_once '../init.php'; 

$api = new API;
$gamesave = new GameSave;
$user = new User();

$user->hastobeLoggedIn();

$visibility = $_POST['visibility'] ?? 'active';
$visibility_where_array = array("save_visibility", "=", $visibility);
$type = $_POST['save_type'] ?? 'full';

if (isset($_POST['save_type'])) 
{
    $type_where_array = array("save_type", "=", $_POST['save_type']);
    $where_array = array("AND", $visibility_where_array, $type_where_array);
}
else $where_array = $visibility_where_array;


$api->setPayload(["saveslist" => $gamesave->getList($where_array)]);
$api->setStatusSuccess();
$api->Return();

?>
