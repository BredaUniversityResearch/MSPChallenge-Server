<?php
class Log extends Base {

	const Warning = "Warning";
	const Error = "Error";
	const Fatal = "Fatal";

	protected $allowed = array("Event");

	public const LOG_ERROR = (1 << 0);
	public const LOG_WARNING = (1 << 1);
	public const LOG_INFO = (1 << 2);
	public const LOG_DEBUG = (1 << 3);

	private static $logFilter = ~0;

	public function __construct($method = "")
	{
		parent::__construct($method);
	}

	/**
	 * @apiGroup Log
	 * @apiDescription Posts an 'error' event in the server log.
	 * @api {POST} /Log/Event Post a event log
	 * @apiParam {string} source Source component of the error. Examples: Server, MEL, CEL, SEL etc.
	 * @apiParam {string} severity Severity of the errror ["Warning"|"Error"|"Fatal"]
	 * @apiParam {string} message Debugging information associated with this event
	 * @apiParam {string} stack_trace Debug stacktrace where the error occured. Optional.
	 */
	public function Event()
	{
		$this->PostEvent($_POST['source'], $_POST['severity'], $_POST['message'], isset($_POST['stack_trace'])? $_POST['stack_trace'] : "");
	}

	public function PostEvent($source, $severity, $message, $stackTrace) 
	{
		Database::GetInstance()->query("INSERT INTO event_log (event_log_source, event_log_severity, event_log_message, event_log_stack_trace) VALUES (?, ?, ?, ?)", array($source, $severity, $message, $stackTrace));
	}

	public static function ServerEvent($source, $severity, $message) 
	{
		$log = new Log();

		$e = new Exception();
		$log->PostEvent($source, $severity, $message, $e->getTraceAsString());
	}

	public static function GetRecreateLogPath()
	{
		$rootPath = getcwd();
		$logPrefix = 'log_session_';
		$sessionId = $_REQUEST['session'];

		$log_dir = $rootPath."/ServerManager"."/log";
		if (!file_exists($log_dir)) {
			mkdir($log_dir, 0777, true);
		}

		$log_file_data = $log_dir.'/'. $logPrefix . $sessionId . '.log';
		return $log_file_data;
	}

	public static function SetupFileLogger(string $logPath) 
	{
		file_put_contents($logPath, "");
		ob_start("Log::RecreateLoggingHandler", 16);
		ob_implicit_flush(1);
	}

	public static function ClearFileLogger()
	{
		ob_end_flush();
	}

	public static function LogError(string $message)
	{
		self::FormatAndPrintToLog($message, self::LOG_ERROR);
	}
	
	public static function LogWarning(string $message)
	{
		self::FormatAndPrintToLog($message, self::LOG_WARNING);
	}
	
	public static function LogInfo(string $message)
	{
		self::FormatAndPrintToLog($message, self::LOG_INFO);
	}

	public static function LogDebug(string $message)
	{
		self::FormatAndPrintToLog($message, self::LOG_DEBUG);
	}

	private static function FormatAndPrintToLog(string $message, int $logLevel) 
	{
		if ((self::$logFilter & $logLevel) == 0)
		{
			return;
		}

		$dateNow = '[' . date("Y-m-d H:i:s") . ']';
		$logCategory = "";
		if (($logLevel & self::LOG_ERROR) == self::LOG_ERROR)
		{
			$logCategory = "ERROR";
		}
		else if (($logLevel & self::LOG_WARNING) == self::LOG_WARNING)
		{
			$logCategory = "WARN";
		}
		else if (($logLevel & self::LOG_INFO) == self::LOG_INFO)
		{
			$logCategory = "INFO";
		}
		else if (($logLevel & self::LOG_DEBUG) == self::LOG_DEBUG)
		{
			$logCategory = "DEBUG";
		}
		else 
		{
			$logCategory = "UNKNOWN";
		}

		print($dateNow . " [ ". $logCategory . ' ] - ' . $message);
	}

	private static function RecreateLoggingHandler(string $message, int $phase)
	{
		file_put_contents(self::GetRecreateLogPath(), $message . PHP_EOL, FILE_APPEND);
		return ""; //Swallow all logging after this has been written to the log file.
	}
}
?>