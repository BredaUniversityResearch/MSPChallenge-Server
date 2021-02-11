<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

//$user->hastobeLoggedIn();


if(isset($_POST['zippath']))
{
	if (is_file($_POST['zippath'])) 
	{

		$outputDirectory = GetSessionArchiveBaseDirectory();

		if (!is_dir($outputDirectory))
		{
			mkdir($outputDirectory, 0777);
		}

		$storeFilePath = $outputDirectory.basename($_POST['zippath']);

		rename($_POST['zippath'], $storeFilePath);
	
	}
	
}
?>
