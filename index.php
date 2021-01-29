<?php 
	// $fdirectory = getcwd()."\\Temp\\";
	// $fname = $fdirectory."/".getmypid()."_".microtime(true);
	// $has_errored = false;
	// if (!is_dir($fdirectory))
	// {
	// 	mkdir($fdirectory);
	// }
	// file_put_contents($fname, json_encode($_SERVER,JSON_PRETTY_PRINT).json_encode($_GET, JSON_PRETTY_PRINT).json_encode($_POST, JSON_PRETTY_PRINT));

	// function catch_error_shutdown()
	// {
	// 	$error = error_get_last();

	// 	global $fname;
	// 	global $has_errored;
	// 	if ($error != null) 
	// 	{
	// 		$has_errored = true;
	// 		file_put_contents($fname."_failed", PHP_EOL.json_encode($error, JSON_PRETTY_PRINT));
	// 	}
	// }
	// register_shutdown_function('catch_error_shutdown');

	header('Content-Type: application/json');
	
	if (version_compare(PHP_VERSION, '7.1.0') < 0)
	{
		throw new Exception("Required at least php version 7.1.0 for ReflectionNamedType");
	}

	require_once("api/class.apihelper.php");
	require_once("helpers.php");

	//unlimited memory usage on the server
	ini_set('memory_limit', '-1');

	APIHelper::SetupApiLoader();

	ob_start();

	$outputData = ["success" => false, "message"=> ""];
	if(!empty($_GET['query'])){
		$outputData = Router::RouteApiCall($_GET['query'], $_POST);
	}
	else{
		http_response_code(404);
	}

	//$outputData["request_url"] = $_SERVER["REQUEST_URI"];
	$pageOutput = ob_get_flush();
	if(!empty($pageOutput))
	{
		$outputData["message"].= PHP_EOL."Additional Debug Information: ".$pageOutput;
		$outputData["success"] = false;
	}

	echo json_encode($outputData);

	// if (!$has_errored)
	// {
	// 	unlink($fname);
	// }
?>
