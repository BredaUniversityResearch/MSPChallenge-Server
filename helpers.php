<?php 

/* This captures all PHP errors and warnings to ensure the standard return format */
//register_shutdown_function('shutdown');
set_error_handler('exceptions_error_handler');

// function shutdown() {
//   if(!is_null($e = error_get_last()))
//   {
//     http_response_code(500);
//     echo Base::WrappedReturn(array("message" => Base::ErrorString(new ErrorException($e["message"], 0, $e["type"], $e["file"], $e["line"]))));
//   }
// }

function exceptions_error_handler($severity, $message, $filename, $lineno) {
  //if (error_reporting() == 0) {
  //  return;
  //}
  //if (error_reporting() & $severity) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
  //}
}
/* End of PHP error and warning capturing code */

if( !function_exists('apache_request_headers') ) {
    function apache_request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';

        foreach($_SERVER as $key => $val) {
            if( preg_match($rx_http, $key) ) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
           // do some nasty string manipulations to restore the original letter case
           // this should work in most cases
                $rx_matches = explode('_', $arh_key);

                if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                    foreach($rx_matches as $ak_key => $ak_val) {
                        $rx_matches[$ak_key] = ucfirst($ak_val);
                    }

                    $arh_key = implode('-', $rx_matches);
                }
                $arh_key = ucfirst(strtolower($arh_key));
                $arh[$arh_key] = $val;
            }
        }

        return( $arh );
    }
}

 ?>
