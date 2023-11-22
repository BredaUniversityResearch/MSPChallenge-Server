<?php

namespace ServerManager;

use ReflectionClass;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileWriter;
use ZipArchive;
use function App\rrmdir;

class GameSave extends Base
{
    private ?DB $db = null;
    private $old;

    public $name;
    public $game_config_files_id;
    public $game_config_version_id;
    public $game_config_files_filename;
    public $game_config_versions_region;
    public $game_server_id;
    public $watchdog_server_id;
    public $game_creation_time;
    public $game_start_year;
    public $game_end_month;
    public $game_current_month;
    public $game_running_til_time;
    public $password_admin;
    public $password_player;
    public $session_state;
    public $game_state;
    public $game_visibility;
    public $players_active;
    public $players_past_hour;
    public $demo_session;
    public $api_access_token;
    public $save_type;
    public $save_notes;
    public $save_visibility;
    public $save_timestamp;
    public $server_version;
    public $id;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    public function getStore(): string
    {
        return ServerManager::getInstance()->getSessionSavesBaseDirectory();
    }

    public function getPrefix(): string
    {
        return ServerManager::getInstance()->getSessionSavesPrefix();
    }

    private function validateVars(): bool|string
    {
        foreach ((new ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $var) {
            $varname = $var->getName();
            switch ($varname) {
                case 'save_timestamp':
                    break; // ignoring, automatically determined
                default:
                    if (0 == strlen($this->$varname)) {
                        return 'Missing value for '.$varname;
                    }
            }
        }

        return true;
    }

    public function get()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot obtain GameSave without a valid id.');
        }
        $this->db->query('SELECT * FROM game_saves WHERE id = ?', [$this->id]);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        if (0 == $this->db->count()) {
            throw new ServerManagerAPIException('Save '.$this->id.' not found.');
        }
        foreach ($this->db->first(true) as $varname => $varvalue) {
            if (property_exists($this, $varname)) {
                $this->$varname = $varvalue;
            }
        }
        $this->game_config_files_id = $this->game_config_version_id;

        $this->old = clone $this;
    }

    public function getContentsConfig(): array
    {
        $original_session_id = $this->getContentsGameList()['id'];
        $file = $this->getFullZipPath();
        $zip = new ZipArchive();
        if (true !== $zip->open($file)) {
            throw new ServerManagerAPIException("Couldn't open the uploaded file. Are you sure it's a ZIP file?");
        }
        $config_content = $zip->getFromName('session_config_'.$original_session_id.'.json');
        if (false === $config_content) {
            throw new ServerManagerAPIException("Couldn't find the session config file in the save ZIP.");
        }
        $zip->close();
        $content = json_decode($config_content, true);
        if (is_null($content) || !is_array($content)) {
            throw new ServerManagerAPIException(json_last_error_msg());
        }

        return $content;
    }

    private function getContentsGameList($file = null): array
    {
        if (is_null($file)) {
            $file = $this->getFullZipPath();
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($file)) {
            throw new ServerManagerAPIException("Couldn't open the uploaded file. Are you sure it's a ZIP file?");
        }
        $game_list_json = $zip->getFromName('game_list.json');
        if (false === $game_list_json) {
            throw new ServerManagerAPIException("Couldn't find the game_list.json file in the save ZIP.");
        }
        $zip->close();
        $content = json_decode($game_list_json, true);
        if (is_null($content) || !is_array($content)) {
            throw new ServerManagerAPIException(json_last_error_msg());
        }

        return $content;
    }

    public function getList($where_array = []): array
    {
        if (!$this->db->action(
            "SELECT gs.id, gs.save_timestamp, gs.name, gs.game_config_files_filename, gs.save_type,
                                DATE_FORMAT(DATE_ADD(str_to_date(CONCAT(cast(gs.game_start_year as char),'-01-01'),
                                '%Y-%m-%d') , INTERVAL + gs.game_current_month MONTH),'%M %Y' ) as game_current_month",
            'game_saves gs',
            $where_array
        )) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $return_array = $this->db->results(true);
        foreach ($return_array as $row => $gamesave) {
            $this->id = $gamesave['id'];
            $this->get();
            $return_array[$row]['save_path'] = $this->getFullZipPath();
        }

        return $return_array;
    }

    /**
     * @throws \Exception
     */
    public function getPrettyVars(): array
    {
        $date_current_month = new \DateTime($this->game_start_year.'-01-01');
        if ($this->game_current_month >= 0) {
            $date_current_month->add(new \DateInterval('P' . $this->game_current_month . 'M'));
        }
        $return['game_current_month'] = $date_current_month->format('M Y');

        return $return;
    }

    public function createZip(GameSession $gameSession): bool
    {
        $urlBase = ServerManager::getInstance()->getAbsoluteUrlBase();
        if (getenv('DOCKER') !== false) {
            $urlBase = 'http://php:80'.ServerManager::getInstance()->getAbsolutePathBase();
        }
        $server_call = self::callServer(
            'GameSession/SaveSession',
            [
                'save_id' => $this->id,
                'type' => $this->save_type,
                'preferredname' => ('layers' == $this->save_type) ? 'temp_'.$this->getPrefix() : $this->getPrefix(),
                'preferredfolder' => $this->getStore(),
                'nooverwrite' => true,
                'response_url' => $urlBase.'api/editGameSave.php',
            ],
            $gameSession->id,
            $gameSession->api_access_token
        );
        if (!$server_call['success']) {
            throw new ServerManagerAPIException($server_call['message']);
        }

        return true;
    }

    public function processZip()
    {
        if ('full' == $this->save_type) {
            $this->processZipFull();
        } elseif ('layers' == $this->save_type) {
            $this->processZipLayers();
        }
    }

    private function processZipFull()
    {
        // need to add one more file to the zip: game_list.json (which is the game_list record of the original session)
        $zippath = $_POST['zippath'] ?? '';
        if (!file_exists($zippath)) {
            throw new ServerManagerAPIException('Could not find the file with path: '.$zippath);
        }
        $gamesession = new GameSession();
        $gamesession->id = $_POST['session_id'] ?? 0;
        $gamesession->get();

        $game_list_contents = get_object_vars($gamesession);
        unset($game_list_contents['log']);
        unset($game_list_contents['save_id']);
        unset($game_list_contents['_jwt']);
        $game_list_contents['password_admin'] = base64_encode($game_list_contents['password_admin']);
        $game_list_contents['password_player'] = base64_encode($game_list_contents['password_player']);

        if ($gamesession->save_id > 0) {
            $gamesave = new GameSave();
            $gamesave->id = $gamesession->save_id;
            $gamesave->get();
            $game_list_contents['game_config_files_filename'] = $gamesave->game_config_files_filename;
            $game_list_contents['game_config_versions_region'] = $gamesave->game_config_versions_region;
        } else {
            $gameconfig = new GameConfig();
            $gameconfig->id = $gamesession->game_config_version_id;
            $gameconfig->get();
            $game_list_contents['game_config_files_filename'] = $gameconfig->filename;
            $game_list_contents['game_config_versions_region'] = $gameconfig->region;
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($zippath)) {
            throw new ServerManagerAPIException("Could not open the file. Are you sure it's a ZIP?");
        }
        if (!$zip->addFromString('game_list.json', json_encode($game_list_contents))) {
            throw new ServerManagerAPIException('Failed to add game_list.json file to the ZIP.');
        }
        $zip->close();
    }

    private function processZipLayers()
    {
        // read the temp zip, create Shapefiles from each json file in it, and create the definitive zip
        $zip = new ZipArchive();
        $def_zip = new ZipArchive();
        $zippath = $_POST['zippath'] ?? '';
        $def_zippath = str_replace('temp_', '', $zippath);

        if (true !== $zip->open($zippath)) {
            throw new ServerManagerAPIException('Could not open the temporary ZIP file, so cannot continue.');
        }
        if (true !== $def_zip->open($def_zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new ServerManagerAPIException('Could not create definitive ZIP file, so cannot continue.');
        }
        do {
            $random = rand(0, 1000);
            $templocation = $this->getStore().'temp_'.$random.'/';
        } while (is_dir($templocation));
        if (!mkdir($templocation)) {
            throw new ServerManagerAPIException(
                'Could not create temporary folder to put files in, so cannot continue.'
            );
        }

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $filename = $zip->getNameIndex($i);
            $filecontents = $zip->getFromIndex($i);
            if (false !== strstr($filename, '.json') && isJsonArray($filecontents)) {
                // don't add it to the ZIP straight-away, we're not sure it was created at this point
                $this->createShapefile($filecontents, $filename, $templocation);
            } else {
                // just assume it's a raster file, don't do anything with it, just add to the definitive zip -
                //   addFromString is binary-safe
                $def_zip->addFromString($filename, $filecontents);
            }
        }

        // now add the entire set of shp filesets from the temp dir to the zip
        foreach (array_diff(scandir($templocation), ['..', '.']) as $file2add) {
            $def_zip->addFile($templocation.$file2add, $file2add);
        }

        $zip->close();
        $def_zip->close();
        rrmdir($templocation);
        unlink($zippath);
    }

    private function createShapefile($filecontents, $filename, $templocation): bool
    {
        if (false !== strpos($filecontents, 'MULTIPOLYGON')) {
            $shapetypetoset = Shapefile::SHAPE_TYPE_POLYGON;
            $classtouse = "Shapefile\Geometry\MultiPolygon";
            $continue = true;
        } elseif (false !== strpos($filecontents, 'MULTILINESTRING')) {
            $shapetypetoset = Shapefile::SHAPE_TYPE_POLYLINE;
            $classtouse = "Shapefile\Geometry\MultiLinestring";
            $continue = true;
        } elseif (false !== strpos($filecontents, 'POINT')) {
            $shapetypetoset = Shapefile::SHAPE_TYPE_POINT;
            $classtouse = "Shapefile\Geometry\Point";
            $continue = true;
        } else {
            $this->save_notes .= 'Problem identifying type of geometry for '.$filename.
                ', so skipped it. This usually happens when there is no geometry at all.'.PHP_EOL;
            $continue = false;
        }

        if ($continue) {
            // attempt to create the Shapefile set within the temp folder
            $shploc = $templocation.str_replace('.json', '.shp', $filename);
            $newShapefile = new ShapefileWriter($shploc, [
                Shapefile::OPTION_EXISTING_FILES_MODE => Shapefile::MODE_OVERWRITE,
                Shapefile::OPTION_DBF_FORCE_ALL_CAPS => false]);
            $newShapefile->setShapeType($shapetypetoset);
            // now add mspid and type fields -- all other fields under 'data' will be defined on the spot
            $newShapefile->addField('mspid', Shapefile::DBF_TYPE_CHAR, 80, 0);
            $newShapefile->addField('type', Shapefile::DBF_TYPE_CHAR, 80, 0);
            // now we can fill the fill with its actual geometry
            $additionalfieldsadded = [];
            $alreadysavederrortype = [];
            foreach (json_decode($filecontents, true) as $count => $geometry_entry) {
                try {
                    $dataarray = [];
                    $dataarray['mspid'] = $geometry_entry['mspid'] ?? '';
                    $dataarray['type'] = $geometry_entry['type'] ?? '';
                    $additionaldata = $geometry_entry['data'] ?? [];
                    foreach ($additionaldata as $fieldname => $fieldvalue) {
                        // Skipping a couple of fields here
                        // 1. skipping duplicate TYPE definition here - it's completely unnecessary and creates problems
                        // 2. skipping anything with name longer than 10 char (notably Shipping_Intensity) here -
                        //  otherwise we get problems
                        if ('type' != $fieldname && 'TYPE' != $fieldname && strlen($fieldname) <= 10) {
                            // $count = 0 means this only happens in first geometry record
                            if (!in_array($fieldname, $additionalfieldsadded) && 0 == $count) {
                                $newShapefile->addField($fieldname, Shapefile::DBF_TYPE_CHAR, 254, 0);
                                $additionalfieldsadded[] = $fieldname;
                            }
                            // make sure we only add dataarray elements that have already been defined as fields
                            if (in_array($fieldname, $additionalfieldsadded)) {
                                $dataarray[$fieldname] = $fieldvalue;
                            }
                        }
                    }
                    // check if the dataarray isn't missing data that has already been defined in additionalfieldsadded
                    foreach ($additionalfieldsadded as $fieldname2check) {
                        if (!isset($dataarray[$fieldname2check])) {
                            $dataarray[$fieldname2check] = '';
                        }
                    }
                    if ("Shapefile\Geometry\MultiPolygon" == $classtouse) {
                        $geometry = new $classtouse([], Shapefile::ACTION_FORCE);
                    } else {
                        $geometry = new $classtouse();
                    }
                    $geometry->initFromWKT($geometry_entry['the_geom']);
                    $geometry->setDataArray($dataarray);
                    $newShapefile->writeRecord($geometry);
                } catch (ShapefileException $e) {
                    if (!in_array($e->getErrorType(), $alreadysavederrortype)) {
                        $this->save_notes .= 'Problem adding geometry from '.$filename.
                            '. Error Type: '.$e->getErrorType().'. Message: '.$e->getMessage().'. ';
                        if (!empty($e->getDetails())) {
                            $this->save_notes .= 'Details: '.$e->getDetails().'. ';
                        }
                        $this->save_notes .= 'Further errors of this type for this entire layer will not be logged.'.
                            PHP_EOL.PHP_EOL;
                        $alreadysavederrortype[] = $e->getErrorType();
                    }
                    continue;
                }
            }
            $newShapefile = null;

            return true;
        }

        return false;
    }

    public function addFromUpload($file)
    {
        if (empty($file)) {
            throw new ServerManagerAPIException("Didn't get an uploaded file, so can't continue.");
        }
        $savevars = $this->getContentsGameList($file);
        $this->server_version = $savevars['server_version'] ?? '4.0-beta7'; // since this var was first added with beta8
        $this->name = DB::getInstance()->ensure_unique_name($savevars['name'], 'name', 'game_saves');
        $this->game_config_files_id = $savevars['game_config_version_id'];
        $this->game_config_files_filename = $savevars['game_config_files_filename'];
        $this->game_config_versions_region = $savevars['game_config_versions_region'];
        $this->game_server_id = $savevars['game_server_id'];
        $this->watchdog_server_id = $savevars['watchdog_server_id'];
        $this->game_creation_time = $savevars['game_creation_time'];
        $this->game_start_year = $savevars['game_start_year'];
        $this->game_end_month = $savevars['game_end_month'];
        $this->game_current_month = $savevars['game_current_month'];
        $this->game_running_til_time = $savevars['game_running_til_time'];
        $this->password_admin = $savevars['password_admin'];
        $this->password_player = $savevars['password_player'];
        $this->session_state = $savevars['session_state'];
        $this->game_state = $savevars['game_state'];
        $this->game_visibility = $savevars['game_visibility'];
        $this->players_active = $savevars['players_active'];
        $this->players_past_hour = $savevars['players_past_hour'];
        $this->demo_session = $savevars['demo_session'];
        $this->api_access_token = $savevars['api_access_token'];
        $this->save_type = 'full';
        $this->save_visibility = 'active';
        $this->save_notes = ' ';
        $this->id = -1;
        $this->add();
        if (!move_uploaded_file($file, $this->getStore().$this->getPrefix().$this->id.'.zip')) {
            throw new ServerManagerAPIException("Couldn't store the uploaded ZIP file in its proper place.");
        }
    }

    public function add()
    {
        $this->validateVars();
        $args = [];
        $sql = 'INSERT INTO `game_saves` 
                (   name, 
                    game_config_version_id, 
                    game_config_files_filename,
                    game_config_versions_region,
                    game_server_id, 
                    watchdog_server_id,
					game_creation_time, 
                    game_start_year, 
                    game_end_month, 
                    game_current_month, 
                    game_running_til_time,
					password_admin, 
                    password_player, 
                    session_state,
                    game_state, 
                    game_visibility, 
                    players_active, 
                    players_past_hour, 
                    demo_session,
                    api_access_token,
                    save_type,
                    save_notes,
                    save_visibility,
                    save_timestamp,
                    server_version
                )
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $args = getPublicObjectVars($this);
        unset($args['id']);
        unset($args['game_config_files_id']);
        // game_watchdog_servers should contain something right?...
        $this->db->query('SELECT id FROM game_watchdog_servers LIMIT 1');
        $args['watchdog_server_id'] = $this->db->first(true)['id'];
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $this->id = $this->db->lastId();
    }

    public function edit()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot update without knowing which id to use.');
        }
        $this->validateVars();
        $args = getPublicObjectVars($this);
        $sql = 'UPDATE game_saves SET 
                    name = ?,
                    
                    game_config_files_filename = ?,
                    game_config_versions_region = ?,
                    game_server_id = ?,
                    watchdog_server_id = ?,
                    game_creation_time = ?,
                    game_start_year = ?,
                    game_end_month = ?,
                    game_current_month = ?,
                    game_running_til_time = ?,
                    password_admin = ?,
                    password_player = ?,
                    session_state = ?,
                    game_state = ?,
                    game_visibility = ?,
                    players_active = ?,
                    players_past_hour = ?,
                    demo_session = ?,
                    api_access_token = ?,
                    save_type = ?,
                    save_notes = ?,
                    save_visibility = ?,
                    save_timestamp = ?,
                    server_version = ?
                WHERE id = ?';
        unset($args['game_config_version_id']); // temporary hack HW 10 Jan 2023
        unset($args['game_config_files_id']);
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->save_visibility = ('active' == $this->save_visibility) ? 'archived' : 'active';
        $this->edit();
    }

    public function getFullZipPath(): bool|string
    {
        $file = $this->getStore().$this->getPrefix().$this->id.'.zip';
        if (file_exists($file)) {
            return $file;
        }

        return false;
    }

    public function load(): bool
    {
        $gamesession = new GameSession();
        $gamesession->name = $_POST['name'] ?? DB::getInstance()->ensure_unique_name(
            $this->name.' (reloaded)',
            'name',
            'game_list'
        );
        $gamesession->watchdog_server_id = $_POST['watchdog_server_id'] ?? $this->watchdog_server_id;
        $gamesession->id = -1;
        $gamesession->game_geoserver_id = null;
        $gamesession->game_config_version_id = $this->game_config_files_id;
        $gamesession->game_server_id = $this->game_server_id;
        $gamesession->game_creation_time = $this->game_creation_time;
        $gamesession->game_start_year = $this->game_start_year;
        $gamesession->game_end_month = $this->game_end_month;
        $gamesession->game_current_month = $this->game_current_month;
        $gamesession->game_running_til_time = $this->game_running_til_time;
        if (Base::isNewPasswordFormat($this->password_admin)) {
            $this->password_admin = base64_decode($this->password_admin); // backwards compatibility
        }
        $gamesession->password_admin = $this->password_admin;
        if (Base::isNewPasswordFormat($this->password_player)) {
            $this->password_player = base64_decode($this->password_player); // backwards compatibility
        }
        $gamesession->password_player = $this->password_player;
        $gamesession->session_state = 'request';
        $gamesession->game_state = $this->game_state;
        $gamesession->game_visibility = $this->game_visibility;
        $gamesession->players_active = $this->players_active;
        $gamesession->players_past_hour = $this->players_past_hour;
        $gamesession->demo_session = $this->demo_session;
        $gamesession->api_access_token = $this->api_access_token;
        $gamesession->server_version = $this->server_version;
        $gamesession->save_id = $this->id;
        $gamesession->add();

        $gamesession->sendLoadRequest();

        return true;
    }
}
