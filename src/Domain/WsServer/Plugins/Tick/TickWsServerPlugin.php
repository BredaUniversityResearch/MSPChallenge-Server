<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\WsServer\Plugins\Plugin;
use Closure;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function React\Promise\all;

class TickWsServerPlugin extends Plugin
{
    private const TICK_MIN_INTERVAL_SEC = 2;

    /**
     * @var GameTick[]
     */
    private array $gameTicks = [];

    public function __construct()
    {
        parent::__construct('tick', self::TICK_MIN_INTERVAL_SEC);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->tick()
                ->then(function (array $tickGameSessionIds) {
                    wdo('just finished tick for game session ids: ' . implode(', ', $tickGameSessionIds));
                });
        };
    }

    /**
     * @throws Exception
     */
    private function tick(): PromiseInterface
    {
        wdo('starting "tick"');
        return $this->getServerManager()->getGameSessionIds()
            ->then(function (Result $result) {
                $gameSessionIds = collect($result->fetchAllRows() ?? [])
                    ->keyBy('id')
                    ->map(function($row) {
                        return $row['id'];
                    });
                $gameSessionId = $this->getGameSessionId();
                if ($gameSessionId != null) {
                    $gameSessionIds = $gameSessionIds->only($gameSessionId);
                }
                $promises = [];
                foreach ($gameSessionIds as $gameSessionId) {
                    wdo('starting "tick" for game session: ' . $gameSessionId);
                    $tickTimeStart = microtime(true);
                    $promises[$gameSessionId] = $this->getGameTick($gameSessionId)->Tick(
                        !empty($_ENV['WS_SERVER_DEBUG_OUTPUT'])
                    )
                    ->then(
                        function () use ($tickTimeStart, $gameSessionId) {
                            $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                                $this->getName(),
                                $gameSessionId,
                                microtime(true) - $tickTimeStart
                            );
                            return $gameSessionId; // just to identify this tick
                        }
                    );
                }
                return all($promises);
            });
    }

    /**
     * @throws Exception
     */
    private function getGameTick(int $gameSessionId): GameTick
    {
        if (!array_key_exists($gameSessionId, $this->gameTicks)) {
            $gameTick = new GameTick();
            $gameTick->setAsync(true);
            $gameTick->setGameSessionId($gameSessionId);
            $gameTick->setAsyncDatabase($this->getServerManager()->getAsyncDatabase($gameSessionId));
            $this->gameTicks[$gameSessionId] = $gameTick;
        }
        return $this->gameTicks[$gameSessionId];
    }
}
