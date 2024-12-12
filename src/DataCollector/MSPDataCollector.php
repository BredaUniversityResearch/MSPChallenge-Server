<?php

namespace App\DataCollector;

use App\Domain\API\v1\Simulations;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function App\await;

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
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function startSimulations(
        int $session,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper
    ): Response {
        $qb = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($session)->createQueryBuilder();
        try {
            $gameState = $qb->select('game_state')->from('game')->fetchOne();
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $simulations = new Simulations();
        $simulations->setGameSessionId($session);
        if (null === $promise = $simulations->changeWatchdogState($gameState)) {
            return new Response('Could not change watchdog state', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $arrResult = await($promise);
        return new Response(json_encode($arrResult));
    }
}
