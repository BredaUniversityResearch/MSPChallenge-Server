<?php

namespace ServerManager;

class Watchdog extends Base
{
    private ?DB $db = null;
    private $old;

    public $name;
    public $address;
    public $available;
    public $id;
    
    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    private function validateVars()
    {
        if (self::HasSpecialChars($this->name)) {
            throw new ServerManagerAPIException("Watchdog name cannot contain special characters.");
        }
        if (!filter_var(gethostbyname($this->address), FILTER_VALIDATE_IP)) {
            throw new ServerManagerAPIException("Watchdog address is not a valid domain name or IP address.");
        }
        if (intval($this->available) !== 0 && intval($this->available) !== 1) {
            throw new ServerManagerAPIException("Watchdog available should be 0 or 1.");
        }
    }

    public function get()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException("Cannot obtain Watchdog without a valid id.");
        }
        $this->db->query("SELECT * FROM game_watchdog_servers WHERE id = ?;", array($this->id));
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        if ($this->db->count() == 0) {
            throw new ServerManagerAPIException("Watchdog server not found.");
        }
        foreach ($this->db->first(true) as $varname => $varvalue) {
            if (property_exists($this, $varname)) {
                $this->$varname = $varvalue;
            }
        }

        $this->old = clone $this;
    }

    public function getList(): array
    {
        $this->db->query("SELECT id, name, address, available FROM game_watchdog_servers");
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        return $this->db->results(true);
    }

    public function add()
    {
        $this->validateVars();
        $this->db->query(
            "INSERT INTO game_watchdog_servers (
                                    name, 
                                    address, 
                                    available
                                    ) VALUES (?, ?, ?)",
            array(
                                    $this->name,
                                    $this->address,
                                    $this->available
            )
        );
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $this->id = $this->db->lastId();
    }

    public function edit()
    {
        $this->validateVars();
        if (empty($this->id)) {
            throw new ServerManagerAPIException("Cannot update without knowing which id to use.");
        }
        $args = getPublicObjectVars($this);
        $sql = "UPDATE game_watchdog_servers SET 
                    name = ?,
                    address = ?,
                    available = ?
                WHERE id = ?;";
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->available = ($this->available == 1) ? 0 : 1;
        $this->edit();
    }
}
