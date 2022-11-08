<?php
//functions that help things along. by definition functions that are so common or fundamental,
//require limited and diverse arguments, it makes no sense to turn them into classes

function getPublicObjectVars($obj)
{
    return get_object_vars($obj);
}

function PHPCanProxy()
{
    if (!empty(ini_get('open_basedir')) || ini_get('safe_mode')) {
        return false;
    }
    return true;
}

function isJsonObject($string)
{
    return is_object(json_decode($string));
}

function isJsonArray($string)
{
    return is_array(json_decode($string, true));
}

// Readeable file size
function size($path)
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

function bold($text)
{
    echo "<span><ext padding='1em' align='center'><h4><span style='background:white'>";
    echo $text;
    echo "</h4></span></text>";
}

function err($text)
{
    echo "<span><text padding='1em' align='center'><font color='red'><h4></span>";
    echo $text;
    echo "</h4></span></font></text>";
}

function redirect($location)
{
    header("Location: {$location}");
}

function rrmdir($src)
{
    $dir = opendir($src);
    while (false !== ( $file = readdir($dir))) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                rrmdir($full);
            } else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

//Displays error and success messages
if (!function_exists('resultBlock')) {
    function resultBlock($errors, $successes)
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
if (!function_exists('lang')) {
    function lang($key, $markers = null)
    {
        global $lang, $us_url_root, $abs_us_root;
        if ($markers == null) {
            if (isset($lang[$key])) {
                $str = $lang[$key];
            } else {
                $str = "";
            }
        } else {
          //Replace any dyamic markers
            if (isset($lang[$key])) {
                $str = $lang[$key];
                $iteration = 1;
                foreach ($markers as $marker) {
                    $str = str_replace("%m".$iteration."%", $marker, $str);
                    $iteration++;
                }
            } else {
                $str = "";
            }
        }


      //Ensure we have something to return
      // dump($key);
        if ($str == "") {
            if (isset($lang["MISSING_TEXT"])) {
                $missing = $lang["MISSING_TEXT"];
            } else {
                $missing = "Missing Text";
            }
          //if nothing is found, let's check to see if the language is English.
            if (isset($lang['THIS_CODE']) && $lang['THIS_CODE'] != "en-US") {
                $save = $lang['THIS_CODE'];
                if ($save == '') {
                    $save = 'en-US';
                }
            //if it is NOT English, we are going to try to grab the key from the English translation
                include("lang/en-US.php");
                if ($markers == null) {
                    if (isset($lang[$key])) {
                        $str = $lang[$key];
                    } else {
                        $str = "";
                    }
                } else {
                  //Replace any dyamic markers
                    if (isset($lang[$key])) {
                        $str = $lang[$key];
                        $iteration = 1;
                        foreach ($markers as $marker) {
                            $str = str_replace("%m".$iteration."%", $marker, $str);
                            $iteration++;
                        }
                    } else {
                        $str = "";
                    }
                }
                $lang = [];
                include("lang/$save.php");
                if ($str == "") {
                  //This means that we went to the English file and STILL did not find the language key, so...
                    $str = "{ $missing }";
                    return $str;
                } else {
                  //falling back to English
                    return $str;
                }
            } else {
              //the language is already English but the code is not found so...
                $str = "{ $missing }";
                return $str;
            }
        } else {
            return $str;
        }
    }
}


if (!function_exists('clean')) {
    //Cleaning function
    function clean($string)
    {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = preg_replace('/[^A-Za-z0-9]/', '', $string); // Removes special chars.

        return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
    }
}


if (!function_exists('encodeURIComponent')) {
    function encodeURIComponent($str)
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
        $lastId = $db->lastId();
        return $lastId;
    }
}

if (!function_exists('isLocalhost')) {
    function isLocalhost()
    {
        if ($_SERVER["REMOTE_ADDR"]=="127.0.0.1" || $_SERVER["REMOTE_ADDR"]=="::1" ||
            $_SERVER["REMOTE_ADDR"]=="localhost") {
            return true;
        } else {
            return false;
        }
    }
}

function checklanguage()
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
}

