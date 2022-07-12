<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\WsServer\Plugins\Plugin;
use Closure;
use Drift\DBAL\Result;

class TicksHandlerWsServerPlugin extends Plugin
{
    private const TICKS_HANDLER_MIN_INTERVAL_SEC = 1;

    /**
     * @var TickWsServerPlugin[]
     */
    private array $tickPlugins = [];

    public function __construct()
    {
        parent::__construct('ticksHandler', self::TICKS_HANDLER_MIN_INTERVAL_SEC);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->getServerManager()->getGameSessionIds(true)
                ->then(function (Result $result) {
                    $gameSessionIds = collect($result->fetchAllRows() ?? [])
                        ->keyBy('id')
                        ->map(function ($row) {
                            return $row['id'];
                        });
                    $gameSessionId = $this->getGameSessionIdFilter();
                    if ($gameSessionId != null) {
                        $gameSessionIds = $gameSessionIds->only($gameSessionId);
                    }
                    $gameSessionIds = $gameSessionIds->all(); // to raw array
                    // first unregister all tick plugins not required anymore
                    $gameSessionIdsToUnregister = array_diff(array_keys($this->tickPlugins), $gameSessionIds);
                    foreach ($gameSessionIdsToUnregister as $gameSessionId) {
                        $this->getWsServer()->unregisterPlugin($this->tickPlugins[$gameSessionId]);
                        unset($this->tickPlugins[$gameSessionId]);
                    }
                    // register all new tick plugins
                    $gameSessionIdsToRegister = array_diff($gameSessionIds, array_keys($this->tickPlugins));
                    foreach ($gameSessionIdsToRegister as $gameSessionId) {
                        $tickPlugin = new TickWsServerPlugin($gameSessionId);
                        $this->getWsServer()->registerPlugin($tickPlugin);
                        $this->tickPlugins[$gameSessionId] = $tickPlugin;
                    }
                });
        };
    }
}
