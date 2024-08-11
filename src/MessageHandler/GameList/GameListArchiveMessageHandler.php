<?php

namespace App\MessageHandler\GameList;

use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Logger\GameSessionLogger;
use App\Message\GameList\GameListArchiveMessage;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
class GameListArchiveMessageHandler extends CommonSessionHandler
{
    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $gameSessionLogger,
        EntityManagerInterface $mspServerManagerEntityManager,
        ConnectionManager $connectionManager,
        ContainerBagInterface $params,
        GameSessionLogger $gameSessionLogFileHandler,
        WatchdogCommunicator $watchdogCommunicator
    ) {
        parent::__construct(...func_get_args());
    }

    /**
     * @throws \Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(GameListArchiveMessage $gameList): void
    {
        $this->setGameSessionAndDatabase($gameList);
        $this->watchdogCommunicator->changeState($this->gameSession, new GameStateValue('end'));
        $this->gameSessionLogFileHandler->empty($this->gameSession->getId());
        $this->info('Session archived.');
        $this->removeSessionRasterStore();
        (new Filesystem())->remove($this->params->get('app.session_config_dir').
            sprintf($this->params->get('app.session_config_name'), $this->gameSession->getId()));
        $this->dropSessionDatabase();
    }
}
