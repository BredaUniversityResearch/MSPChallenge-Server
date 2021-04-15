<?php 

/* This captures all PHP errors and warnings to ensure the standard return format */
set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
/* End of PHP error and warning capturing code */

function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

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
