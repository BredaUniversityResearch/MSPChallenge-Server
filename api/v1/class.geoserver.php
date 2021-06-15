<?php
	class GeoServer extends Base {

		public $baseurl;
		public $username;
		public $password;

		public function __construct($baseurl="", $username="", $password="") {
			if (!empty($baseurl)) $this->baseurl = $baseurl;
			if (!empty($username)) $this->username = $username;
			if (!empty($password)) $this->password = $password;
		}

		//generic GET request
		public function curlGet($url, $returntype="json"){
	    	$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password);
			$return = $this->CallBack($this->baseurl."rest/".$url, 
										array(), 
										array("Accept: application/" . $returntype),
										false,
										false,
										$customopt); 
	    	return json_decode($return, true);
		}

		public function request($url, $method = "GET", $data = "", $contentType = "text/xml") {
			$headers = array("Content-Type: " . $contentType, "Content-Length: " . strlen($data));
			$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password,
								CURLOPT_SSL_VERIFYPEER => false,
								CURLOPT_HEADER => false);
			
			if ($method == "DELETE" || $method == "PUT") {
				$customopt[CURLOPT_CUSTOMREQUEST] = $method;
			}

			$result = $this->CallBack($this->baseurl."rest/".$url, 
										$data, 					// content to send, see above
										$headers,				// headers to send, see above
										false,					// sync request, so wait for it
										false,					// don't json encode the $data supplied
										$customopt);			// see above additional curl opts for this request

			return $result;
		}

		public function ows($url){
			$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password,
								CURLOPT_SSL_VERIFYPEER => false,
								CURLOPT_HEADER => false);

			try 
			{
				$result = $this->CallBack($this->baseurl . $url, 
										array(), 				// no content to send
										array(),				// no headers to send
										false,					// sync request, so wait for it
										false,					// no content, so no json encoding of it required either
										$customopt);			// see above additional curl opts for this request
				return $result;
			}
			catch (Throwable $e)
			{
				print("Geoserver request failed to url ".$url.". Exception: ".$e->getMessage().PHP_EOL);
				return null;
			}
		}

		public function HasLengthError($data, $minreq){
			if(sizeof($data) < $minreq){
				return true;
			}
			return false;
		}

		public function ParseJSON($data){
			foreach($data as $key => $d){
				$tmp = explode(",", $d['the_geom']);
				$arr = array();

				foreach($tmp as $geom){
					array_push($arr, explode(" ", trim($geom)));
				}

				$data[$key]['the_geom'] = $arr;

			}
			return json_encode($data);
		}

		public function GetAllRemoteLayers($region="northsee"){
			$layers = array();
			$requestResult = json_decode($this->request("workspaces/".$region."/layers?format=json"), true);
			if (!isset($requestResult["layers"]) || !isset($requestResult["layers"]["layer"])) 
			{
				return $layers; 
			}

			foreach ($requestResult["layers"]["layer"] as $layer) 
			{
				$layers[] = $layer["name"];
			}
			return $layers;
		}
	}
?>
