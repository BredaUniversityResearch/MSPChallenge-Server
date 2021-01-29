<?php

/* Ensure we have a backdoor through security */
require_once("../api_config.php");
require_once("test.assert.php");
require_once("test.base.php");
require_once("test.batch.php");
require_once("test.cel.php");
require_once("test.recreate.php");

/* unit tests to make sure the API responds correctly.*/

header("Content-type: text/plain");

$sessionIdMatches = array();
preg_match("/\/([0-9]+)\//", TestBase::TARGET_SERVER_BASE_URL, $sessionIdMatches);
$targetSessionId = $sessionIdMatches[1][0];

$tokenId = 1337;
try
{
	$pdo = new PDO("mysql:dbname=msp_session_".$targetSessionId.";host=127.0.0.1", "root", "", array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	$tokenId = rand(0, PHP_INT_MAX);
	$token = $pdo->prepare("INSERT INTO api_token (api_token_token, api_token_valid_until, api_token_scope) VALUES(?, 0, ?);")->execute(array($tokenId, 0x7FFFFFFF));
}
catch (PDOException $e)
{
	print("PDO threw exception when trying to inject up API token. Token might not be valid, and tests might fail because of this.".PHP_EOL."Exception: ".$e->getMessage().PHP_EOL);
}

$testClasses = [
//	new TestBatch($tokenId),
//	new TestCEL($tokenId),
	new TestRecreate($tokenId)
];
foreach($testClasses as $test)
{ 
	$test->RunAll();
}

?>