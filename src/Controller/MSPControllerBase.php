<?php

namespace App\Controller;

use App\Domain\Helper\SymfonyToLegacyHelper;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MSPControllerBase extends AbstractController
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
