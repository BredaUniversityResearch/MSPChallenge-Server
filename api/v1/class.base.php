<?php
	class Base
	{
		public static $debug = false;
		
		public static $more = false;
		
		public static $public = "dbfc9c465c3ed8394049f848344f4ab8";
		
		public static $workspace = "msp";
		
		public $isvalid = false;

		public function __construct($called)
		{
			if($called !== "")
			{
				$this->isvalid = $this->Validate($this->allowed, $called);
			}
		}
				
		public static function Debug($d)
		{
			echo "<pre>";
				print_r($d);
			echo "</pre>";
		}

		public static function Warning($d)
		{
			echo "<pre style='color:#c67d00; background-color:#000; padding: 10px 5px; margin: 0px; margin-top: 10px;'>";
				print_r("WARNING     ");
				print_r($d);
			echo "</pre>";
		}

		public static function Error($d)
		{
			echo "<pre style='color:#e80000; background-color:#000; padding: 10px 5px; margin-top: 10px;'>";
				print_r("ERROR       ");
				print_r($d);
			echo "</pre>";
		}

		public function GetResourceName($res)
		{
			return str_replace(".shp", "", str_replace(".SHP", "", $res));
		}
		
		public function ShowMessage($string)
		{
			print_r($string);
			echo "<hr><br/>";
		}
		
		public static function Parse($data)
		{
			if(self::$more){
				Base::Debug($data);
				return;
			}
			if(isset($data[0]['geometry']))
				$data[0]['geometry'] = json_decode($data[0]['geometry']);
			return json_encode($data);
		}

		public static function WrappedReturn($array=array()) {
			$required = array("success", "message", "payload");
			foreach ($required as $required_value) {
				if (!isset($array[$required_value])) $array[$required_value] = NULL;
			}
			if (!is_bool($array["success"])) $array["success"] = false;
			return Base::JSON($array);
		}

		public static function ErrorString($errorexception) {
			return $errorexception->getMessage() . PHP_EOL . "Of file ".$errorexception->getFile()." On line ".$errorexception->getLine() .PHP_EOL."Stack trace: ".$errorexception->getTraceAsString();
		}
		
		public static function JSON($data)
		{
			if(self::$more){
				Base::Debug($data);
				return;
			}
			return json_encode($data);
		}

		
		public static function MultiParse($data, $encode=false)
		{
			if(self::$more){
				Base::Debug($data);
				return;
			}

			$arr = array();
			foreach($data as $childkey => $child){
				array_push($arr, Base::MergeGeometry($child, false));
			}
			return ($encode) ? json_encode($arr) : $arr;
		}

		
		public static function MergeGeometry($data, $encode = false)
		{
			if(self::$more){
				Base::Debug($data);
				return;
			}

			$current = "";
			$arr = array();

			foreach($data as &$d){
				$geom = array("id" => $d['id'], 
					"geometry" => json_decode($d['geometry'], true), 
					"subtractive" => array(), 
					"persistent" => $d['persistent'], 
					"mspid" => ($d['mspid'] == null) ? 0 : $d['mspid'], 
					"type" => $d['type'],
					"country" => ($d['country'] == null) ? -1 : $d['country']);
				
				if(isset($d['active'])){
					$geom["active"] = $d['active'];
				}

				//if this geometry needs to be handled as a subtractive poly
				if(isset($d['subtractive']) && $d['subtractive'] != 0){
					foreach($arr as &$g){
						if($g['id'] == $d['subtractive']){
							if(!is_array($g['subtractive'])){
								$g['subtractive'] = array();
							}

							array_push($g['subtractive'], $geom);
						}
					}
				}
				else{
					array_push($arr, $geom);
				}

				if($d['data'] == "[]")
					$arr[sizeof($arr)-1]['data'] = "";
				else
					$arr[sizeof($arr)-1]['data'] = json_decode($d['data']);
			}

			return ($encode) ? json_encode($arr) : $arr;
		}

		
		protected function Validate($allowed, $called)
		{
			$calledFunctionAllowed = false;
			$accessFlagsRequired = Security::ACCESS_LEVEL_FLAG_FULL;

			$calledLower = strtolower($called);
			foreach($allowed as $allowedToCheck)
			{
				if (is_array($allowedToCheck))
				{
					if (strtolower($allowedToCheck[0]) == $calledLower)
					{
						$calledFunctionAllowed = true;
						$accessFlagsRequired = $allowedToCheck[1];
						break;
					}
				}
				else if (strtolower($allowedToCheck) == $calledLower)
				{
					$calledFunctionAllowed = true;
					break;
				}
			}

			$security = new Security();
			return $calledFunctionAllowed && $security->ValidateAccess($accessFlagsRequired);
		}
		
		protected function FillEmptyPostVars($keys, $postvar) 
		{
			if (!is_array($keys) || !is_array($postvar)) return false;
			foreach ($keys as $value) {
				if (!isset($postvar[$value])) {
					$postvar[$value] = "";
				}
			}
			return $postvar;
		}

		
		public static function TimeStamp()
		{
		    return date("Y-m-d H:i:s", time());
		}
		
		public static function CreateDateTime($timestamp)
		{
			return date("Y-m-d H:i:s", $timestamp);
		}
		
		public function GroupMessages($userid, $data)
		{
			$arr = array();

			foreach($data as $message){
				
				//message thread index
				$index = 0;

				if($message['receiver'] != $userid){
					$index = $message['receiver'];
				}
				else{
					$index = $message['sender'];
				}
				
				//if the thread doesn't exist, create a new one
				if(!isset($arr[$index])){
					$arr[$index] = array();
				}

				//push the message in the array
				array_push($arr[$index], $message);
			}


			return json_encode($arr);
		}

		
		public static function Dir()
		{
			$abs_app_root = $_SERVER['DOCUMENT_ROOT'];
			$url_app_root = '';
			$self_path = explode("/", $_SERVER['PHP_SELF']);
			$self_path_length = count($self_path);
			for($i = 1; $i < $self_path_length; $i++){
				array_splice($self_path, $self_path_length-$i, $i);
				$url_app_root = implode("/",$self_path)."/";
				if (file_exists($abs_app_root.$url_app_root.'api_config.php')) break;
			}
			return rtrim($abs_app_root.$url_app_root, "/");
		}

		public function PHPCanProxy() 
		{
			if (!empty(ini_get('open_basedir')) || ini_get('safe_mode')) {
				return false;
			}
			return true;
		}

		public function CallBack($url, $postarray, $headers = array(), $async = false, $asjson = false, $customopt = array()) 
		{
			$ch = curl_init($url);
	
			// any proxy required for the external calls of any kind (MSP Authoriser, BUas GeoServer, or any other GeoServer)
			$proxy = Config::GetInstance()->GetAuthWithProxy();
			if (!empty($proxy) && strstr($url, GameSession::GetRequestApiRoot()) === false && strstr($url, "localhost") === false && $this->PHPCanProxy()) {
				curl_setopt($ch, CURLOPT_PROXY, $proxy);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
			}

			curl_setopt($ch, CURLOPT_USERAGENT, "MSP Challenge Server API");

			if ($asjson) $post = json_encode($postarray);
			else $post = $postarray;
			if (!empty($post)) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			if (!empty($headers)) {
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			}
			if (!$async) {
				curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			}
			else {
				curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			}

			if (!empty($customopt)) {
				foreach ($customopt as $key => $val) {
					curl_setopt($ch, $key, $val);
				}
			}

			$return = curl_exec($ch);
			$info = curl_getinfo($ch);
			
			if ($async == false && ($return === false || $info === false || $info["http_code"] == 401))
			{
				throw new Exception("Request failed to url ".$url.PHP_EOL."CURL Error: ".curl_error($ch).PHP_EOL."Response Http code: ".($info["http_code"] ?? "Unknown").PHP_EOL."Response Page output: ".($return??"Nothing"));
			}
			curl_close($ch);
			
			return $return;
		}
	}
?>
