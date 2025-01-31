<?php

namespace App\DataCollector;

use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SimulationHelper;
use App\Entity\Game;
use App\Entity\ServerManager\GameList;
use App\Entity\Watchdog;
use App\Repository\GameRepository;
use App\Repository\WatchdogRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MSPDataCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        try {
            $serverManager = \ServerManager\ServerManager::getInstance();
        } catch (Exception $e) {
            return;
        }
        $address = $serverManager->GetTranslatedServerURL();
        if ($address == "localhost") {
            $address .= PHP_EOL . "Translated automatically to ".gethostbyname(gethostname());
        }

        $this->data = [
            'MSP Challenge Server version' => $serverManager->GetCurrentVersion(),
            'Server Address' => $address,
            // e.g.:
            //'currentToken' => \ServerManager\Session::get('currentToken'),
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @throws Exception
     */
    public function startSimulations(
        int $session,
        ConnectionManager $connectionManager,
        WatchdogCommunicator $watchdogCommunicator,
        SimulationHelper $simulationHelper
    ): Response {
        try {
            $gameListRepo = $connectionManager->getServerManagerEntityManager()
                ->getRepository(GameList::class);
            $gameList = $gameListRepo->find($session);
            $em = ConnectionManager::getInstance()->getGameSessionEntityManager($session);
            /** @var GameRepository $gameRepo */
            $gameRepo = $em->getRepository(Game::class);
            $game = $gameRepo->retrieve();

            if ($game->getGameState() == GameStateValue::SETUP) {
                // re-register simulations
                /** @var WatchdogRepository $watchdogRepo */
                $watchdogRepo = $em->getRepository(Watchdog::class);
                $watchdogRepo->registerSimulations($simulationHelper->getInternalSims($session));
            }

            $watchdogCommunicator->changeState($session, $game->getGameState(), $gameList->getGameCurrentMonth());
        } catch (Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new Response('Simulations started requested', Response::HTTP_OK);
    }
}
