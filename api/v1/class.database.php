<?php

class Database {
	
	private $configurationApplied = false;
	private $db_host;
	private $db_user;
	private $db_pass;
	public $db_name;
	
	private $conn;
	private bool $isTransactionRunning = false;
	
	private static $instance = null;

	protected $salt = 'c02b7d24a066adb747fdeb12deb21bfa';

	private static array $PDOArgs = array(
		PDO::MYSQL_ATTR_LOCAL_INFILE => true,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
		PDO::ATTR_TIMEOUT => 5
	);
	
	function __construct()
	{
		if (self::$instance != null)
		{
			throw new Exception("Creation of multiple database instances."); 
		}
		self::$instance = $this;
		$this->status = 'dev';
		$this->SetupConfiguration();
	}

	function __destruct()
	{
		if ($this->isTransactionRunning)
		{
			$this->DBRollbackTransaction();
			throw new Exception("DB destructed when transaction was still running. Rolling back");
		}
		$conn = null;
		self::$instance = null;
	}

	public static function GetInstance()
	{
		if (self::$instance == null)
		{
			new Database();
		}
		return self::$instance;
	}
	
	private function SetupConfiguration($overrideSessionId = GameSession::INVALID_SESSION_ID)
	{
		if ($this->configurationApplied === false) {
			$dbConfig = Config::GetInstance()->DatabaseConfig();
			$this->db_host = $dbConfig["host"];
			$this->db_user = $dbConfig["user"];
			$this->db_pass = $dbConfig["password"];

			$sessionId = ($overrideSessionId == GameSession::INVALID_SESSION_ID)? GameSession::GetGameSessionIdForCurrentRequest() : $overrideSessionId;
			if ($sessionId != GameSession::INVALID_SESSION_ID) {
				$this->db_name = $dbConfig["multisession_database_prefix"].$sessionId;
				$this->configurationApplied = true;
			} else {
				// a proper sessionId is simply required
				$this->configurationApplied = false;
			}
		}
	}

	public function reloadSetupConfiguration()
	{
		$this->configurationApplied = false;
		$this->SetupConfiguration();
	}

	private function connectToDatabase() 
	{
		if ($this->conn == null) {

			try {
				$this->SetupConfiguration();
				$this->conn = new PDO('mysql:host='.$this->db_host.';dbname='.$this->db_name, $this->db_user, $this->db_pass, self::$PDOArgs);
			} catch(PDOException $e){
				$this->conn = null;
			}
		}
	}

	private function CreateNewConnectionToConfiguredHost($user, $password)
	{
		$connection = new PDO("mysql:host=".$this->db_host, $user, $password, self::$PDOArgs);
		return $connection;
	}

	public static function CreateTemporaryDBConnection($dbHost, $user, $password, $dbName) 
	{
		$connection = new PDO("mysql:host=".$dbHost, $user, $password, self::$PDOArgs);
		$connection->query("USE ".$dbName);
		return $connection;
	}

	private function isConnectedToDatabase() 
	{
		return $this->conn != null;
	}

	public function query($statement, $vars = null, $getid=false)
	{
		$this->connectToDatabase();
		if (!$this->isConnectedToDatabase()) {
			return [];
		}
		$result = array();
		
		try {
			$query = $this->executeQuery($statement, $vars);
		} catch (PDOException $e) {
			throw new Exception("Query exception: ".$e->getMessage()." Query: ".str_replace(array("\n", "\r", "\t"), " ", var_export($statement, true))." Vars: ".str_replace(array("\n", "\r"), "", var_export($vars, true)));
		}

		if($getid == true)
			return $this->conn->lastInsertID();
		
		//Just making sure we aren't calling fetchAll on an empty result set (update/insert queries) which will result in a SQLSTATE[HY000] exception.
		if ($query != null && $query->columnCount() > 0)
		{
			$result = $query->fetchAll(PDO::FETCH_ASSOC);
			
			foreach($result as $key1=>$arr){
				foreach($arr as $key=>$var)
					$result[$key1][$key] = $var;
			}
		}

		$query = null;
		return $result;
	}

	public function queryReturnAffectedRowCount($statement, $vars = null) 
	{
		$query = $this->executeQuery($statement, $vars);
		$result = $query->rowCount();
		$query = null;
		return $result;
	}

	private function executeQuery($statement, $vars) 
	{ 
		$this->connectToDatabase();
		
		$query = $this->prepareQuery($statement);
		$this->executePreparedQuery($query, $vars);
		return $query;
	}

	public function prepareQuery($statement) 
	{
		$this->connectToDatabase();
		return $this->conn->prepare($statement);
	}

	public function executePreparedQuery($query, $vars)
	{
		if ($vars != null) {
			$query->execute($vars);
		}
		else {
			$query->execute();
		}
	}

	public function quote($string)
	{
		//Simple wrapper for PDO::Quote since that needs an instance of the connection...
		$this->connectToDatabase();
		return $this->conn->quote($string);
	}

	public function GetDatabaseName()
	{
		$this->SetupConfiguration();
		return $this->db_name;
	}

	public function SwitchToSessionDatabase($sessionId)
	{
		$this->SetupConfiguration($sessionId);
		$this->SwitchDatabase($this->GetDatabaseName());
	}

	public function SwitchDatabase($databaseName)
	{
		Database::GetInstance()->query("USE ".$databaseName);
		$this->db_name = $databaseName;
	}

	public function CreateDatabaseAndSelect()
	{
		$this->SetupConfiguration();

		$dbConfig = Config::GetInstance()->DatabaseConfig();

		$temporaryConnection = $this->CreateNewConnectionToConfiguredHost($dbConfig["multisession_create_user"], $dbConfig["multisession_create_password"]);
		$temporaryConnection->query("CREATE DATABASE IF NOT EXISTS ".$this->db_name);
		$temporaryConnection->query("GRANT ALL PRIVILEGES ON `".str_replace("_", "\_", $this->db_name)."`.* TO ".$this->db_user."@localhost");
		$temporaryConnection = null;

		$this->connectToDatabase();
	}

	public function DropSessionDatabase($databaseName)
	{
		$dbConfig = Config::GetInstance()->DatabaseConfig();

		if (stripos($databaseName, $dbConfig['multisession_database_prefix']) !== false)
		{
			$temporaryConnection = $this->CreateNewConnectionToConfiguredHost($dbConfig["multisession_create_user"], $dbConfig["multisession_create_password"]);
			
			$temporaryConnection->query("DROP DATABASE IF EXISTS ".$databaseName);
		}
	}
	
	public function DBStartTransaction() 
	{ 
		$this->connectToDatabase();
		if ($this->isConnectedToDatabase()) 
		{
			if ($this->isTransactionRunning)
			{
				throw new Exception("Running multiple transactions");
			}
			
			$this->conn->beginTransaction();
			$this->isTransactionRunning = true;
		}
	}

	public function DBCommitTransaction() 
	{ 
		if ($this->isConnectedToDatabase()) 
		{
			if (!$this->isTransactionRunning)
			{
				throw new Exception("Commiting transaction when no transaction is running");
			}
			$this->conn->commit();
			$this->isTransactionRunning = false;
		}
	}

	public function DBRollbackTransaction() 
	{ 
		if (!$this->isTransactionRunning)
		{
			return;
		}
		if ($this->isConnectedToDatabase()) 
		{
			$this->conn->rollBack();
			$this->isTransactionRunning = false;
		}
	}

	public function Encrypt($string)
	{
		return hash_hmac('sha512', $string, $this->salt);
	}

	protected static function execInBackground($cmd) 
	{ 
		if (substr(php_uname(), 0, 7) == "Windows"){ 
			pclose(popen("start /B ". $cmd, "r"));  
		} 
		else { 
			exec($cmd . " > /dev/null &");   
		} 
	}
	
	private function GetMysqlExecutableDirectory()
	{
		$mysqlDir = Database::GetInstance()->query("SELECT @@basedir as mysql_home");
		return $mysqlDir[0]["mysql_home"];
	}

	public function CreateMspDatabaseDump($outputFilePath, $blockUntilComplete) 
	{
		//Creates a database dump at the given path blocks until it's done.
		$this->CreateDatabaseDump($outputFilePath, $blockUntilComplete, $this->db_host, $this->db_user, $this->db_pass, $this->GetDatabaseName());
	}

	public function CreateDatabaseDump($outputFilePath, $blockUntilComplete, $databaseHost, $databaseUser, $dbPassword, $databaseName)
	{
		$dumpCommand = $this->GetMysqlExecutableDirectory()."/bin/mysqldump --user=\"".$databaseUser."\" --password=\"".$dbPassword."\" --host=\"".$databaseHost."\" \"".$databaseName."\" > \"".$outputFilePath."\"";
		if ($blockUntilComplete == true)
		{
			exec($dumpCommand);
		}
		else 
		{
			self::execInBackground($dumpCommand);
		}
	}
}
