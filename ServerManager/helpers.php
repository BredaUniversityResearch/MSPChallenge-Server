<?php
//functions that help things along. by definition functions that are so common or fundamental,
//require limited and diverse arguments, it makes no sense to turn them into classes

use ServerManager\DB;
use ServerManager\Input;

function getPublicObjectVars($obj): array
{
    return get_object_vars($obj);
}

function PHPCanProxy(): bool
{
    if (!empty(ini_get('open_basedir')) || ini_get('safe_mode')) {
        return false;
    }
    return true;
}

function isJsonObject($string): bool
{
    return is_object(json_decode($string));
}

function isJsonArray($string): bool
{
    return is_array(json_decode($string, true));
}

// Readeable file size
function size($path): string
{
    $bytes = sprintf('%u', filesize($path));

    if ($bytes > 0) {
        $unit = intval(log($bytes, 1024));
        $units = array('B', 'KB', 'MB', 'GB');

        if (array_key_exists($unit, $units) === true) {
            return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]);
        }
    }

    return $bytes;
}

function bold($text): void
{
    echo "<span><ext padding='1em' align='center'><h4><span style='background:white'>";
    echo $text;
    echo "</h4></span></text>";
}

function err($text): void
{
    /** @noinspection XmlDeprecatedElement */
    /** @noinspection HtmlUnknownAttribute */
    /** @noinspection HtmlDeprecatedAttribute */
    /** @noinspection HtmlDeprecatedTag */
    echo "<span><text padding='1em' align='center'><font color='red'><h4></span>";
    echo $text;
    echo "</h4></span></font></text>";
}

function redirect($location): void
{
    header("Location: $location");
}

//Displays error and success messages
if (!function_exists('resultBlock')) {
    function resultBlock($errors, $successes): void
    {
      //Error block
        if (count($errors) > 0) {
            echo "<div class='alert alert-danger alert-dismissible' role='alert'> " .
                "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>".
                "<span aria-hidden='true'>&times;</span></button><ul style='padding-left:1.25rem !important'>";
            foreach ($errors as $error) {
                echo "<li>".$error."</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

      //Success block
        if (count($successes) > 0) {
            echo "<div class='alert alert-success alert-dismissible' role='alert'> ".
                "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>".
                "<span aria-hidden='true'>&times;</span></button><ul style='padding-left:1.25rem !important'>";
            foreach ($successes as $success) {
                echo "<li>".$success."</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
    }
}

//Inputs language strings from selected language.



function lang($key, $markers = null)
{
    global $lang, $us_url_root, $abs_us_root;

    $fnHandleMarkers = function () use ($markers, $lang, $key) {
        if ($markers == null) {
            return $lang[$key] ?? "";
        }
        //Replace any dyamic markers
        if (isset($lang[$key])) {
            $str = $lang[$key];
            $iteration = 1;
            foreach ($markers as $marker) {
                $str = str_replace("%m".$iteration."%", $marker, $str);
                $iteration++;
            }
            return $str;
        }
        return '';
    };
    $str = $fnHandleMarkers();

  //Ensure we have something to return
  // dump($key);
    if ($str == "") {
        $missing = $lang["MISSING_TEXT"] ?? "Missing Text";
      //if nothing is found, let's check to see if the language is English.
        if (isset($lang['THIS_CODE']) && $lang['THIS_CODE'] != "en-US") {
            $save = $lang['THIS_CODE'];
            if ($save == '') {
                $save = 'en-US';
            }
        //if it is NOT English, we are going to try to grab the key from the English translation
            include("lang/en-US.php");
            $str = $fnHandleMarkers();
            $lang = [];
            include("lang/$save.php");
            if ($str == "") {
              //This means that we went to the English file and STILL did not find the language key, so...
                return "{ $missing }";
            } else {
              //falling back to English
                return $str;
            }
        } else {
          //the language is already English but the code is not found so...
            return "{ $missing }";
        }
    } else {
        return $str;
    }
}


if (!function_exists('clean')) {
    //Cleaning function
    function clean($string): array|string|null
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z\d]/', '', $string); // Removes special chars.

        return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
    }
}


if (!function_exists('encodeURIComponent')) {
    function encodeURIComponent($str): string
    {
        $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
        return strtr(rawurlencode($str), $revert);
    }
}

if (!function_exists('logger')) {
    function logger($user_id, $logtype, $lognote)
    {
        $db = DB::getInstance();

        $fields = array(
        'user_id' => $user_id,
        'logdate' => date("Y-m-d H:i:s"),
        'logtype' => $logtype,
        'lognote' => $lognote,
        'ip'            => $_SERVER['REMOTE_ADDR'],
        );
        $db->insert('logs', $fields);
        return $db->lastId();
    }
}

if (!function_exists('isLocalhost')) {
    function isLocalhost(): bool
    {
        if ($_SERVER["REMOTE_ADDR"]=="127.0.0.1" || $_SERVER["REMOTE_ADDR"]=="::1" ||
            $_SERVER["REMOTE_ADDR"]=="localhost") {
            return true;
        } else {
            return false;
        }
    }
}

function checklanguage(): bool
{
    $your_token = $_SERVER['REMOTE_ADDR'];
    if (!empty($_POST['language_selector'])) {
        $the_token = Input::get("your_token");
        if ($your_token != $the_token) {
            err("Language change failed");
            return false;
        } else {
            $count = 0;
            $set = '';
            foreach ($_POST as $k => $v) {
                $count++;

                if ($count != 3) {
                    continue;
                } else {
                    $set = substr($k, 0, -2);
                }
            }
            if (strlen($set) != 5 || (substr($set, 2, 1) != '-')) {
              //something is fishy with this language key
                err("Language change failed");
                return false;
            }
            $_SESSION['us_lang']=$set;

            header("Refresh:0");
        }
    }
    if (!isset($_SESSION['us_lang'])) {
            $_SESSION['us_lang'] = 'en-US';
    }
    return true;
}
