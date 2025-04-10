<?php

namespace App\Repository\ServerManager;

use App\Domain\Services\ConnectionManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Exception;

class ServerEntityRepository extends EntityRepository
{
    /**
     * @throws Exception
     */
    protected function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = ConnectionManager::getInstance()->getServerManagerEntityManager();
        return $em;
    }
}
