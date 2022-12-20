<?php

namespace ServerManager;

class GeoServer extends Base
{
    private ?DB $db = null;
    private $old;

    public $name;
    public $address;
    public $username;
    public $password;
    public $available;
    public $id;

    public function __construct()
    {
        $this->db = DB::getInstance();
    }

    private function validateVars()
    {
        if (self::HasSpecialChars($this->name)) {
            throw new ServerManagerAPIException('GeoServer name cannot contain special characters.');
        }
        if (false === filter_var($this->address, FILTER_VALIDATE_URL)) {
            throw new ServerManagerAPIException('The GeoServer address is not a fully-qualified URL.');
        }
        if (!str_ends_with($this->address, '/')) {
            $this->address .= '/';
        }
        if (self::EmptyOrHasSpaces($this->username)) {
            throw new ServerManagerAPIException('GeoServer username cannot be empty or contain spaces.');
        }
        if (self::EmptyOrHasSpaces($this->password)) {
            throw new ServerManagerAPIException('GeoServer password cannot be empty or contain spaces.');
        }
        if (0 !== intval($this->available) && 1 !== intval($this->available)) {
            throw new ServerManagerAPIException('GeoServer available should be 0 or 1.');
        }
    }

    public function retrievePublic()
    {
        $vars = ['jwt' => $this->jwt, 'audience' => ServerManager::getInstance()->GetBareHost()];
        $authoriser_call = self::postCallAuthoriser(
            'geocredjwt.php',
            $vars
        );
        if (!$authoriser_call['success']) {
            throw new ServerManagerAPIException('Could not obtain public MSP Challenge GeoServer credentials.');
        }
        $this->address = $authoriser_call['credentials']['baseurl'] ?? '';
        $this->username = $authoriser_call['credentials']['username'] ?? '';
        $this->password = $authoriser_call['credentials']['password'] ?? '';
    }

    public function get()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot obtain data without a valid geoserver id.');
        }

        $this->db->query('SELECT * FROM game_geoservers WHERE id = ?', [$this->id]);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        if (0 == $this->db->count()) {
            throw new ServerManagerAPIException('GeoServer not found.');
        }
        foreach ($this->db->first(true) as $varname => $varvalue) {
            if (property_exists($this, $varname)) {
                $this->$varname = $varvalue;
            }
        }

        if (1 == $this->id && null !== $this->jwt) {
            $this->retrievePublic(); // this will get the BUas public GeoServer address and credentials
        }

        $this->old = clone $this;
    }

    public function getList(): array
    {
        $this->db->query('SELECT id, name, address, available FROM game_geoservers');
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }

        return $this->db->results(true);
    }

    public function delete()
    {
        // soft delete only - either set or reverted
        $this->get();
        $this->available = (1 == $this->available) ? 0 : 1;
        $this->edit();
    }

    public function edit()
    {
        if (empty($this->id)) {
            throw new ServerManagerAPIException('Cannot update without knowing which id to use.');
        }
        $this->validateVars();
        $args = getPublicObjectVars($this);
        $sql = 'UPDATE game_geoservers SET 
                    name = ?,
                    address = ?,
                    username = ?,
                    password = ?,
                    available = ?
                WHERE id = ?';
        $this->db->query($sql, $args);
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
    }

    public function add()
    {
        $this->validateVars();
        $args = getPublicObjectVars($this);
        unset($args['id']);
        $this->db->query(
            'INSERT INTO game_geoservers (
                                    name, 
                                    address, 
                                    username, 
                                    password,
                                    available
                                    ) VALUES (?, ?, ?, ?, ?)',
            $args
        );
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
        $this->id = $this->db->lastId();
    }
}
