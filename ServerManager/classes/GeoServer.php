<?php

class GeoServer extends Base
{
    private $_db;
    private $_old;

    public $name;
    public $address;
    public $username;
    public $password;
    public $available;
    public $id;

    public function __construct() 
    {
        $this->_db = DB::getInstance();
    }

    private function validateVars()
    {
        if (self::HasSpecialChars($this->name)) 
            throw new Exception("GeoServer name cannot contain special characters.");
        if (filter_var($this->address, FILTER_VALIDATE_URL) === false) 
            throw new Exception("The GeoServer address is not a fully-qualified URL.");
        if (substr($this->address, -1) != "/") 
            $this->address .= "/";
        if (self::EmptyOrHasSpaces($this->username)) 
            throw new Exception("GeoServer username cannot be empty or contain spaces.");
        if (self::EmptyOrHasSpaces($this->password)) 
            throw new Exception("GeoServer password cannot be empty or contain spaces.");
        if (intval($this->available) !== 0 && intval($this->available) !== 1) 
            throw new Exception("GeoServer available should be 0 or 1.");
    }

    public function retrievePublic()
    {
        $vars = array("jwt" => $this->_jwt, "audience" => ServerManager::getInstance()->GetBareHost());
        $authoriser_call = self::callAuthoriser(
            "geocredjwt.php", 
            $vars
        );
        if (!$authoriser_call["success"]) throw new Exception("Could not obtain public MSP Challenge GeoServer credentials.");
        $this->address = $authoriser_call["credentials"]["baseurl"] ?? "";
        $this->username = $authoriser_call["credentials"]["username"] ?? "";
        $this->password = $authoriser_call["credentials"]["password"] ?? "";
    }

    public function get() 
    {
        if (empty($this->id)) throw new Exception("Cannot obtain data without a valid id.");
        
        if (!$this->_db->query("SELECT * FROM game_geoservers WHERE id = ?", array($this->id))) throw new Exception($this->_db->errorString());
        if ($this->_db->count() == 0) throw new Exception("GeoServer not found.");
        foreach ($this->_db->first(true) as $varname => $varvalue)
        {
            if (property_exists($this, $varname)) $this->$varname = $varvalue;
        }
        
        if ($this->id == 1 && !is_null($this->_jwt)) $this->retrievePublic(); // this will get the BUas public GeoServer address and credentials 

        $this->_old = clone $this;
    }

    public function getList()
    {
        if (!$this->_db->query("SELECT id, name, address, available FROM game_geoservers")) throw new Exception($this->_db->errorString());
        return $this->_db->results(true);
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->available = ($this->available == 1) ? 0 : 1;
        $this->edit();
    }

    public function edit()
    {
        if (empty($this->id)) throw new Exception("Cannot update without knowing which id to use.");
        $this->validateVars();
        $args = getPublicObjectVars($this);
        $sql = "UPDATE game_geoservers SET 
                    name = ?,
                    address = ?,
                    username = ?,
                    password = ?,
                    available = ?
                WHERE id = ?";
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
    }

    public function add() 
    {
        $this->validateVars();
        $args = getPublicObjectVars($this);
        unset($args["id"]);
        if (!$this->_db->query("INSERT INTO game_geoservers (
                                    name, 
                                    address, 
                                    username, 
                                    password,
                                    available
                                    ) VALUES (?, ?, ?, ?, ?)", 
                                    $args)) throw new Exception($this->_db->errorString());
        $this->id = $this->_db->lastId();
    }    
    
}