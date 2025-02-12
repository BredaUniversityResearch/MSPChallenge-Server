<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\WsServer\Plugins\Plugin;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\resolveOnFutureTick;
use function App\tpf;

class TickWsServerPlugin extends Plugin
{
    private int $gameSessionId;
    private ?GameTick $gameTick = null;

    public static function getDefaultMinIntervalSec(): float
    {
        return 2;
    }

    public function __construct(int $gameSessionId, ?float $minIntervalSec = null)
    {
        $this->gameSessionId = $gameSessionId;
        parent::__construct('tick' . $gameSessionId, $minIntervalSec);
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (?Context $context) {
            return $this->tick($context)
                ->then(function (int $gameSessionId) {
                    $this->addOutput(
                        'just finished tick for game session id: ' . $gameSessionId,
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                });
        });
    }

    /**
     * @throws Exception
     */
    private function tick(?Context $context): PromiseInterface
    {
        $gameSessionIdFilter = $this->getGameSessionIdFilter();
        if ($gameSessionIdFilter != null && $gameSessionIdFilter !== $this->gameSessionId) {
            // fail-safe. Should not happen since the tick plugin will be automatically unregistered from the loop
            //   by TicksHandlerWsServerPlugin. But if the tick plugin is called for a game session *not* filtered,
            //   just resolve, and do not do anything.
            return resolveOnFutureTick(new Deferred(), $this->gameSessionId)->promise();
        }

        $this->addOutput(
            'starting "tick" for game session: ' . $this->gameSessionId,
            OutputInterface::VERBOSITY_VERY_VERBOSE
        );
        return $this->getGameTick($this->gameSessionId)->tick(
            $this->isDebugOutputEnabled(),
            $context
        )
        ->then(
            function () {
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
            $gameTick
                ->setAsync(true)
                ->setGameSessionId($gameSessionId)
                ->setAsyncDatabase($this->getServerManager()->getGameSessionDbConnection($gameSessionId))
                ->setStopwatch($this->getStopwatch());
            $this->gameTick = $gameTick;
        }
        return $this->gameTick;
    }
}
