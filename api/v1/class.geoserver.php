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
	    	/*$ch = curl_init($this->baseurl . "rest/" . $url);

			curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
			

	    	//stop printing to screen
	    	curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/" . $returntype));*/
			
			$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password);
			$return = $this->CallBack($this->baseurl."rest/".$url, 
										array(), 
										array("Accept: application/" . $returntype),
										false,
										false,
										$customopt); //curl_exec($ch);

	    	//curl_close($ch);
	    	return json_decode($return, true);
		}

		public function request($url, $method = "GET", $data = "", $contentType = "text/xml") {
			$headers = array("Content-Type: " . $contentType, "Content-Length: " . strlen($data));
			$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password,
								CURLOPT_SSL_VERIFYPEER => false,
								CURLOPT_HEADER => false);
			/*$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->baseurl . "rest/" . $url);
			curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);*/

			/*if($method == "POST"){
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			}*/
			//else 
			if ($method == "DELETE" || $method == "PUT") {
				$customopt[CURLOPT_CUSTOMREQUEST] = $method;
			}

			/*if($data != ""){
				curl_setopt($ch, CURLOPT_HTTPHEADER,
					array("Content-Type: " . $contentType, "Content-Length: " . strlen($data))
				);
			}

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);*/
			
			$result = $this->CallBack($this->baseurl."rest/".$url, 
										$data, 					// content to send, see above
										$headers,				// headers to send, see above
										false,					// sync request, so wait for it
										false,					// don't json encode the $data supplied
										$customopt);			// see above additional curl opts for this request

			/*$info = curl_getinfo($ch);
			curl_close($ch);

			if($info["http_code"] == 401){
				return $info["http_code"]; //was: "Access denied. Check login credentials.";
			}
			else if($method == "DELETE" || $method == "POST"){
				return $info["http_code"];
			}
			else{
				return $result;
			}*/
			return $result;
		}

		public function ows($url){
			// echo $this->baseurl . $url . "<br/>";

			/*$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->baseurl . $url);
			curl_setopt($ch, CURLOPT_USERPWD, $this->username.":".$this->password);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);*/

			$customopt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password,
								CURLOPT_SSL_VERIFYPEER => false,
								CURLOPT_HEADER => false);

			$result = $this->CallBack($this->baseurl . $url, 
										array(), 				// no content to send
										array(),				// no headers to send
										false,					// sync request, so wait for it
										false,					// no content, so no json encoding of it required either
										$customopt);			// see above additional curl opts for this request
			/*$info = curl_getinfo($ch);

			curl_close($ch);

			if($info["http_code"] == 401) {
				return $info["http_code"]; //was: "Access denied. Check login credentials.";
			}
			else{
				return $result;
			}*/
			return $result;
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

		public function GetResources($workspace, $storename){
			$data = json_decode($this->request("resource/data/msp/" . $workspace . "/" . $storename . "?format=json", "", "json"), false);
			return (isset($data->ResourceDirectory->children->child)) ? $data->ResourceDirectory->children->child : null;
		}

		public function GetRemoteDirStruct($region=""){
			$arr = array();

			if($region == ""){
				$requestresult = $this->request("resource/data/msp?format=json");
				if ($requestresult == 401) {
					//echo 'Invalid username & password.';
					return array();
				}
				$workspaces = json_decode($requestresult);

				foreach($workspaces->ResourceDirectory->children->child as $workspace){
					$data = json_decode($this->request("resource/data/msp/" . $workspace->name . "?format=json"));
					$arr[$workspace->name] = array();

					if(!empty($data)){
						foreach($data as $d){
							if(!empty($d->children)){
								foreach($d->children->child as $datastore){
									$arr[$workspace->name][$datastore->name] = array();

									$subdir = json_decode($this->request("resource/data/msp/" . $workspace->name . "/" . $datastore->name . "?format=json"));
									if(!empty($subdir->ResourceDirectory->children)){
										foreach($subdir->ResourceDirectory->children->child as $obj){
											if(strtolower(pathinfo($obj->name, PATHINFO_EXTENSION)) == "shp" || strtolower(pathinfo($obj->name, PATHINFO_EXTENSION)) == "tif"){
												array_push($arr[$workspace->name][$datastore->name], pathinfo($obj->name, PATHINFO_FILENAME));
											}
										}
									}
								}
							}
						}
					}

				}
			}
			else{
				$data = json_decode($this->request("resource/data/msp/" . $region . "?format=json"));
				$arr = array();

				if(!empty($data)){
					foreach($data as $d){

						foreach($d->children->child as $datastore){
							$subdir = json_decode($this->request("resource/data/msp/" . $region . "/" . $datastore->name . "?format=json"));
							if(!empty($subdir->ResourceDirectory->children)){
								foreach($subdir->ResourceDirectory->children->child as $obj){
									if(strtolower(pathinfo($obj->name, PATHINFO_EXTENSION)) == "shp" || strtolower(pathinfo($obj->name, PATHINFO_EXTENSION)) == "tif"){
										array_push($arr, pathinfo($obj->name, PATHINFO_FILENAME));
									}
								}
							}
						}
					}
				}
				else{
					echo "invalid region";
				}
			}

			return $arr;
		}
	}
?>
