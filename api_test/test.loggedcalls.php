<?php

use App\Domain\API\APIHelper;
use App\Domain\API\v1\UnitTestSupport;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class TestLoggedCalls extends TestBase
{
    public function __construct(string $token)
    {
        parent::__construct($token);
    }

    /**
     * @TestMethod
     */
    public function RunLoggedCalls() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $apiHelper = APIHelper::getInstance();
        $targetFolder = $apiHelper->GetBaseFolder().UnitTestSupport::GetIntermediateFolder(
            $apiHelper->getGameSessionIdForCurrentRequest()
        );

        $targetSubtaskMethod = new ReflectionMethod($this, "RunStoredRequest");
        $targetSubtaskMethod->setAccessible(true);

        $fileIterator = new DirectoryIterator($targetFolder);
        foreach ($fileIterator as $fileInfo) {
            if (!$fileInfo->isDot()) {
                $this->RunSubTask($targetSubtaskMethod, $fileInfo->getFilename(), array($fileInfo->getPathname()));
            }
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    protected function RunStoredRequest(string $targetFilePath)
    {
        $data = json_decode(file_get_contents($targetFilePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode file payload of ".$targetFilePath.". Error: ".json_last_error_msg());
        }

        if (!isset($data["call_class"]) || !isset($data["call_method"]) || !isset($data["call_data"])) {
            return;
        }
        
        try {
            $this->DoApiRequest("/api/".$data["call_class"]."/".$data["call_method"], $data["call_data"]);
        } catch (Exception $ex) {
            if ($data["result"]["success"] == true) {
                throw $ex;
            }
        }
    }
}
