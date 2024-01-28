<?php

namespace App\Controller\SessionAPI;

use App\Domain\Services\ConnectionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{

    public function getSessionEntityManager($sessionId): EntityManagerInterface
    {
        $database = ConnectionManager::getInstance()->getGameSessionDbName($sessionId);
        return $this->container->get("doctrine.orm.{$this->database}_entity_manager");
    }
    public static function wrapPayloadForResponse(array $payload, ?string $message = null): array
    {
        return [
            'header_type' => '',
            'header_data' => '',
            'success' => is_null($message),
            'message' => $message,
            'payload' => $payload
        ];
    }
}
