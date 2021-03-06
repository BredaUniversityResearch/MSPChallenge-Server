<?php
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

class DB {
	private static $_instance = null;
	private $_pdo, $_query, $_error = false, $_errorInfo, $_results=[], $_resultsArray=[], $_count = 0, $_lastId, $_queryCount=0;
	private $_host, $_dbname, $_user, $_pass;

	private static $PDOArgs = array(
		PDO::MYSQL_ATTR_LOCAL_INFILE => true,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
		PDO::ATTR_TIMEOUT => 5
	);

	private function __construct($config = []){
		if($config == []) {
			$this->_host = Config::get('mysql/host');
			$this->_dbname = Config::get('mysql/db');
			$this->_user = Config::get('mysql/username');
			$this->_pass =	Config::get('mysql/password');
		}
		else {
			if(is_array($config) && count($config) == 1) {
				$this->_host = Config::get($config[0].'/host');
				$this->_dbname = Config::get($config[0].'/db');
				$this->_user = Config::get($config[0].'/username');
				$this->_pass = Config::get($config[0].'/password');
			}
			else {
				$this->_host = $config[0];
				$this->_dbname = $config[1];
				$this->_user = $config[2];
				$this->_pass = $config[3];
			}
		}
		
		try {
            $dsn = 'mysql:host='.$this->_host.
                    ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT).
                    ';dbname='.$this->_dbname;
			$this->_pdo = new PDO($dsn, $this->_user, $this->_pass, self::$PDOArgs);
			// XAMPP doesn't seem to remove databases when it uninstalls MySQL (which means a reinstall could lead to problems because the dbase will still be there but maybe empty/outdated)
			// so check if the server_id is available in the settings table - caught below if it doesn't
			$server_id_attempt = $this->cell("settings.value", array("name", "=", "server_id"));
            if (empty($server_id_attempt))
            {
                throw new Exception();
            }
		} catch (Exception $e) { 
			// assumes connection failed because the database doesn't exist yet, so attempt to create and fill it, thereby reattempting connection
			$this->attempt_dbase_install();
		}
		
	}

	public static function getInstance(){
		if (!isset(self::$_instance)) {
			self::$_instance = new DB();
		}
		return self::$_instance;
	}

	public static function getDB($config){
			self::$_instance = new DB($config);
		return self::$_instance;
	}

	public function query($sql, $params = array()){
		//echo "DEBUG: query(sql=$sql, params=".print_r($params,true).")<br />\n";
		$this->_queryCount++;
		$this->_error = false;
		$this->_errorInfo = array(0, null, null); $this->_resultsArray=[]; $this->_count=0; $this->_lastId=0;
		if ($this->_query = $this->_pdo->prepare($sql)) {
			$x = 1;
			if (count($params)) {
				foreach ($params as $param) {
					$this->_query->bindValue($x, $param);
					$x++;
				}
			}

			if ($this->_query->execute()) {
				if ($this->_query->columnCount() > 0) {
					$this->_results = $this->_query->fetchALL(PDO::FETCH_OBJ);
					$this->_resultsArray = json_decode(json_encode($this->_results),true);
				}
				$this->_count = $this->_query->rowCount();
				$this->_lastId = $this->_pdo->lastInsertId();
			} else{
				$this->_error = true;
				$this->_errorInfo = $this->_query->errorInfo();
			}
		}
		$this->_query->closeCursor();
		return $this;
	}

	public function findAll($table){
		return $this->action('SELECT *',$table);
	}

	public function findById($id,$table){
		return $this->action('SELECT *',$table,array('id','=',$id));
	}

	public function action($action, $table, $where = array(), $orderby = null){
		$sql    = "{$action} FROM {$table}";
		$values = array();
		$is_ok  = true;

		if ($where_text = $this->_calcWhere($where, $values, "and", $is_ok))
			$sql .= " WHERE $where_text";

		if (!is_null($orderby))
			$sql .= " ORDER BY ".$orderby;

		if ($is_ok)
			if (!$this->query($sql, $values)->error())
				return $this;

		return false;
	}

	private function _calcWhere($w, &$vals, $comboparg='and', &$is_ok=NULL) {
		#echo "DEBUG: Entering _calcwhere(w=".print_r($w,true).",...)<br />\n";
		if (is_array($w)) {
				#echo "DEBUG: is_array - check<br />\n";
			$comb_ops   = ['and', 'or', 'and not', 'or not'];
			$valid_ops  = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'ALIKE', 'NOT ALIKE', 'REGEXP', 'NOT REGEXP'];
			$two_args   = ['IS NULL', 'IS NOT NULL'];
			$four_args  = ['BETWEEN', 'NOT BETWEEN'];
			$arr_arg    = ['IN', 'NOT IN'];
			$nested_arg = ['ANY', 'ALL', 'SOME'];
			$nested     = ['EXISTS', 'NOT EXISTS'];
			$nestedIN   = ['IN SELECT', 'NOT IN SELECT'];
			$wcount     = count($w);

			if ($wcount == 0)
				return "";

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

				} elseif ($wcount==3  &&  in_array($w[1],$valid_ops)) {
					#echo "DEBUG: normal condition w=".print_r($w,true)."<br />\n";
					$vals[] = $w[2];
					return "{$w[0]} {$w[1]} ?";

				} elseif ($wcount==2  &&  in_array($w[1],$two_args)) {
					return "{$w[0]} {$w[1]}";

				} elseif ($wcount==4  &&  in_array($w[1],$four_args)) {
					$vals[] = $w[2];
					$vals[] = $w[3];
					return "{$w[0]} {$w[1]} ? AND ?";

				} elseif ($wcount==3  &&  in_array($w[1],$arr_arg)  &&  is_array($w[2])) {
					$vals = array_merge($vals,$w[2]);
					return "{$w[0]} {$w[1]} (" . substr( str_repeat(",?",count($w[2])), 1) . ")";

				} elseif (($wcount==5 || $wcount==6 && is_array($w[5]))  &&  in_array($w[1],$valid_ops)  &&  in_array($w[2],$nested_arg)) {
					return  "{$w[0]} {$w[1]} {$w[2]}" . $this->get_subquery_sql($w[4],$w[3],$w[5],$vals,$is_ok);

				} elseif (($wcount==3 || $wcount==4 && is_array($w[3]))  &&  in_array($w[0],$nested)) {
					return $w[0] . $this->get_subquery_sql($w[2],$w[1],$w[3],$vals,$is_ok);

				} elseif (($wcount==4 || $wcount==5 && is_array($w[4]))  &&  in_array($w[1],$nestedIN)) {
					return "{$w[0]} " . substr($w[1],0,-7) . $this->get_subquery_sql($w[3],$w[2],$w[4],$vals,$is_ok);

				} else {
					echo "ERROR: w=".print_r($w,true)."<br />\n";
					$is_ok = false;
				}
			} else { // associative array ['field' => 'value']
				#echo "DEBUG: Associative<br />\n";
				$sql = '';
				$combop = '';
				foreach ($w as $k=>$v) {
					if (in_array(strtolower($k), $comb_ops)) {
						#echo "DEBUG: A<br />\n";
						#echo "A: k=$k, v=".print_r($v,true)."<br />\n";
						$sql .= $combop . ' (' . $this->_calcWhere($v, $vals, $k, $is_ok) . ') ';
						$combop = $comboparg;
					} else {
						#echo "DEBUG: B<br />\n";
						#echo "B: k=$k, v=".print_r($v,true)."<br />\n";
						$vals[] = $v;
						if (in_array(substr($k,-1,1), array('=', '<', '>'))) // 'field !='=>'value'
							$sql .= $combop . ' ' . $k . ' ? ';
						else // 'field'=>'value'
							$sql .= $combop . ' ' . $k . ' = ? ';
						$combop = $comboparg;
					}
				}
				return ' ('.$sql.') ';
			}
		} else {
			echo "ERROR: No array in $w<br />\n";
			$is_ok = false;
		}
	}

	public function get($table, $where, $orderby = null){
		return $this->action('SELECT *', $table, $where, $orderby);
	}

	public function delete($table, $where){
		return empty($where) ? false : $this->action('DELETE', $table, $where);
	}

	public function deleteById($table,$id){
		return $this->action('DELETE',$table,array('id','=',$id));
	}

	public function insert($table, $fields=[], $update=false) {
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

		for ($i=0; $i<$records; $i++)
			foreach ($fields as $field)
				$values[] = is_array($field) ? $field[$i] : $field;

		$col = ",(" . substr( str_repeat(",?",count($fields)), 1) . ")";
		$sql = "INSERT INTO {$table} (`". implode('`,`', $keys)."`) VALUES ". substr( str_repeat($col,$records), 1);

		if ($update) {
			$sql .= " ON DUPLICATE KEY UPDATE";

			foreach ($keys as $key)
				if ($key != "id")
					$sql .= " `$key` = VALUES(`$key`),";

			if (!empty($keys))
				$sql = substr($sql, 0, -1);
		}

		return !$this->query($sql, $values)->error();
	}

	public function update($table, $id, $fields){
		$sql   = "UPDATE {$table} SET " . (empty($fields) ? "" : "`") . implode("` = ? , `", array_keys($fields)) . (empty($fields) ? "" : "` = ? ");
		$is_ok = true;

		if (!is_array($id)) {
			$sql     .= "WHERE id = ?";
			$fields[] = $id;
		} else {
			if (empty($id))
				return false;

			if ($where_text = $this->_calcWhere($id, $fields, "and", $is_ok))
				$sql .= "WHERE $where_text";
		}

		if ($is_ok)
			if (!$this->query($sql, $fields)->error())
				return true;

		return false;
	}

	public function results($assoc = false){
		if($assoc) return ($this->_resultsArray) ? $this->_resultsArray : [];
		return ($this->_results) ? $this->_results : [];
	}

	public function first($assoc = false){
		return (!$assoc || $assoc && $this->count()>0)  ?  $this->results($assoc)[0]  :  [];
	}

	public function count(){
		return $this->_count;
	}

	public function error(){
		return $this->_error;
	}

	public function errorInfo() {
		return $this->_errorInfo;
	}

	public function errorString() {
		return 'ERROR #'.$this->_errorInfo[0].': '.$this->_errorInfo[2];
	}

	public function lastId(){
		return $this->_lastId;
	}

	public function getQueryCount(){
		return $this->_queryCount;
	}

	private function get_subquery_sql($action, $table, $where, &$values, &$is_ok) {
		if (is_array($where))
			if ($where_text = $this->_calcWhere($where, $values, "and", $is_ok))
				$where_text = " WHERE $where_text";

		return " (SELECT $action FROM $table$where_text)";
	}

	public function cell($tablecolumn, $id=[]) {
		$input = explode(".", $tablecolumn, 2);

		if (count($input) != 2)
			return null;

		$result = $this->action("SELECT {$input[1]}", $input[0], (is_numeric($id) ? ["id","=",$id] : $id));

		return ($result && $this->_count>0)  ?  $this->_resultsArray[0][$input[1]]  :  null;
	}

	public function getColCount(){
		return $this->_query->columnCount();
	}

	public function getColMeta($counter){
		return $this->_query->getColumnMeta($counter);
	}
	
	public function dbase_migrate() {
		// this function is called in index.php
		$directory = ServerManager::getInstance()->GetServerManagerRoot()."install/migrations";
		$files = array_diff(scandir($directory), array('..', '.'));
		// for each file found, check if the filename is in the settings table
		foreach ($files as $file) {
			$this->query("SELECT value FROM settings WHERE name = ?", array($file));
			if (empty($this->results(true))) {
				// if it isn't then require_once and add it to the database
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
	
	public function attempt_dbase_install() {
		try {
			$this->_pdo = null;
            $dsn = 'mysql:host='.$this->_host.
                ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT);
			$this->_pdo = new PDO($dsn, $this->_user, $this->_pass, self::$PDOArgs);
			$this->_pdo->exec("CREATE DATABASE IF NOT EXISTS `".$this->_dbname."` DEFAULT CHARACTER SET utf8;");
			$this->_pdo = null;
            $dsn .= ';dbname='.$this->_dbname;
			$this->_pdo = new PDO($dsn, $this->_user, $this->_pass, self::$PDOArgs);
			require_once(ServerManager::getInstance()->GetServerManagerRoot()."install/mysql_structure.php");
			$this->query($sqls);
		}
		catch (PDOException $e) {
			// if the above connection attempt even fails, then assume MySQL cannot be connected to for another more general reason.
			$this->_error = true;
			$this->_errorInfo = $e->errorInfo;
		}
	}

	public function ensure_unique_name($name, $column, $table) {
		// ensures that $name is a unique value in the database, given the $table and $column to check
		// will add (1) or (2) for example to ensure the $name is unique
		$foundrecord = $this->cell($table.".".$column, [$column, "=", $name]);
		if ($foundrecord == $name) {
		  $counter = 0;
		  do {
			$counter++;
			$nametocheck = $name." (".$counter.")";
			$foundrecord = $this->cell($table.".".$column, [$column, "=", $nametocheck]);
		  }
		  while ($foundrecord == $nametocheck);
		  $name = $nametocheck;
		}
		return $name;
	}

}
