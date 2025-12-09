<?php

namespace App\MessageHandler\GameList;

use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\ServerManager\GameList;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListArchiveMessage;
use App\VersionsProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

#[AsMessageHandler]
class GameListArchiveMessageHandler extends CommonSessionHandler
{
    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator,
        VersionsProvider $provider,
        SimulationHelper $simulationHelper
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws \Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function __invoke(GameListArchiveMessage $gameList): void
    {
        $this->setGameSessionAndDatabase($gameList);
        $this->database = $this->connectionManager->getGameSessionDbName($gameList->id);
        $this->entityManager = $this->connectionManager->getGameSessionEntityManager($gameList->id);
        $this->gameSession = new GameList($gameList->id);
        $this->watchdogCommunicator->changeState(
            $this->gameSession->getId(),
            new GameStateValue('end'),
            $this->gameSession->getGameCurrentMonth()
        );
        $this->removeSessionRasterStore();
        (new Filesystem())->remove($this->params->get('app.session_config_dir').
            sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId()));
        $this->dropSessionDatabase();
    }
}
