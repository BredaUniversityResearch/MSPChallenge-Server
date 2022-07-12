<?php

namespace App\Domain\API\v1;

use App\Domain\Common\ObjectMethod;
use App\Domain\WsServer\ClientDisconnectedException;
use Closure;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;
use function App\resolveOnFutureTick;

// Routes the HTTP request incl. its parameters to the correct class method, and wraps that method's results in a
//   consistent response
class Router
{
    public const ALLOWED_CLASSES = array(
        "batch",
        "cel",
        "energy",
        "game",
        "gamesession",
        "geometry",
        "kpi",
        "layer",
        "log",
        "mel",
        "objective",
        "plan",
        "rel",
        "security",
        "sel",
        "simulations",
        "unittestsupport",
        "update",
        "user",
        "warning"
    );

    private const TRANSACTIONS_TOGGLE = false; // should we be using transactions at all?
    private const TRANSACTIONS_MODE = "OptIn"; // or OptOut
                               // >> OptIn means some calls will request transactions (so default is no transactions)
                               // >> OptOut means some calls will request no transactions (so default is transactions)
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function RouteApiCall(string $apiCallUrl, array $data): array
    {
        $endpointData = self::parseEndpointString($apiCallUrl);
        try {
            $result = self::ExecuteCall(
                $endpointData["class"],
                $endpointData["method"],
                $data,
                self::TRANSACTIONS_TOGGLE
            );

            if (UnitTestSupport::ShouldLogApiCalls()) {
                $unitTestSupport = new UnitTestSupport();
                $unitTestSupport->RecordApiCall($endpointData["class"], $endpointData["method"], $data, $result);
            }

            return $result;
        } catch (Exception $e) {
            return self::formatResponse(
                false,
                $e->getMessage(),
                null,
                $endpointData["class"],
                $endpointData["method"],
                $data
            );
        }
    }

    public static function parseEndpointString(string $apiCallUrl): array
    {
        $arr = explode("/", $apiCallUrl);
        $class = ucfirst($arr[0]);
        $method = ucfirst($arr[1]);
        $params = array_splice($arr, 2);
        if (count($params) > 0) {
            echo ("Data passed via url. Everything should be passed as POST data");
            //Everyting should be done via POST data now. If you see the above message the endpoint needs to change.
        }
        return array("class" => $class, "method" => $method);
    }

    /**
     * @throws Exception
     */
    public static function executeCallAsync(
        ObjectMethod $objectMethod,
        array $data,
        ?Closure $preExecuteCallback = null,
        ?Closure $postExecuteCallback = null
    ): PromiseInterface {
        $class = $objectMethod->getInstance();
        $className = (new ReflectionClass($class))->getShortName();
        $method = $objectMethod->getMethod();

        /** @var Base $class */
        if (!$class->isValid()) {
            throw new ClientDisconnectedException('Access denied (Security)');
        }

        if (null !== $preExecuteCallback) {
            $preExecuteCallback($data);
        }

        $classData = new ReflectionClass($class);
        $methodData = $classData->getMethod($method);

        // everything ok, try to actually call the class method and catch any exceptions thrown
        $arguments = self::resolveArguments($classData, $methodData, $data);

        try {
            $promise = $class->$method(...$arguments);
        } catch (Throwable $e) {
            // execution failed, perhaps because of database connection failure or PHP warning, or because
            //   endpoint threw exception
            // PHP code parsing errors are caught in the shutdown function as defined in helpers.php
            $message = Base::ErrorString($e);
            $response = self::formatResponse(false, $message, null, $className, $method, $data);
            return resolveOnFutureTick(new Deferred(), $response)->promise();
        }

        if (!($promise instanceof PromiseInterface)) {
            throw new Exception('This method is not asynchronous: ' . $className . ':' . $method);
        }

        return $promise
            ->then(function ($payload) use ($className, $method, $data, $postExecuteCallback) {
                if (null !== $postExecuteCallback) {
                    $postExecuteCallback($payload);
                }
                return $payload;
            });
    }

    /**
     * @throws Exception
     */
    public static function createObjectMethodFromEndpoint(string $endpoint): ObjectMethod
    {
        $endpointData = self::ParseEndpointString($endpoint);
        return new ObjectMethod(
            self::createObjectFrom($endpointData['class'], $endpointData['method']),
            $endpointData['method']
        );
    }

    /**
     * @throws Exception
     */
    private static function createObjectFrom(string $className, string $method): object
    {
        //make sure the class exists is allowed to be called at all, and if not, immediately fail
        if (!self::AllowedClass($className)) {
            throw new Exception('Invalid class: '. $className);
        }

        try {
            // since the Router class itself is part of api version, we can just use the Router's class name spaced name
            //   and replace the last name part
            $fullClassName = str_replace('Router', $className, self::class);
            $class = new $fullClassName($method);
        } catch (\RuntimeException $e) {
            // attempt class name with all upper case letters
            $fullClassName = str_replace('Router', strtoupper($className), self::class);
            $class = new $fullClassName($method);
        }

        return $class;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ExecuteCall(
        string $className,
        string $method,
        array $data,
        bool $startDatabaseTransaction = true
    ): array {
        try {
            $class = self::createObjectFrom($className, $method);
        } catch (Exception $e) {
            $message = Base::ErrorString(new Exception('Invalid class.'));
            return self::FormatResponse(false, $message, null, $className, $method, $data);
        }
        $message = null;
        $payload = null;
        if (!$class->isValid()) {
            // security check failed in this instance, so class method not allowed
            $success = false;
            $message = Base::ErrorString(new Exception("Access denied (Security)."));
        } elseif (method_exists($class, $method)) {
            $classData = new ReflectionClass($class);
            $methodData = $classData->getMethod($method);

            // everything ok, try to actually call the class method - wrapped in a transaction - and catch any
            //   exceptions thrown
            $arguments = self::resolveArguments($classData, $methodData, $data);
            $CallWantsTransaction = self::CheckMethodCommentsForTransactionOptions($methodData);
            try {
                if ($CallWantsTransaction && $startDatabaseTransaction) {
                    Database::GetInstance()->DBStartTransaction();
                }

                $payload = $class->$method(...$arguments);

                if ($CallWantsTransaction && $startDatabaseTransaction) {
                    Database::GetInstance()->DBCommitTransaction();
                }
            } catch (Throwable $e) {
                // execution failed, perhaps because of database connection failure or PHP warning, or because
                //   endpoint threw exception
                // PHP code parsing errors are caught in the shutdown function as defined in helpers.php
                $success = false;

                if ($CallWantsTransaction && $startDatabaseTransaction) {
                    Database::GetInstance()->DBRollbackTransaction();
                }

                $message = Base::ErrorString($e);
                return self::formatResponse($success, $message, null, $className, $method, $data);
            }
            // execution worked, payload has been set, message can remain empty
            $success = true;
        } else {
            // this can only mean that the class method doesn't exist, even though the class does
            $success = false;
            $message = Base::ErrorString(new Exception("Invalid method."));
        }
        return self::formatResponse($success, $message, $payload, $className, $method, $data);
    }

    /**
     * @throws Exception
     */
    private static function resolveArguments(
        ReflectionClass $classData,
        ReflectionMethod $methodData,
        array $argumentsArray
    ): array {
        $className = $classData->getName();
        $parametersData = $methodData->getParameters();

        $result = [];
        $inValues = $argumentsArray;

        foreach ($parametersData as $parameter) {
            if (isset($argumentsArray[$parameter->getName()])) {
                $parameterValue = $argumentsArray[$parameter->getName()];
                if ($parameter->hasType()) {
                    $parameterType = $parameter->getType();
                    $parameterTypeName = $parameterType->getName();
                    try {
                        if (gettype($parameterValue) !== $parameterTypeName &&
                            !self::TrySafeCast($parameterValue, $parameterTypeName)
                        ) {
                            throw new Exception(
                                $className . "::" . $methodData->getName() .
                                " expects argument with name \"" . $parameter->getName() .
                                "\" to be of type \"" . $parameterTypeName . "\", input value \"" .
                                var_export($parameterValue, true) . "\" could not be converted."
                            );
                        }
                    } catch (Throwable $e) {
                        throw new Exception(
                            $className . "::" . $methodData->getName() .
                            " encountered error when resolving argument \"" . $parameter->getName() .
                            "\" of type \"" . $parameterTypeName . "\", input value \"" .
                            var_export($parameterValue, true) . "\" raised an exception in conversion: " . $e
                        );
                    }
                }

                $result[] = $parameterValue;
                unset($inValues[$parameter->getName()]);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $result[] = $parameter->getDefaultValue();
            } else {
                throw new Exception(
                    $className . "::" . $methodData->getName() .
                    " expects argument with name \"" . $parameter->getName() . "\". Got " .
                    var_export($argumentsArray, true)
                );
            }
        }

        if (count($inValues) > 0) {
            throw new Exception(
                "Call to " . $className . "::" . $methodData->getName() .
                " was provided the following parameters that were not taken by any argument matching this name: \"" .
                implode(", ", array_keys($inValues)) . "\""
            );
        }

        return $result;
    }

    /**
     * @param bool $success
     * @param string|null $message
     * @param mixed|null $payload
     * @param string $className
     * @param string $method
     * @param array $data
     * @return array
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function formatResponse(
        bool $success,
        ?string $message,
        $payload = null,
        string $className = '',
        string $method = '',
        array $data = []
    ): array {
        if (!$success) {
            $message .= PHP_EOL .
            "Request: " . $className . "/" . $method . PHP_EOL .
            "Call data: " . str_replace(array("\n", "\r"), "", var_export($data, true)) . PHP_EOL .
            "Request URI: " . $_SERVER['REQUEST_URI'];
        }

        return array(
            // no need for header information from api, only needed for websocket server communication.
            "header_type" => null,
            "header_data" => null,
            "success" => $success,
            "message" => $message,
            "payload" => $payload
        );
    }

    /**
     * @param mixed $input
     * @param string $targetType
     * @return bool
     * @throws Exception
     */
    private static function trySafeCast(&$input, string $targetType): bool
    {
        if ($targetType == "string") {
            $input = strval($input);
            return true;
        } elseif ($targetType == "array") {
            if (gettype($input) != "string") {
                throw new Exception(
                    "Target type was array, but input type was not string as expected. Input type was " .
                    gettype($input)
                );
            }
            $input = json_decode($input, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                throw new Exception("Failed to decode json. Error: ".json_last_error_msg());
            }
            return true;
        } else {
            $filters = array(
                "int" => FILTER_VALIDATE_INT,
                "bool" => FILTER_VALIDATE_BOOLEAN,
                "float" => FILTER_VALIDATE_FLOAT
            );
            if (isset($filters[$targetType])) {
                $converted = filter_var($input, $filters[$targetType], FILTER_NULL_ON_FAILURE);
                if ($converted !== null) {
                    $input = $converted;
                    return true;
                }
                return false;
            }
        }
        throw new Exception("Unknown target type for TrySafeCast. Target: \"$targetType\"");
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function AllowedClass(string $class): bool
    {
        return in_array(strtolower($class), self::ALLOWED_CLASSES);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private static function CheckMethodCommentsForTransactionOptions(ReflectionMethod $methodData): bool
    {
        $comment = $methodData->getDocComment();
        if (self::TRANSACTIONS_MODE == "OptOut") {
            return stristr($comment, "@ForceNoTransaction") === false;
        } elseif (self::TRANSACTIONS_MODE == "OptIn") {
            return stristr($comment, "@ForceTransaction") !== false;
        }
        return false;
    }
}
