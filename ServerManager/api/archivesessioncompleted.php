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

if(isset($_POST['session_id']) && isset($_FILES['archive']))
{
	$sessionId = intval($_POST['session_id']);

	if (!is_dir($outputDirectory))
	{
		mkdir($outputDirectory, 0777);
	}

	$storeFilePath = $outputDirectory.basename($_FILES['archive']['name']);

	if (move_uploaded_file($_FILES['archive']['tmp_name'], $storeFilePath)) {
		//move_uploaded_file only returns true if the file was valid and move was successful
		//so in this case it's safe to delete the original on the server itself
		$oldlocation = isset($_POST['oldlocation']) ? $_POST['oldlocation'] : '';
		if (file_exists($oldlocation)) {
			if (filesize($oldlocation) > 0) {
				unlink($oldlocation);
			}
		}
	}
}
?>
