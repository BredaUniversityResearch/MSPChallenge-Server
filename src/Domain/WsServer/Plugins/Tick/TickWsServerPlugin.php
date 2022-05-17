<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\WsServer\Plugins\Plugin;
use Closure;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\resolveOnFutureTick;

class TickWsServerPlugin extends Plugin
{
    private const TICK_MIN_INTERVAL_SEC = 2;

    private int $gameSessionId;
    private ?GameTick $gameTick = null;

    public function __construct(int $gameSessionId)
    {
        $this->gameSessionId = $gameSessionId;
        parent::__construct('tick' . $gameSessionId, self::TICK_MIN_INTERVAL_SEC);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->tick()
                ->then(function (int $gameSessionId) {
                    $this->addDebugOutput(
                        'just finished tick for game session id: ' . $gameSessionId
                    );
                });
        };
    }

    /**
     * @throws Exception
     */
    private function tick(): PromiseInterface
    {
        // fail-safe. Should not happen since the tick plugin will be automatically unregistered from the loop,
        //   once the game session is not active
        $gameSessionIdFilter = $this->getGameSessionIdFilter();
        if ($gameSessionIdFilter != null && $gameSessionIdFilter !== $this->gameSessionId) {
            // do not do anything.
            return resolveOnFutureTick(new Deferred(), $gameSessionIdFilter)->promise();
        }

        $this->addDebugOutput('starting "tick" for game session: ' . $this->gameSessionId);
        $tickTimeStart = microtime(true);
        return $this->getGameTick($this->gameSessionId)->Tick(
            $this->isDebugOutputEnabled()
        )
        ->then(
            function () use ($tickTimeStart) {
                $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                    $this->getName(),
                    $this->gameSessionId,
                    microtime(true) - $tickTimeStart
                );
                return $this->gameSessionId; // just to identify this tick
            }
        );
    }

    /**
     * @throws Exception
     */
    private function getGameTick(int $gameSessionId): GameTick
    {
        if ($this->gameTick === null) {
            $gameTick = new GameTick();
            $gameTick->setAsync(true);
            $gameTick->setGameSessionId($gameSessionId);
            $gameTick->setAsyncDatabase($this->getServerManager()->getGameSessionDbConnection($gameSessionId));
            $this->gameTick = $gameTick;
        }
        return $this->gameTick;
    }
}
