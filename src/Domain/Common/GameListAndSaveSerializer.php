<?php

namespace App\Domain\Common;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\EntityEnums\GameVisibilityValue;
use App\Entity\ServerManager\GameConfigVersion;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class GameListAndSaveSerializer
{
    private ?ObjectNormalizer $normalizer = null;
    private ?Serializer $serializer = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $this->serializer = new Serializer([$this->normalizer], [new JsonEncoder()]);
    }

    /**
     * @throws ReflectionException
     */
    public function createJsonFromGameSave(GameSave $gameSave): string
    {
        $gameSave->encodePasswords();
        return $this->serializer->serialize(
            $gameSave,
            'json',
            (new NormalizerContextBuilder(GameSave::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGenericNormalizeCallbacks())
                ->toArray()
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws ReflectionException
     */
    public function createDataFromGameList(GameList $gameList): array
    {
        return $this->serializer->normalize(
            $gameList,
            null,
            (new NormalizerContextBuilder(GameList::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGameListNormalizeCallbacks())
                ->toArray()
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws ReflectionException
     */
    public function createDataFromGameSave(GameSave $gameSave): array
    {
        return $this->serializer->normalize(
            $gameSave,
            null,
            (new NormalizerContextBuilder(GameSave::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGenericNormalizeCallbacks())
                ->toArray()
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function createGameSaveFromJson(string $json): GameSave
    {
        return $this->serializer->deserialize(
            $json,
            GameSave::class,
            'json',
            (new NormalizerContextBuilder(GameSave::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGameListJsonDenormalizeCallbacks())
                ->toArray()
        );
    }

    /**
     * @param array $data being a normalization of a GameList or GameSave object
     *
     * @return GameList
     * @throws ReflectionException
     * @throws Exception
     */
    public function createGameListFromData(array $data): GameList
    {
        return $this->serializer->denormalize(
            $data,
            GameList::class,
            null,
            (new NormalizerContextBuilder(GameList::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGameListDenormalizeCallbacks())
                ->toArray()
        );
    }

    /**
     * @param array $data being a normalization of a GameSave or GameList object
     *
     * @return GameSave
     * @throws ReflectionException
     * @throws Exception
     */
    public function createGameSaveFromData(array $data): GameSave
    {
        return $this->serializer->denormalize(
            $data,
            GameSave::class,
            null,
            (new NormalizerContextBuilder(GameSave::class))
                ->withIgnoredAttributes($this->getGenericIgnoredAttributes())
                ->withCallbacks($this->getGenericDenormalizeCallbacks())
                ->toArray()
        );
    }

    private function getGenericIgnoredAttributes(): array
    {
        return [
            'gameCreationTimePretty', 'gameEndMonthPretty', 'gameCurrentMonthPretty', 'gameRunningTilTimePretty',
            'gameGeoServer', 'gameServer', 'gameWatchdogServer', 'runningGame', 'countries',
            'saveType', 'saveNotes', 'saveVisibility', 'saveTimestampPretty', 'saveTimestamp'
        ];
    }

    private function getGenericNormalizeCallbacks(): array
    {
        return [
            'gameConfigVersion' => fn($innerObject) => (!is_null($innerObject)) ? $innerObject->getId() : null,
            'sessionState' => fn($innerObject) => ((string) $innerObject),
            'gameState' => fn($innerObject) => ((string) $innerObject),
            'gameVisibility' => fn($innerObject) => ((string) $innerObject)
        ];
    }

    /**
     * @throws Exception
     */
    private function getGenericDenormalizeCallbacks(): array
    {
        return [
            'gameConfigVersion' => fn($innerObject) => (!is_null($innerObject)) ? $this->em->getRepository(
                GameConfigVersion::class
            )->find($innerObject) : null,
            'sessionState' => fn($innerObject) => new GameSessionStateValue($innerObject),
            'gameState' => fn($innerObject) => new GameStateValue($innerObject),
            'gameVisibility' => fn($innerObject) => new GameVisibilityValue($innerObject)
        ];
    }

    /**
     * @throws Exception
     */
    private function getGameListDenormalizeCallbacks(): array
    {
        $callbacks = $this->getGenericDenormalizeCallbacks();
        $callbacks['gameSave'] = fn($innerObject) => (!is_null($innerObject)) ? $this->em->getRepository(
            GameSave::class
        )->find($innerObject) : null;
        return $callbacks;
    }

    private function getGameListNormalizeCallbacks(): array
    {
        $callbacks = $this->getGenericNormalizeCallbacks();
        $callbacks['gameSave'] = fn($innerObject) => (!is_null($innerObject)) ? $innerObject->getId() : null;
        return $callbacks;
    }

    /**
     * @throws Exception
     */
    private function getGameListJsonDenormalizeCallbacks(): array
    {
        $callbacks = $this->getGenericDenormalizeCallbacks();
        $callbacks['passwordAdmin'] = fn($innerObject) => base64_decode($innerObject);
        $callbacks['passwordPlayer'] = fn($innerObject) => base64_decode($innerObject);
        return $callbacks;
    }
}
