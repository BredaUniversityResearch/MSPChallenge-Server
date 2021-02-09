<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

//$user->hastobeLoggedIn();

$outputDirectory = GetSessionArchiveBaseDirectory();

if(isset($_POST['oldlocation']))
{

	if (is_file($_POST['oldlocation'])) {

		$sessionId = intval($_POST['session_id']);

		if (!is_dir($outputDirectory))
		{
			mkdir($outputDirectory, 0777);
		}

		$storeFilePath = $outputDirectory.basename($_POST['oldlocation']);

		rename($_POST['oldlocation'], $storeFilePath);
	
	}
	
}
?>
