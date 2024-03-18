<?php

namespace App\Repository\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class GameSaveRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    public function save(GameSave $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameSave $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function defaultDenormalizeContext(): array
    {
        // @todo (HW): member names via reflection
        return [
            AbstractNormalizer::CALLBACKS => [
                'gameConfigVersion' => fn($innerObject) => $this->getEntityManager()->getRepository(
                    GameConfigVersion::class
                )->find($innerObject['id']),
                'gameServer' => fn($innerObject) => $this->getEntityManager()->getRepository(
                    GameServer::class
                )->find($innerObject['id']),
                'gameWatchdogServer' => fn($innerObject) => $this->getEntityManager()->getRepository(
                    GameWatchdogServer::class
                )->find($innerObject['id']),
                'sessionState' => fn($innerObject) => new GameSessionStateValue($innerObject),
                'gameState' => fn($innerObject) => new GameStateValue($innerObject),
                'gameVisibility' => fn($innerObject) => new GameVisibilityValue($innerObject)
            ],
        ];
    }

    public static function defaultNormalizeContext(): array
    {
        // @todo (HW): member names via reflection
        return [
            AbstractNormalizer::CALLBACKS => [
                'sessionState' => fn($innerObject) => ((string) $innerObject),
                'gameState' => fn($innerObject) => ((string) $innerObject),
                'gameVisibility' => fn($innerObject) => ((string) $innerObject)
            ]
        ];
    }
}
