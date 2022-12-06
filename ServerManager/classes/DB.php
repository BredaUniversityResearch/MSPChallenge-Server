<?php

namespace ServerManager;

/*
UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use App\Domain\Helper\Config;
use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DB
{
    private static ?DB $instance = null;
    private PDO $pdo;
    private PDOStatement|false|null $query = null;
    private bool $error = false;
    private $errorInfo;
    private array $results=[];
    private array $resultsArray=[];
    private int $count = 0;
    private $lastId;
    private int $queryCount=0;
    private $host;
    private $dbname;
    private $user;
    private $pass;

    private static array $PDOArgs = array(
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    );

    private function __construct($config = [])
    {
        if ($config == []) {
            $this->host = Config::get('mysql/host');
            $this->dbname = Config::get('mysql/db');
            $this->user = Config::get('mysql/username');
            $this->pass =  Config::get('mysql/password');
        } else {
            if (is_array($config) && count($config) == 1) {
                $this->host = Config::get($config[0].'/host');
                $this->dbname = Config::get($config[0].'/db');
                $this->user = Config::get($config[0].'/username');
                $this->pass = Config::get($config[0].'/password');
            } else {
                $this->host = $config[0];
                $this->dbname = $config[1];
                $this->user = $config[2];
                $this->pass = $config[3];
            }
        }
        
        try {
            $dsn = 'mysql:host='.$this->host.
                    ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT).
                    ';dbname='.$this->dbname;
            $this->pdo = new PDO($dsn, $this->user, $this->pass, self::$PDOArgs);
            // XAMPP doesn't seem to remove databases when it uninstalls MySQL (which means a reinstall could lead to
            //problems because the dbase will still be there but maybe empty/outdated)
            // so check if the server_id is available in the settings table - caught below if it doesn't
            $server_id_attempt = $this->cell("settings.value", array("name", "=", "server_id"));
            if (empty($server_id_attempt)) {
                throw new Exception();
            }
        } catch (Exception $e) {
            // assumes connection failed because the database doesn't exist yet, so attempt to create and fill it,
            //thereby reattempting connection
            $this->attempt_dbase_install();
        }
    }

    public static function getInstance(): ?DB
    {
        if (!isset(self::$instance)) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    public static function getDB($config)
    {
        self::$instance = new DB($config);
        return self::$instance;
    }

    public function query($sql, $params = array()): self
    {
        //echo "DEBUG: query(sql=$sql, params=".print_r($params,true).")<br />\n";
        $this->queryCount++;
        $this->error = false;
        $this->errorInfo = array(0, null, null);
        $this->resultsArray=[];
        $this->count=0;
        $this->lastId=0;
        if ($this->query = $this->pdo->prepare($sql)) {
            $x = 1;
            if (count($params)) {
                foreach ($params as $param) {
                    $this->query->bindValue($x, $param);
                    $x++;
                }
            }

            if ($this->query->execute()) {
                if ($this->query->columnCount() > 0) {
                    $this->results = $this->query->fetchALL(PDO::FETCH_OBJ);
                    $this->resultsArray = json_decode(json_encode($this->results), true);
                }
                $this->count = $this->query->rowCount();
                $this->lastId = $this->pdo->lastInsertId();
            } else {
                $this->error = true;
                $this->errorInfo = $this->query->errorInfo();
            }
        }
        $this->query->closeCursor();
        return $this;
    }

    public function findAll($table)
    {
        return $this->action('SELECT *', $table);
    }

    public function findById($id, $table)
    {
        return $this->action('SELECT *', $table, array('id','=',$id));
    }

    public function action($action, $table, $where = array(), $orderby = null)
    {
        $sql    = "{$action} FROM {$table}";
        $values = array();
        $is_ok  = true;

        if ($where_text = $this->_calcWhere($where, $values, "and", $is_ok)) {
            $sql .= " WHERE $where_text";
        }

        if (!is_null($orderby)) {
            $sql .= " ORDER BY ".$orderby;
        }

        if ($is_ok) {
            if (!$this->query($sql, $values)->error()) {
                return $this;
            }
        }

        return false;
    }

    // phpcs:ignore
    private function _calcWhere($w, &$vals, $comboparg = 'and', &$is_ok = null): ?string
    {
        #echo "DEBUG: Entering _calcwhere(w=".print_r($w,true).",...)<br />\n";
        if (is_array($w)) {
                #echo "DEBUG: is_array - check<br />\n";
            $comb_ops   = ['and', 'or', 'and not', 'or not'];
            $valid_ops  = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'ALIKE', 'NOT ALIKE', 'REGEXP',
                'NOT REGEXP'];
            $two_args   = ['IS NULL', 'IS NOT NULL'];
            $four_args  = ['BETWEEN', 'NOT BETWEEN'];
            $arr_arg    = ['IN', 'NOT IN'];
            $nested_arg = ['ANY', 'ALL', 'SOME'];
            $nested     = ['EXISTS', 'NOT EXISTS'];
            $nestedIN   = ['IN SELECT', 'NOT IN SELECT'];
            $wcount     = count($w);

            if ($wcount == 0) {
                return "";
            }

            # believe it or not, this appears to be the fastest way to check
            # sequential vs associative. Particularly with our expected short
            # arrays it shouldn't impact memory usage
            # https://gist.github.com/Thinkscape/1965669
            if (array_values($w) === $w) { // sequential array
                        #echo "DEBUG: Sequential array - check!<br />\n";
                if (in_array(strtolower($w[0]), $comb_ops)) {
                            #echo "DEBUG: w=".print_r($w,true)."<br />\n";
                    $sql = '';
                    $combop = '';
                    for ($i = 1; $i < $wcount; $i++) {
                        $sql .= ' '. $combop . ' ' . $this->_calcWhere($w[$i], $vals, "and", $is_ok);
                        $combop = $w[0];
                    }
                    return '('.$sql.')';
                } elseif ($wcount==3  &&  in_array($w[1], $valid_ops)) {
                    #echo "DEBUG: normal condition w=".print_r($w,true)."<br />\n";
                    $vals[] = $w[2];
                    return "{$w[0]} {$w[1]} ?";
                } elseif ($wcount==2  &&  in_array($w[1], $two_args)) {
                    return "{$w[0]} {$w[1]}";
                } elseif ($wcount==4  &&  in_array($w[1], $four_args)) {
                    $vals[] = $w[2];
                    $vals[] = $w[3];
                    return "{$w[0]} {$w[1]} ? AND ?";
                } elseif ($wcount==3  &&  in_array($w[1], $arr_arg)  &&  is_array($w[2])) {
                    $vals = array_merge($vals, $w[2]);
                    return "{$w[0]} {$w[1]} (" . substr(str_repeat(",?", count($w[2])), 1) . ")";
                } elseif (($wcount==5 || $wcount==6 && is_array($w[5]))  &&  in_array($w[1], $valid_ops) &&
                    in_array($w[2], $nested_arg)) {
                    return  "{$w[0]} {$w[1]} {$w[2]}" . $this->get_subquery_sql($w[4], $w[3], $w[5], $vals, $is_ok);
                } elseif (($wcount==3 || $wcount==4 && is_array($w[3]))  &&  in_array($w[0], $nested)) {
                    return $w[0] . $this->get_subquery_sql($w[2], $w[1], $w[3], $vals, $is_ok);
                } elseif (($wcount==4 || $wcount==5 && is_array($w[4]))  &&  in_array($w[1], $nestedIN)) {
                    return "{$w[0]} " . substr($w[1], 0, -7) . $this->get_subquery_sql(
                        $w[3],
                        $w[2],
                        $w[4],
                        $vals,
                        $is_ok
                    );
                } else {
                    echo "ERROR: w=".print_r($w, true)."<br />\n";
                    $is_ok = false;
                    return null;
                }
            } else { // associative array ['field' => 'value']
                #echo "DEBUG: Associative<br />\n";
                $sql = '';
                $combop = '';
                foreach ($w as $k => $v) {
                    if (in_array(strtolower($k), $comb_ops)) {
                        #echo "DEBUG: A<br />\n";
                        #echo "A: k=$k, v=".print_r($v,true)."<br />\n";
                        $sql .= $combop . ' (' . $this->_calcWhere($v, $vals, $k, $is_ok) . ') ';
                        $combop = $comboparg;
                    } else {
                        #echo "DEBUG: B<br />\n";
                        #echo "B: k=$k, v=".print_r($v,true)."<br />\n";
                        $vals[] = $v;
                        if (in_array(substr($k, -1, 1), array('=', '<', '>'))) { // 'field !='=>'value'
                            $sql .= $combop . ' ' . $k . ' ? ';
                        } else { // 'field'=>'value'
                            $sql .= $combop . ' ' . $k . ' = ? ';
                        }
                        $combop = $comboparg;
                    }
                }
                return ' ('.$sql.') ';
            }
        } else {
            echo "ERROR: No array in $w<br />\n";
            $is_ok = false;
            return null;
        }
    }

    public function get($table, $where, $orderby = null): bool|static
    {
        return $this->action('SELECT *', $table, $where, $orderby);
    }

    public function delete($table, $where): bool|static
    {
        return empty($where) ? false : $this->action('DELETE', $table, $where);
    }

    public function deleteById($table, $id): bool|static
    {
        return $this->action('DELETE', $table, array('id','=',$id));
    }

    public function insert($table, $fields = [], $update = false): bool
    {
        $keys    = array_keys($fields);
        $values  = [];
        $records = 0;

        foreach ($fields as $field) {
            $count = is_array($field) ? count($field) : 1;

            if (!isset($first_time)  ||  $count<$records) {
                $first_time = true;
                $records    = $count;
            }
        }

        for ($i=0; $i<$records; $i++) {
            foreach ($fields as $field) {
                $values[] = is_array($field) ? $field[$i] : $field;
            }
        }

        $col = ",(" . substr(str_repeat(",?", count($fields)), 1) . ")";
        $sql = "INSERT INTO {$table} (`". implode('`,`', $keys)."`) VALUES ". substr(str_repeat($col, $records), 1);

        if ($update) {
            $sql .= " ON DUPLICATE KEY UPDATE";

            foreach ($keys as $key) {
                if ($key != "id") {
                    $sql .= " `$key` = VALUES(`$key`),";
                }
            }

            if (!empty($keys)) {
                $sql = substr($sql, 0, -1);
            }
        }

        return !$this->query($sql, $values)->error();
    }

    public function update($table, $id, $fields): bool
    {
        $sql   = "UPDATE {$table} SET " . (empty($fields) ? "" : "`") . implode("` = ? , `", array_keys($fields)) .
            (empty($fields) ? "" : "` = ? ");
        $is_ok = true;

        if (!is_array($id)) {
            $sql     .= "WHERE id = ?";
            $fields[] = $id;
        } else {
            if (empty($id)) {
                return false;
            }

            if ($where_text = $this->_calcWhere($id, $fields, "and", $is_ok)) {
                $sql .= "WHERE $where_text";
            }
        }

        if ($is_ok) {
            if (!$this->query($sql, $fields)->error()) {
                return true;
            }
        }

        return false;
    }

    public function results($assoc = false): array
    {
        if ($assoc) {
            return ($this->resultsArray) ? $this->resultsArray : [];
        }
        return ($this->results) ? $this->results : [];
    }

    public function first($assoc = false)
    {
        return (!$assoc || $assoc && $this->count()>0)  ?  $this->results($assoc)[0]  :  [];
    }

    public function count(): int
    {
        return $this->count;
    }

    public function error(): bool
    {
        return $this->error;
    }

    public function errorInfo()
    {
        return $this->errorInfo;
    }

    public function errorString(): string
    {
        return 'ERROR #'.$this->errorInfo[0].': '.$this->errorInfo[2];
    }

    public function lastId()
    {
        return $this->lastId;
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function get_subquery_sql($action, $table, $where, &$values, &$is_ok): string
    {
        if (is_array($where)) {
            if ($where_text = $this->_calcWhere($where, $values, "and", $is_ok)) {
                $where_text = " WHERE $where_text";
            }
        }

        return " (SELECT $action FROM $table$where_text)";
    }

    public function cell($tablecolumn, $id = [])
    {
        $input = explode(".", $tablecolumn, 2);

        if (count($input) != 2) {
            return null;
        }

        $result = $this->action("SELECT {$input[1]}", $input[0], (is_numeric($id) ? ["id","=",$id] : $id));

        return ($result && $this->count>0)  ?  $this->resultsArray[0][$input[1]]  :  null;
    }

    public function getColCount(): int
    {
        return $this->query->columnCount();
    }

    public function getColMeta($counter): bool|array
    {
        return $this->query->getColumnMeta($counter);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function dbase_migrate(): void
    {
        // this function is called in index.php
        $directory = ServerManager::getInstance()->GetServerManagerRoot()."install/migrations";
        $files = array_diff(scandir($directory), array('..', '.'));
        // for each file found, check if the filename is in the settings table
        foreach ($files as $file) {
            $this->query("SELECT value FROM settings WHERE name = ?", array($file));
            if (empty($this->results(true))) {
                // if it isn't then require_once and add it to the database
                $sql = '';
                require_once(ServerManager::getInstance()->GetServerManagerRoot()."install/migrations/".$file);
                $sql =
                "START TRANSACTION;"
                .$sql.
                "INSERT INTO settings (name, value) VALUES (?, ?);
				COMMIT;";
                $this->query($sql, array($file, date("Y-m-d H:i:s")));
            }
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function attempt_dbase_install(): void
    {
        try {
            $dsn = 'mysql:host='.$this->host.
                ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT);
            $this->pdo = new PDO($dsn, $this->user, $this->pass, self::$PDOArgs);
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `".$this->dbname."` DEFAULT CHARACTER SET utf8;");
            $dsn .= ';dbname='.$this->dbname;
            $this->pdo = new PDO($dsn, $this->user, $this->pass, self::$PDOArgs);
        } catch (PDOException $e) {
            // if the above connection attempt even fails, then assume MySQL cannot be connected to for another more
            //  general reason.
            $this->error = true;
            $this->errorInfo = $e->errorInfo;
            return;
        }

        $application = new Application(SymfonyToLegacyHelper::getInstance()->getKernel());
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $returnCode = $application->run(
            new StringInput('doctrine:migrations:migrate -vvv -n --em='.$this->dbname),
            $output
        );
        if (0 !== $returnCode) {
            $this->error = true;
            $this->errorInfo = 'Failed to apply newest migrations to database: '.$this->dbname.PHP_EOL.$output->fetch();
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ensure_unique_name($name, $column, $table)
    {
        // ensures that $name is a unique value in the database, given the $table and $column to check
        // will add (1) or (2) for example to ensure the $name is unique
        $foundrecord = $this->cell($table.".".$column, [$column, "=", $name]);
        if ($foundrecord == $name) {
            $counter = 0;
            do {
                $counter++;
                $nametocheck = $name." (".$counter.")";
                $foundrecord = $this->cell($table.".".$column, [$column, "=", $nametocheck]);
            } while ($foundrecord == $nametocheck);
            $name = $nametocheck;
        }
        return $name;
    }
}
