<?php
	/*
		Routes the HTTP request incl. its parameters to the correct class method, and wraps that method's results in a consistent response
	*/

	class Router {
		private const ALLOWED_CLASSES = array(
			"batch", // no need to check
			"cel", // done
			"energy", // done 
			"game", // done
			"gamesession", // done 
			"geometry", // done
			"kpi", // done
			"layer", // done
			"log", // done
			"mel", // done
			"objective", // done
			"plan", // done
			"rel", // done
			"security", // done
			"sel", // done
			"simulations", // done
			"update", // done
			"user", // done
			"warning" //done
		);

		public static function RouteApiCall(string $apiCallUrl, array $data)
		{
			$endpointData = self::ParseEndpointString($apiCallUrl);
			return self::ExecuteCall($endpointData["class"], $endpointData["method"], $data, true);
		}

		public static function ParseEndpointString(string $apiCallUrl)
		{
			$arr = explode("/", $apiCallUrl);
			$class = ucfirst($arr[0]);
			$method = ucfirst($arr[1]);
			$params = array_splice($arr, 2);
			if (count($params) > 0)
			{
				echo("Data passed via url. Everything should be passed as POST data"); 
				//Everyting should be done via POST data now. If you see the above message the endpoint needs to change.
			}
			return array("class" => $class, "method" => $method);
		}

		public static function ExecuteCall(string $className, string $method, array $data, bool $startDatabaseTransaction = true) 
		{
			//make sure the class exists is allowed to be called at all, and if not, immediately fail
			if(!self::AllowedClass($className)) {
				$success = false;
				$message = Base::ErrorString(new Exception("Invalid class."));
				return self::FormatResponse($success, $message, null, $className, $method, $data);
			}
			
			$class = new $className($method);
			$message = NULL;
			$payload = NULL;
			if(!$class->isvalid) {
				// security check failed in this instance, so class method not allowed
				$success = false;
				$message = Base::ErrorString(new Exception("Access denied (Security)."));
			}
			elseif(method_exists($class, $method)) {
				// everything ok, try to actually call the class method - wrapped in a transaction - and catch any exceptions thrown
				$arguments = self::ResolveArguments($class, $method, $data);
				try {				
					$payload = $class->$method(...$arguments);
				} catch (\Throwable $e) { 
					// execution failed, perhaps because of database connection failure or PHP warning, or because endpoint threw exception
					// PHP code parsing errors are caught in the shutdown function as defined in helpers.php
					$success = false;

					if ($startDatabaseTransaction)
					{
						$class->DBRollbackTransaction();
					}

					$message = Base::ErrorString($e);
					return self::FormatResponse($success, $message, null, $className, $method, $data);
				}
				// execution worked, payload has been set, message can remain empty
				$success = true;
			}
			else {
				// this can only mean that the class method doesn't exist, even though the class does
				$success = false;
				$message = Base::ErrorString(new Exception("Invalid method."));
			}
			return self::FormatResponse($success, $message, $payload, $className, $method, $data);
		}

		private static function ResolveArguments(object $classInstance, string $methodName, array $argumentsArray)
		{
			$classData = new ReflectionClass($classInstance);
			$className = $classData->getName();
			$methodData = $classData->getMethod($methodName);
			$parametersData = $methodData->getParameters();

			$result = [];
			$inValues = $argumentsArray;

			foreach($parametersData as $parameter)
			{
				if (isset($argumentsArray[$parameter->getName()]))
				{
					$parameterValue = $argumentsArray[$parameter->getName()];
					if ($parameter->hasType())
					{
						$parameterType = $parameter->getType();
						try 
						{
							if (gettype($parameterValue) !== $parameterType->getName() && !self::TrySafeCast($parameterValue, $parameterType->getName()))
							{
								throw new Exception($className."::".$methodName." expects argument with name \"".$parameter->getName()."\" to be of type \"".$parameterType."\", input value \"".var_export($parameterValue, true)."\" could not be converted.");
							}
						}
						catch(Throwable $e)
						{
							throw new Exception($className."::".$methodName." encountered error when resolving argument \"".$parameter->getName()."\" of type \"".$parameterType."\", input value \"".var_export($parameterValue, true)."\" raised an exception in conversion: ".$e);
						}
					}

					$result[] = $parameterValue;
					unset($inValues[$parameter->getName()]);
				}
				else if ($parameter->isDefaultValueAvailable())
				{
					$result[] = $parameter->getDefaultValue();
				}
				else
				{
					throw new Exception($className."::".$methodName." expects argument with name \"".$parameter->getName()."\". Got ".var_export($argumentsArray, true));
				}
			}

			if (count($inValues) > 0)
			{
				throw new Exception("Call to ".$className."::".$methodName." was provided the following parameters that were not taken by any argument matching this name: \"".implode(", ", array_keys($inValues))."\"");
			}

			return $result;
		}

		private static function FormatResponse(bool $success, ?string $message, $payload = null, string $className, string $method, array $data) 
		{
			if (!$success) $message .= PHP_EOL .
										"Request: ".$className . "/" . $method . PHP_EOL . 
										"Call data: " . str_replace(array("\n", "\r"), "", var_export($data, true)) . PHP_EOL . 
										"Request URI: ".$_SERVER['REQUEST_URI'];

			return array(
				"success" => $success,
				"message" => $message,
				"payload" => $payload
			);
		}

		private static function TrySafeCast(&$input, string $targetType): bool
		{
			if($targetType == "string")
			{
				$input = strval($input); 
				return true;
			}
			else if ($targetType == "array")
			{
				if (gettype($input) != "string")
				{
					throw new Exception("Target type was array, but input type was not string as expected. Input type was ".gettype($input));
				}
				$input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
				return true;
			}
			else 
			{
				$filters = array(
					"int" => FILTER_VALIDATE_INT, 
					"bool" => FILTER_VALIDATE_BOOLEAN,
					"float" => FILTER_VALIDATE_FLOAT
				);
				if (isset($filters[$targetType]))
				{
					$converted = filter_var($input, $filters[$targetType], FILTER_NULL_ON_FAILURE);
					if ($converted !== null)
					{
						$input = $converted;
						return true;
					}
					return false;
				}
				
			}
			throw new Exception("Unkonwn target type for TrySafeCast. Target: \"$targetType\"");
			return false;
		}

		private static function AllowedClass($class)
		{
			return in_array(strtolower($class), self::ALLOWED_CLASSES);
		}
	}
?>
