<?php
// Userspice inspired code to determine where the ServerManager is installed
$abs_app_root = $_SERVER['DOCUMENT_ROOT'];
$url_app_root = '';
$self_path = explode("/", $_SERVER['PHP_SELF']);
$self_path_length = count($self_path);
for($i = 1; $i < $self_path_length; $i++){
	array_splice($self_path, $self_path_length-$i, $i);
	$url_app_root = implode("/",$self_path)."/";
	if (file_exists($abs_app_root.$url_app_root.'z_root.php')) break;
}
// the two variables $url_app_root and $abs_app_root have now been set properly and will be used throughout the system

$codeBranch = '/dev';//str_ireplace("/ServerManager/", "", $url_app_root);

// Endpoints
$stable_msp_auth_url_prefix = 'https://auth.mspchallenge.info';
$msp_auth_endpoint = '/usersc/plugins/apibuilder/authmsp/';
$current_msp_auth_endpoint = $stable_msp_auth_url_prefix . $msp_auth_endpoint;

$GLOBALS['config'] = array(
						'code_branch'	=> $codeBranch,
						'mysql'      	=> array(
							'host'         => 'localhost',
							'username'     => 'root',
							'password' => '',
							'db'           => 'msp_server_manager',
							'multisession_database_prefix' => 'msp_session_'),
						'remember'        => array(
						  'cookie_name'   	=> 'pmqesoxiw318374csb',
						  'cookie_expiry' 	=> 604800),
						'session' => array(
						  'session_name' 	=> 'user',
						  'token_name' 		=> 'token'),
						'msp_auth'				=> array(
							'root'					=> $stable_msp_auth_url_prefix,
							'api_endpoint'			 => $current_msp_auth_endpoint,
							'with_proxy' 			=> ""),
						'msp_server_protocol'	=> 'http://',	// change to https to go secure with all background connections to your MSP Challenge servers
						'msp_servermanager_protocol' => 'http://' // change to https to go secure with all background connections to your MSP Challenge Server Manager
					);
