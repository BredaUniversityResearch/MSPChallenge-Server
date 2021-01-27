<?php
	class Log extends Base {

		const Warning = "Warning";
		const Error = "Error";
		const Fatal = "Fatal";

		protected $allowed = array("Event");

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
			$this->query("INSERT INTO event_log (event_log_source, event_log_severity, event_log_message, event_log_stack_trace) VALUES (?, ?, ?, ?)", array($source, $severity, $message, $stackTrace));
		}

		public static function ServerEvent($source, $severity, $message) 
		{
			$log = new Log();

			$e = new Exception();
			$log->PostEvent($source, $severity, $message, $e->getTraceAsString());
		}
	}
?>