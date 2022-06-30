<?php

namespace App\Domain\API\v1;

use App\Domain\Common\DatabaseDefaults;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private bool $configurationApplied = false;
    private ?string $db_host = null;
    private ?string $db_user = null;
    private ?string $db_pass = null;
    public ?string $db_name = null;
    
    private ?PDO $conn = null;
    private bool $isTransactionRunning = false;

    /**
     * @var Database[] array
     */
    private static array $instances = [];

    protected string $salt = 'c02b7d24a066adb747fdeb12deb21bfa';

    private static array $PDOArgs = array(
        PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    );

    private int $sessionId;

    /**
     * @throws Exception
     */
    public function __construct(int $overrideSessionId = GameSession::INVALID_SESSION_ID)
    {
        // note that it is possible that sessionId is equal to INVALID_SESSION_ID (=-1), in which case the Database
        //   instance is created, but will not do any queries since there will be no valid configuration/connection
        $this->sessionId = ($overrideSessionId == GameSession::INVALID_SESSION_ID) ?
            GameSession::GetGameSessionIdForCurrentRequest() : $overrideSessionId;

        if (isset(self::$instances[$this->sessionId])) {
            throw new Exception("Creation of multiple database instances for session: " . $this->sessionId);
        }
        self::$instances[$this->sessionId] = $this;
        $this->SetupConfiguration();
    }

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        if ($this->isTransactionRunning) {
            $this->DBRollbackTransaction();
            throw new Exception("DB destructed when transaction was still running. Rolling back");
        }
        self::$instances[$this->sessionId] = null;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetInstance(int $overrideSessionId = GameSession::INVALID_SESSION_ID): Database
    {
        // note that it is possible that sessionId is equal to INVALID_SESSION_ID (=-1), in which case the Database
        //   instance is created, but will not do any queries since there will be no valid configuration/connection
        $sessionId = ($overrideSessionId == GameSession::INVALID_SESSION_ID) ?
            GameSession::GetGameSessionIdForCurrentRequest() : $overrideSessionId;
        if (!isset(self::$instances[$sessionId])) {
            self::$instances[$sessionId] = new Database($sessionId);
        }
        return self::$instances[$sessionId];
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetupConfiguration(): bool
    {
        // valid configured setup has already been applied
        if ($this->configurationApplied) {
            return true;
        }

        $dbConfig = Config::GetInstance()->DatabaseConfig();
        $this->db_host = $dbConfig["host"];
        $this->db_user = $dbConfig["user"];
        $this->db_pass = $dbConfig["password"];

        if ($this->sessionId != GameSession::INVALID_SESSION_ID) {
            $this->db_name = $dbConfig["multisession_database_prefix"].$this->sessionId;
            $this->configurationApplied = true;
        } else {
            // a proper sessionId is simply required
            $this->configurationApplied = false;
        }

        return $this->configurationApplied;
    }

    private function connectToDatabase(): bool
    {
        if ($this->conn == null) {
            // check if configuration has been applied
            if (!$this->SetupConfiguration()) {
                return false;
            }
            try {
                $dsn = 'mysql:host='.$this->db_host.';dbname='.$this->db_name .
                    ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT);
                $this->conn = new PDO(
                    $dsn,
                    $this->db_user,
                    $this->db_pass,
                    self::$PDOArgs
                );
            } catch (PDOException $e) {
                $this->conn = null;
                return false;
            }
        }
        return true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CreateNewConnectionToConfiguredHost(string $user, string $password): PDO
    {
        $dsn = "mysql:host=".$this->db_host .
            ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT);
        return new PDO($dsn, $user, $password, self::$PDOArgs);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function CreateTemporaryDBConnection(
        string $dbHost,
        string $user,
        string $password,
        string $dbName
    ): PDO {
        $dsn = "mysql:host=".$dbHost .
            ';port='.($_ENV['DATABASE_PORT'] ?? DatabaseDefaults::DEFAULT_DATABASE_PORT);
        $connection = new PDO($dsn, $user, $password, self::$PDOArgs);
        $connection->query("USE ".$dbName);
        return $connection;
    }

    private function isConnectedToDatabase(): bool
    {
        return $this->conn != null;
    }

    /**
     * @param string $statement
     * @param array|null $vars
     * @param bool $getId
     * @return array|string
     * @throws Exception
     */
    public function query(
        string $statement,
        ?array $vars = null,
        bool $getId = false
    ) {/*: array|string */ // <-- for php 8
        if (!$this->connectToDatabase()) {
            return [];
        }
        $result = array();
        
        try {
            $query = $this->executeQuery($statement, $vars);
        } catch (Exception $e) {
            throw new Exception(
                "Query exception: ".$e->getMessage()." Query: ".
                str_replace(array("\n", "\r", "\t"), " ", var_export($statement, true)).
                " Vars: ".str_replace(array("\n", "\r"), "", var_export($vars, true)),
                (int)$e->getCode(), // just pass the original exception code
                $e // pass previous such that it is possible to debug back to the original exception
            );
        }

        if ($getId == true) {
            return $this->conn->lastInsertID();
        }
        
        // Just making sure we aren't calling fetchAll on an empty result set (update/insert queries)
        //   which will result in a SQLSTATE[HY000] exception.
        if ($query != null && $query->columnCount() > 0) {
            $result = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            foreach ($result as $key1 => $arr) {
                foreach ($arr as $key => $var) {
                    $result[$key1][$key] = $var;
                }
            }
        }

        $query = null;
        return $result;
    }

    /**
     * @throws Exception
     */
    public function queryReturnAffectedRowCount(string $statement, ?array $vars = null): int
    {
        if (false === $query = $this->executeQuery($statement, $vars)) {
            return 0;
        }
        $result = $query->rowCount();
        $query = null;
        return $result;
    }

    /**
     * @param string $statement
     * @param array|null $vars
     * @return false|PDOStatement
     * @throws Exception
     */
    private function executeQuery(string $statement, ?array $vars)/*: false|PDOStatement*/ // <<-- for php 8
    {
        if (!$this->connectToDatabase()) {
            return false;
        }
        
        if (false === $query = $this->prepareQuery($statement)) {
            return false;
        }
        $this->executePreparedQuery($query, $vars);
        return $query;
    }

    /**
     * @param string $statement
     * @return false|PDOStatement
     */
    public function prepareQuery(string $statement)/*: false|PDOStatement*/ // <<-- for php 8
    {
        if (!$this->connectToDatabase()) {
            return false;
        }
        return $this->conn->prepare($statement);
    }

    /**
     * @throws Exception
     */
    public function executePreparedQuery(PDOStatement $query, ?array $vars):  bool
    {
        if ($vars != null) {
            if (!is_array($vars)) {
                throw new Exception(
                    "Failed to execute prepared statement. Vars is not an array. Value: ".
                    var_export($vars, true)." Query: ".$query->queryString
                );
            }
            return $query->execute($vars);
        } else {
            return $query->execute();
        }
    }

    /**
     * @param string $string
     * @return false|string
     */
    public function quote(string $string)/*: false|string*/ // <<-- for php 8
    {
        //Simple wrapper for PDO::Quote since that needs an instance of the connection...
        if (!$this->connectToDatabase()) {
            return false;
        }
        return $this->conn->quote($string);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetDatabaseName(): ?string
    {
        $this->SetupConfiguration();
        return $this->db_name;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SwitchToSessionDatabase(int $sessionId): void
    {
        $this->SetupConfiguration($sessionId);
        $this->SwitchDatabase($this->GetDatabaseName());
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function SwitchDatabase(string $databaseName): void
    {
        Database::GetInstance()->query("USE ".$databaseName);
        $this->db_name = $databaseName;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateDatabaseAndSelect(): void
    {
        $this->SetupConfiguration();

        $dbConfig = Config::GetInstance()->DatabaseConfig();

        $temporaryConnection = $this->CreateNewConnectionToConfiguredHost(
            $dbConfig["multisession_create_user"],
            $dbConfig["multisession_create_password"]
        );
        $temporaryConnection->query("CREATE DATABASE IF NOT EXISTS ".$this->db_name);
        $temporaryConnection->query(
            "GRANT ALL PRIVILEGES ON `".str_replace("_", "\_", $this->db_name)."`.* TO ".$this->db_user."@localhost"
        );
        $temporaryConnection = null;

        $this->connectToDatabase();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function DropSessionDatabase(string $databaseName): void
    {
        $dbConfig = Config::GetInstance()->DatabaseConfig();

        if (stripos($databaseName, $dbConfig['multisession_database_prefix']) !== false) {
            $temporaryConnection = $this->CreateNewConnectionToConfiguredHost(
                $dbConfig["multisession_create_user"],
                $dbConfig["multisession_create_password"]
            );
            
            $temporaryConnection->query("DROP DATABASE IF EXISTS ".$databaseName);
        }
    }

    /**
     * @throws Exception
     */
    public function DBStartTransaction(): void
    {
        if ($this->connectToDatabase()) {
            if ($this->isTransactionRunning) {
                throw new Exception("Running multiple transactions");
            }
            
            $this->conn->beginTransaction();
            $this->isTransactionRunning = true;
        }
    }

    /**
     * @throws Exception
     */
    public function DBCommitTransaction(): void
    {
        if ($this->isConnectedToDatabase()) {
            if (!$this->isTransactionRunning) {
                throw new Exception("Commiting transaction when no transaction is running");
            }
            $this->conn->commit();
            $this->isTransactionRunning = false;
        }
    }

    public function DBRollbackTransaction(): void
    {
        if (!$this->isTransactionRunning) {
            return;
        }
        if ($this->isConnectedToDatabase()) {
            $this->conn->rollBack();
            $this->isTransactionRunning = false;
        }
    }

    /**
     * @param string$string
     * @return false|string
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Encrypt(string $string)/*: false|string*/ // <<-- for php 8
    {
        return hash_hmac('sha512', $string, $this->salt);
    }

    public static function execInBackground(string $cmd): void
    {
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B \"msp background task\" ". $cmd, "r"));
        } else {
            exec($cmd . " > /dev/null &");
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetMysqlExecutableDirectory(): string
    {
        $dbConfig = Config::GetInstance()->DatabaseConfig();
        // todo: improve this by using ConnectionManager?
        $temporaryConnection = Database::CreateTemporaryDBConnection(
            $dbConfig["host"],
            $dbConfig["user"],
            $dbConfig["password"],
            $dbConfig["database"]
        );
        $result = $temporaryConnection->query("SELECT @@basedir as mysql_home");
        $mysqlDir = $result->fetch(PDO::FETCH_ASSOC);
        $temporaryConnection = null;
        return $mysqlDir["mysql_home"] ?? '';
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateMspDatabaseDump(string $outputFilePath, bool $blockUntilComplete): void
    {
        //Creates a database dump at the given path blocks until it's done.
        $this->CreateDatabaseDump(
            $outputFilePath,
            $blockUntilComplete,
            $this->db_host,
            $this->db_user,
            $this->db_pass,
            $this->GetDatabaseName()
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CreateDatabaseDump(
        string $outputFilePath,
        bool $blockUntilComplete,
        string $databaseHost,
        string $databaseUser,
        string $dbPassword,
        string $databaseName
    ): void {
        $dumpCommand = "\"".$this->GetMysqlExecutableDirectory()."/bin/mysqldump\" --user=\"".
            $databaseUser."\" --password=\"".$dbPassword."\" --host=\"".
            $databaseHost."\" \"".$databaseName."\" > \"".$outputFilePath."\"";
        if ($blockUntilComplete) {
            exec($dumpCommand);
        } else {
            self::execInBackground($dumpCommand);
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportMspDatabaseDump(string $importFilePath, bool $blockUntilComplete): void
    {
        //Creates a database dump at the given path blocks until it's done.
        $this->ImportDatabaseDump(
            $importFilePath,
            $blockUntilComplete,
            $this->db_host,
            $this->db_user,
            $this->db_pass,
            $this->GetDatabaseName()
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function ImportDatabaseDump(
        string $importFilePath,
        bool $blockUntilComplete,
        string $databaseHost,
        string $databaseUser,
        string $dbPassword,
        string $databaseName
    ): void {
        $dumpCommand = "\"".$this->GetMysqlExecutableDirectory()."/bin/mysql\" --user=\"".
            $databaseUser."\" --password=\"".$dbPassword."\" --host=\"".
            $databaseHost."\" \"".$databaseName."\" < \"".$importFilePath."\"";
        if ($blockUntilComplete == true) {
            exec($dumpCommand);
        } else {
            self::execInBackground($dumpCommand);
        }
    }
}
