<?php
require_once '../init.php'; 

if(isset($_POST['zippath']))
{
	if (is_file($_POST['zippath'])) 
	{

		$outputDirectory = ServerManager::getInstance()->GetSessionArchiveBaseDirectory();

		if (!is_dir($outputDirectory))
		{
			mkdir($outputDirectory, 0777);
		}

		$storeFilePath = $outputDirectory.basename($_POST['zippath']);

		rename($_POST['zippath'], $storeFilePath);
	
	}
	
}
?>
