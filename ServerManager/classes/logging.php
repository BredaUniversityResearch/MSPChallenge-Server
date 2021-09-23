<?php

class Logging
{
	public static function LogError($message)
	{
		echo $message;
	}

	public static function Verbose($message)
	{
		if (is_array($message))
		{
			print_r($message);
			print("\n");
		}
		else
		{
			print($message."\n");
		}
	}
}

?>
