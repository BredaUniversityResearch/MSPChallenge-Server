<?php

namespace App\Domain\Communicator;

use App\Domain\API\v1\Simulation;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Log\LogContainerInterface;
use App\Domain\Log\LogContainerTrait;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function App\await;

class WatchdogCommunicator extends AbstractCommunicator implements LogContainerInterface
{
    use LogContainerTrait;

    public function __construct(
        HttpClientInterface $client,
        // below is required by legacy to be auto-wired, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ) {
        parent::__construct($client);
    }

    /**
     * @throws Exception
     */
    public function changeState(
        int $sessionId,
        GameStateValue $newWatchdogState,
        ?int $currentMonth = null
    ): void {
        if ($_ENV['APP_ENV'] === 'test') {
            $this->log('Watchdog request was canceled as you are in test mode.');
            return;
        }
        $simulation = new Simulation();
        $simulation->setGameSessionId($sessionId);
        await($simulation->changeWatchdogState($newWatchdogState, $currentMonth));
        $this->appendFromLogContainer($simulation);
    }
}
