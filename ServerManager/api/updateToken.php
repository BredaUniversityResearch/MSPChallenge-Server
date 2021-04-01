<?php 
require_once '../init.php';

$user->hastobeLoggedIn();

$servermanager = ServerManager::getInstance();

$return['status'] = 'error';
$return['jwt'] = '';
if (!empty($_POST['Token'])) {
    // check the old token, get the new token in one go
    $url = $servermanager->GetMSPAuthAPI().'checkjwt.php';
    $checkoldgetnew = json_decode(CallAPI("POST", $url, array (
                                                            "jwt" => $_POST['Token'],
                                                            "audience" => $servermanager->GetBareHost()
                                                        )));
    // if old accepted and new returned
    if ($checkoldgetnew->success && !empty($checkoldgetnew->jwt)) {
        Session::put("currentToken", $checkoldgetnew->jwt);
        $return['status'] = 'success';
        $return['jwt'] = $checkoldgetnew->jwt;
    }
}

echo json_encode($return);
?>