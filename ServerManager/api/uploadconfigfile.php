<?php
require_once '../init.php'; 
/*// all the configurable variables
require_once '../config.php';

// all the classes
require_once '../classes/class.autoloader.php';

// all the helper functions
require_once '../helpers.php';*/

$user->hastobeLoggedIn();

$uid = $user->data()->id;

$response_array = array('status' => 'error',
	'message' => 'Missing parameter values'
);

if((isset($_POST['config_file_id']) || !empty($_POST["new_config_file_name"])) &&
	isset($_POST['description']) &&
	isset($_POST["change_message"]) &&
	!empty($uid) &&
	isset($_FILES['config_file']))
{
	$db = DB::getInstance();
	$db->query("START TRANSACTION");

	$configFileName = null;
	$newConfigVersionId = 1;
	$configFileId = -1;
	$description = strip_tags($_POST['description']);
	if (is_numeric($_POST['config_file_id']))
	{
		$configFileId = intval($_POST['config_file_id']);
		$db->query("SELECT game_config_version.version, game_config_files.filename, game_config_version.region
			FROM game_config_version
				INNER JOIN game_config_files ON game_config_version.game_config_files_id = game_config_files.id
			WHERE game_config_version.game_config_files_id = ?
			ORDER BY version DESC
			LIMIT 0,1",
			array($configFileId));
		$results = $db->results(true);
		if (count($results) > 0)
		{
			$newConfigVersionId = intval($results[0]["version"]) + 1;
			$configFileName = $results[0]["filename"];
			$region = $results[0]["region"];
			$db->query("UPDATE game_config_files SET description = ? WHERE id = ?", array($description, $configFileId));
		}
		else
		{
			$response_array["message"] = "Unknown config file id";
			echo json_encode($response_array);
			$db->query("ROLLBACK");
			return;
		}
	}
	else
	{
		$configFileName = strip_tags($_POST['new_config_file_name']);
		$configFileName = str_replace(" ", "_", $configFileName);
		$configFileName = preg_replace( '/[^a-zA-Z0-9\_]+/', '-', $configFileName);

		$db->query("SELECT id FROM game_config_files WHERE filename LIKE ?", array($configFileName));
		if (count($db->results()) > 0)
		{
			$response_array['message'] = "Config file with that name already exists";
			echo json_encode($response_array);
			$db->query("ROLLBACK");
			return;
		}

		$newConfigVersionId = 1;

		$db->query("INSERT INTO game_config_files (filename, description) VALUES(?, ?)", array($configFileName, $description));
		$configFileId = $db->lastId();
	}

	$relativePath = $configFileName."/".$configFileName."_".$newConfigVersionId.".json";
	$storeFilePath = GetConfigBaseDirectory().$relativePath;

	$outputDirectory = pathinfo($storeFilePath, PATHINFO_DIRNAME);
	if (!is_dir($outputDirectory))
	{
		mkdir($outputDirectory, 0777); 
	}

	if (is_file($storeFilePath))
	{
		$response_array['message'] = "Config file with the specified version already exists at the store path";
		echo json_encode($response_array);
		$db->query("ROLLBACK");
		return;
	}

	move_uploaded_file($_FILES['config_file']['tmp_name'], $storeFilePath);

	if (!isset($region)) $region = "Unknown";
	$clientVersions = "Any";
	$configFileValues = json_decode(file_get_contents($storeFilePath), true);
	if ($configFileValues != null)
	{
		if (isset($configFileValues["datamodel"]["region"]) && $region == "Unknown") //if (isset($configFileValues["region"]) && $region == "Unknown")
		{
			$region = $configFileValues["datamodel"]["region"]; //$region = $configFileValues["region"];
		}

		if (array_key_exists("application_versions", $configFileValues))
		{
			$versionData = $configFileValues["application_versions"];
			$minClientVersion = (isset($versionData["client_version_min"]))? $versionData["client_version_min"] : -1;
			$maxClientVersion = (isset($versionData["client_version_max"]))? $versionData["client_version_max"] : -1;

			if ($minClientVersion > 0 && $maxClientVersion > 0)
			{
				$clientVersions = $minClientVersion." - ".$maxClientVersion;
			}
			else if ($minClientVersion > 0)
			{
				$clientVersions = $minClientVersion."+";
			}
		}
	}
	$sql = "INSERT INTO game_config_version(game_config_files_id, version, version_message, upload_time, upload_user, last_played_time, file_path, region, client_versions) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$params = array($configFileId, $newConfigVersionId, $_POST['change_message'], microtime(true), $uid, 0, $relativePath, $region, $clientVersions);
	//$response_array['status'] = "error";
	//$response_array['message'] = $params[0].' '.$params[1].' '.$params[2].' '.$params[3].' '.$params[4].' '.$params[5].' '.$params[6].' '.$params[7].' '.$params[8];
	$db->query($sql,
		$params);

	$response_array['status'] = "Success";
	$response_array['message'] = "The new configuration file was saved successfully and ready to be used.";

	$db->query("COMMIT");
}
echo json_encode($response_array);
?>
