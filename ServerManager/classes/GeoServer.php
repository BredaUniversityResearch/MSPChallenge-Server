<?php

class GeoServer extends Base
{
    private $_db;
    private $_id;
    private $_list;

    public $name;
    public $address;
    public $username;
    public $password;    

    public function __construct() 
    {
        $this->_db = DB::getInstance();
    }

    private function setList($list)
    {
        if (!is_array($list)) $list = array();
        $this->_list = array("geoserver" => $list);
    }

    private function validateVars()
    {
        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $var)
        {   
            $varname = $var->getName();
            if (empty($this->$varname)) return "Missing value for ".$varname;
        }

        $this->address = filter_var($this->address, FILTER_VALIDATE_URL);
        if ($this->address === false) return "The GeoServer address is not a fully-qualified URL.";
        if (substr($this->address, -1) != "/") $this->address .= "/";

        return true;
    }

    public function create() 
    {
        $validated = $this->validateVars();
        if ($validated === true)
        {
            try 
            {
                $result = $this->_db->query("SELECT id FROM game_geoservers WHERE name LIKE ? OR address LIKE ?", array($this->name, $this->address));
                if ($result->count())
                {
                    return "Duplicate GeoServer found in database. Please change name and/or address.";
                } else
                {
                    if ($this->_db->query("INSERT INTO game_geoservers (name, address, username, password) VALUES (?, ?, ?, ?)", array($this->name, $this->address, base64_encode($this->username), base64_encode($this->password))))
                    {
                        return true;
                    } else 
                    {
                        return $this->_db->errorString();
                    }
                }
            } catch (Exception $e) 
            {
                return $e->getMessage();
            }
        } else
        {
            return $validated;
        }
    }

    public function getList()
    {
        $query_string = "SELECT id, address, name FROM game_geoservers";
        try 
        {
            if ($this->_db->query($query_string)) 
            {
                $this->setList($this->_db->results(true));
                return $this->_list;
            }
            else 
            {
                return $this->_db->errorString();
            }
        } catch (Exception $e) 
        {
            return $e->getMessage();
        }
    }

    
    
}