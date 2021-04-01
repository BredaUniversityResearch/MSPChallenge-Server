<?php

class Base 
{
    // needs a function to call server API
    public function callServer($session_id, $api_access_token, $endpoint, $data2send)
    {
        $call_return = $this->callAPI("POST", ServerManager::getInstance()->GetServerURLBySessionId($session_id).$endpoint, $data2send, array($api_access_token), false);
        $call_return_decoded = json_decode($call_return);
        if ($call_return_decoded->success) return true;
        else return $call_return_decoded->message;
    }

    // needs a function to call Authoriser API
    public function callAuthoriser($endpoint, $data2send)
    {
        return $this->callAPI("POST", ServerManager::getInstance()->GetMSPAuthAPI().$endpoint, $data2send);
    }

    // needs a generic calling something function
    private function callAPI($method, $url, $data2send = false, $headers = array(), $asjson = true) 
    {
        $curl = curl_init();
        switch ($method)
        {
          case "POST":
              curl_setopt($curl, CURLOPT_POST, 1);
              if ($data2send) {
                if ($asjson) $data2send = json_encode($data2send);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data2send);
              }
              break;
          case "PUT":
              curl_setopt($curl, CURLOPT_PUT, 1);
              break;
          default:
              if ($data2send) {
                $url = sprintf("%s?%s", $url, http_build_query($data2send));
              }
        }
        if (!empty($headers) && is_array($headers)) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
    
        // any proxy required for the external calls to the MSP Authoriser? (which are the only kind of external calls done by ServerManager)
        $proxy = Config::get('msp_auth/with_proxy');
        if (!empty($proxy) && strstr($url, ServerManager::getInstance()->GetMSPAuthAPI()) !== false && PHPCanProxy()) {
          curl_setopt($curl, CURLOPT_PROXY, $proxy);
          curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
        }
        curl_setopt($curl, CURLOPT_USERAGENT, "MSP Challenge Server Manager API");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}