<?php

class Watchdog extends Base
{
    private $_db;
    private $_old;

    public $name;
    public $address;
    public $available;
    public $id;
    
    public function __construct() 
    {
        $this->_db = DB::getInstance();
    }

    private function validateVars()
    {
        if (self::HasSpecialChars($this->name)) 
            throw new Exception("Watchdog name cannot contain special characters.");
        if (!filter_var(gethostbyname($this->address), FILTER_VALIDATE_IP)) 
            throw new Exception("Watchdog address is not a valid domain name or IP address.");
        if (intval($this->available) !== 0 && intval($this->available) !== 1) 
            throw new Exception("Watchdog available should be 0 or 1.");
    }

    public function get()
    {
        if (empty($this->id)) throw new Exception("Cannot obtain Watchdog without a valid id.");
        if (!$this->_db->query("SELECT * FROM game_watchdog_servers WHERE id = ?;", array($this->id))) throw new Exception($this->_db->errorString());
        if ($this->_db->count() == 0) throw new Exception("Watchdog server not found.");
        foreach ($this->_db->first(true) as $varname => $varvalue)
        {
            if (property_exists($this, $varname)) $this->$varname = $varvalue;
        }

        $this->_old = clone $this;
    }

    public function getList()
    {
        if (!$this->_db->query("SELECT id, name, address, available FROM game_watchdog_servers")) throw new Exception($this->_db->errorString());
        return $this->_db->results(true);
    }

    public function add() 
    {
        $this->validateVars();
        if (!$this->_db->query("INSERT INTO game_watchdog_servers (
                                    name, 
                                    address, 
                                    available
                                    ) VALUES (?, ?, ?)", 
                                array(
                                    $this->name, 
                                    $this->address, 
                                    $this->available
                                ))) throw new Exception($this->_db->errorString());
        $this->id = $this->_db->lastId();
    }

    public function edit()
    {
        $this->validateVars();
        if (empty($this->id)) throw new Exception("Cannot update without knowing which id to use.");
        $args = getPublicObjectVars($this);
        $sql = "UPDATE game_watchdog_servers SET 
                    name = ?,
                    address = ?,
                    available = ?
                WHERE id = ?;";
        if (!$this->_db->query($sql, $args)) throw new Exception($this->_db->errorString());
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->available = ($this->available == 1) ? 0 : 1;
        $this->edit();
    }


}