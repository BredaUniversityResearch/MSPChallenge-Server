<?php 
	$fdirectory = realpath("Temp/");
	$fname = $fdirectory."/".getmypid()."_".microtime(true);
	$has_errored = false;
	if (!is_dir($fdirectory))
	{
		mkdir($fdirectory);
	}
	file_put_contents($fname, json_encode($_SERVER,JSON_PRETTY_PRINT).json_encode($_GET, JSON_PRETTY_PRINT).json_encode($_POST, JSON_PRETTY_PRINT));

	function catch_error_shutdown()
	{
		$error = error_get_last();

		global $fname;
		global $has_errored;
		if ($error != null) 
		{
			$has_errored = true;
			file_put_contents($fname."_failed", PHP_EOL.json_encode($error, JSON_PRETTY_PRINT));
		}
	}
	register_shutdown_function('catch_error_shutdown');

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

	if(!empty($_GET['query'])){
		$data = Router::RouteApiCall($_GET['query'], $_POST);
		echo json_encode($data);
	}
	else{
		http_response_code(404);
	}

	ob_end_flush();

	if (!$has_errored)
	{
		unlink($fname);
	}
?>
