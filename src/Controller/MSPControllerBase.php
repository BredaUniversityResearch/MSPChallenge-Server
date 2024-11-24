<?php

namespace App\Controller;

use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;

class MSPControllerBase extends BaseController
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        SymfonyToLegacyHelper::getInstance()->setControllerForwarder(
            function (string $controller, array $path = [], array $query = []) {
                return $this->forward($controller, $path, $query);
            }
        );
    }
}
