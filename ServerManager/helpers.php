<?php

namespace ServerManager;

//functions that help things along. by definition functions that are so common or fundamental,
//require limited and diverse arguments, it makes no sense to turn them into classes

use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;

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

/**
 * Inputs language strings from selected language.
 *
 * @throws Exception
 */
function lang($key): string
{
    return SymfonyToLegacyHelper::getInstance()->getTranslator()->trans($key);
}


//Cleaning function
function clean($string): array|string|null
{
    $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
    $string = preg_replace('/[^A-Za-z\d]/', '', $string); // Removes special chars.

    return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
}

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
