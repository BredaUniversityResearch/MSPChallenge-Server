<?php
require_once '../init.php'; 

$db = DB::getInstance();
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileWriter;
use Shapefile\Geometry\Point;
use Shapefile\Geometry\MultiLinestring;
use Shapefile\Geometry\MultiPolygon;

//header('Content-type: application/json');
$response_array['status'] = 'error';
$response_array['message'] = 'No savefile path given.';

if(!empty($_POST['zipname']) && !empty($_POST['session_id']) && !empty($_POST["type"])) {
	$session_id = (int) $_POST['session_id'];
	$zipname = str_replace("/ServerManager/", "", $_POST['zipname']);
	$type = (string) $_POST["type"];
	$save_notes = "";
	
	// get the game session details from ServerManager database
	$db->query("SELECT gl.*, gcf.filename AS game_config_files_filename, gcv.region AS game_config_versions_region,
						gs.game_config_files_filename AS gamesave_config_files_filename, 
						gs.game_config_versions_region AS gamesave_config_versions_region 
				FROM game_list gl 
				LEFT JOIN game_config_version gcv ON gcv.id = gl.game_config_version_id 
				LEFT JOIN game_config_files gcf ON gcv.game_config_files_id = gcf.id 
				LEFT JOIN game_saves gs ON gs.id = gl.save_id
				WHERE gl.id = ?", array($session_id));
	$sessionslist = $db->results(true);
	if (!empty($sessionslist)) {
		// if the server being saved was already a reloaded save to begin with, then get the proper config filename and region from the saves table
		if ($sessionslist[0]["save_id"] > 0) {
			$sessionslist[0]["game_config_files_filename"] = $sessionslist[0]["gamesave_config_files_filename"];
			$sessionslist[0]["game_config_versions_region"] = $sessionslist[0]["gamesave_config_versions_region"];
		}
		unset($sessionslist[0]["gamesave_config_files_filename"]);
		unset($sessionslist[0]["gamesave_config_versions_region"]);

		if ($type == "full") {
			// in this case we want to add one more file to the zip: game_list.json (which is the game_list record of the original session)
			// first save into a .json file and add to the ZIP file that was already created
			$zip = new ZipArchive();
			$result = $zip->open(ServerManager::getInstance()->GetServerManagerRoot().$zipname);
			if ($result === TRUE) {
				$zip->addFromString('game_list.json', json_encode($sessionslist[0]));
				$zip->close();
			}
		}
		elseif ($type == "layers") {
			// in this case we want to read the temp zip, create Shapefiles from each json file in it, and create the definitive zip
			$def_zipname = str_replace("temp_", "", $zipname);
			$zip = new ZipArchive();
			$def_zip = new ZipArchive();
			$result = $zip->open(ServerManager::getInstance()->GetServerManagerRoot().$zipname);
			$def_result = $def_zip->open(ServerManager::getInstance()->GetServerManagerRoot().$def_zipname, ZipArchive::CREATE | ZipArchive::OVERWRITE);
			do {
				$random = rand(0, 1000);
				$templocation = ServerManager::getInstance()->GetServerManagerRoot()."saves/temp_".$random."/";
			} while (is_dir($templocation));
			mkdir($templocation);
			// loop through the existing zip's contents to create the new zip
			if ($result === TRUE && $def_result === TRUE) {
				for($i = 0; $i < $zip->numFiles; $i++) {
					$filename = $zip->getNameIndex($i);
					$filecontents = $zip->getFromIndex($i);
					// if it's a readable json file
					if (strstr($filename, ".json") !== false && isJson($filecontents)) {
						// let's determine what geometry we're dealing with 
						//echo $filename.PHP_EOL;
						$rawcontents = file_get_contents("zip://".ServerManager::getInstance()->GetServerManagerRoot().$zipname."#".$filename);
						if (strpos($rawcontents, "MULTIPOLYGON") !== false) {
							$shapetypetoset = Shapefile::SHAPE_TYPE_POLYGON;
							$classtouse = "Shapefile\Geometry\MultiPolygon";
							$continue = true;
						}
						elseif (strpos($rawcontents, "MULTILINESTRING") !== false) {
							$shapetypetoset = Shapefile::SHAPE_TYPE_POLYLINE;
							$classtouse = "Shapefile\Geometry\MultiLinestring";
							$continue = true;
						}
						elseif (strpos($rawcontents, "POINT") !== false) {
							$shapetypetoset = Shapefile::SHAPE_TYPE_POINT;
							$classtouse = "Shapefile\Geometry\Point";
							$continue = true;
						}
						else {
							$save_notes .= "Problem identifying type of geometry for ".$filename.", so skipped it. This usually happens when there is no geometry at all.".PHP_EOL;
							$continue = false;
						}

						if ($continue) {
							//if ($filename == "NS_No_shipping_zones.json") die(var_dump($rawcontents));
							// attempt to create the Shapefile set in the new random temp folder
							$shploc = $templocation.str_replace(".json", ".shp", $filename);
							$newShapefile = new ShapefileWriter($shploc, [Shapefile::OPTION_EXISTING_FILES_MODE => Shapefile::MODE_OVERWRITE, Shapefile::OPTION_DBF_FORCE_ALL_CAPS => false]);
							$newShapefile->setShapeType($shapetypetoset);
							// now add mspid and type fields -- all other fields under 'data' will be defined on the spot
							$newShapefile->addField("mspid", Shapefile::DBF_TYPE_CHAR, 80, 0);
							$newShapefile->addField("type", Shapefile::DBF_TYPE_CHAR, 80, 0);
							$additionalfieldsadded = array();
							// now we can fill the fill with its actual geometry
							$jsoncontents = json_decode($rawcontents, true);
							//die(var_dump($jsoncontents));
							$alreadysavederrortype = array();
							foreach ($jsoncontents as $count => $geometry_entry) {
								//if ($filename == "NS_No_shipping_zones.json") var_dump($geometry_entry).PHP_EOL;
								try {
									$dataarray = array();
									$dataarray["mspid"] = $geometry_entry["mspid"] ?? "";
									$dataarray["type"] = $geometry_entry["type"] ?? "";
									$additionaldata = $geometry_entry["data"] ?? array();
									foreach ($additionaldata as $fieldname => $fieldvalue) {
										// Skipping a couple of fields here
										// 1. skipping duplicate TYPE definition here - it's completely unnecessary and creates problems
										// 2. skipping anything with name longer than 10 char (notably Shipping_Intensity) here - otherwise we get problems
										if ($fieldname != "type" && $fieldname != "TYPE" && strlen($fieldname) <= 10) { 
											if (!in_array($fieldname, $additionalfieldsadded) && $count == 0) { // $count = 0 means this only happens in first geometry record
												$newShapefile->addField($fieldname, Shapefile::DBF_TYPE_CHAR, 254, 0);
												$additionalfieldsadded[] = $fieldname;
											}	
											// make sure we only add dataarray elements that have already been defined as fields
											if (in_array($fieldname, $additionalfieldsadded)) $dataarray[$fieldname] = $fieldvalue;
										}
									}
									// check if the dataarray isn't missing data that has already been defined in additionalfieldsadded
									foreach ($additionalfieldsadded as $fieldname2check) {
										if (!isset($dataarray[$fieldname2check])) $dataarray[$fieldname2check] = '';
									}
									if ($classtouse == "Shapefile\Geometry\MultiPolygon") $geometry = new $classtouse(array(), Shapefile::ACTION_FORCE);
									else $geometry = new $classtouse();
									$geometry->initFromWKT($geometry_entry["the_geom"]);
									$geometry->setDataArray($dataarray);
									//if ($filename == "NS_No_shipping_zones.json") var_dump($geometry_entry["the_geom"]).PHP_EOL.var_dump($dataarray).PHP_EOL;
									$newShapefile->writeRecord($geometry);
								}
								catch (ShapefileException $e) {
									// if the Shapefile creation failed, add the failure at the end of a log var for storage as save_notes later, and just move on to the next
									if (!in_array($e->getErrorType(), $alreadysavederrortype)) {
										$save_notes .= "Problem adding geometry from ".$filename.". Error Type: " . $e->getErrorType() . ". Message: " . $e->getMessage() . ". ";
										if (!empty($e->getDetails())) $save_notes .= "Details: " . $e->getDetails().". ";
										$save_notes .= "Further errors of this type for this entire layer will not be logged.".PHP_EOL.PHP_EOL;
										$alreadysavederrortype[] = $e->getErrorType();
										//echo $e->getErrorType() . ". Message: " . $e->getMessage().PHP_EOL;
									}
									continue;
								}
							}
							$newShapefile = null;
						}
					}
					// else just assume it's a raster file, don't do anything with it, just add to the definitive zip
					else {
						$def_zip->addFromString($filename, $filecontents); // addFromString is binary-safe
					}
				}
				// now add the entire set of shp filesets from the temp dir to the zip
				foreach (array_diff(scandir($templocation), array('..', '.')) as $file2add) {
					$def_zip->addFile($templocation.$file2add, $file2add);
				}
				$zip->close();
				$def_zip->close();
				rrmdir($templocation);
				unlink(ServerManager::getInstance()->GetServerManagerRoot().$zipname);
				$zipname = $def_zipname;
			}
		}
		else {
			// no other types allowed
			die();
		}
		// add to the ServerManager database
	
  
		if ($db->query("INSERT INTO game_saves (name, game_config_version_id, game_config_files_filename, game_config_versions_region, game_server_id, watchdog_server_id, game_creation_time, 
												game_start_year, game_end_month, game_current_month, game_running_til_time, password_admin,
												password_player, session_state, game_state, game_visibility, players_active, players_past_hour,
												demo_session, api_access_token, save_type, save_path, save_notes, server_version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);", 
			array(ensure_unique_name($sessionslist[0]['name'], "name", "game_saves"), $sessionslist[0]['game_config_version_id'], $sessionslist[0]['game_config_files_filename'], $sessionslist[0]['game_config_versions_region'], $sessionslist[0]['game_server_id'], $sessionslist[0]['watchdog_server_id'], $sessionslist[0]['game_creation_time'], 
			$sessionslist[0]['game_start_year'], $sessionslist[0]['game_end_month'], $sessionslist[0]['game_current_month'], $sessionslist[0]['game_running_til_time'], $sessionslist[0]['password_admin'],
			$sessionslist[0]['password_player'], $sessionslist[0]['session_state'], $sessionslist[0]['game_state'], $sessionslist[0]['game_visibility'], $sessionslist[0]['players_active'], $sessionslist[0]['players_past_hour'],
			$sessionslist[0]['demo_session'], $sessionslist[0]['api_access_token'], $type, $zipname, $save_notes, $sessionslist[0]['server_version']))) {
				
				// now, finally, make the filename unique and update game_saves table accordingly
				$save_id = $db->lastid();
				$newzipname = "saves/save_".$save_id.".zip";
				rename(ServerManager::getInstance()->GetServerManagerRoot().$zipname, ServerManager::getInstance()->GetServerManagerRoot().$newzipname);
				$db->query("UPDATE game_saves SET save_path = ? WHERE id = ?", array($newzipname, $save_id));
				
				echo json_encode(array("status" => "success"));
		}
	}
	
}
?>
