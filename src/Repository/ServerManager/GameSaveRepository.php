<?php

namespace App\Repository\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Domain\Common\NormalizerContextBuilder;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameSave;
use App\Entity\ServerManager\GameServer;
use App\Entity\ServerManager\GameWatchdogServer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class GameSaveRepository extends EntityRepository
{
    private ?ObjectNormalizer $normalizer = null; // to be created upon usage

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

    /**
     * @throws ExceptionInterface|ReflectionException
     */
    public function createGameSaveFromData(array $gameSaveData)
    {
        $this->normalizer ??= new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        return $this->normalizer->denormalize(
            $gameSaveData,
            GameSave::class,
            null,
            (new NormalizerContextBuilder(GameSave::class))->withCallbacks([
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
            ])->toArray()
        );
    }

    /**
     * @throws ExceptionInterface|ReflectionException
     */
    public function createDataFromGameSave(GameSave $gameSave)
    {
        $this->normalizer ??= new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        return $this->normalizer->normalize(
            $gameSave,
            null,
            (new NormalizerContextBuilder(GameSave::class))->withCallbacks([
                'sessionState' => fn($innerObject) => ((string) $innerObject),
                'gameState' => fn($innerObject) => ((string) $innerObject),
                'gameVisibility' => fn($innerObject) => ((string) $innerObject)
            ])->toArray()
        );
    }
}
