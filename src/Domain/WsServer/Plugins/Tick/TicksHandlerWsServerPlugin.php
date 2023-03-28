<?php

namespace App\Domain\WsServer\Plugins\Tick;

use App\Domain\Common\ToPromiseFunction;
use App\Domain\WsServer\Plugins\Plugin;
use Drift\DBAL\Result;
use function App\tpf;

class TicksHandlerWsServerPlugin extends Plugin
{
    /**
     * @var TickWsServerPlugin[]
     */
    private array $tickPlugins = [];

    public static function getDefaultMinIntervalSec(): float
    {
        return 1;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('ticks handler', $minIntervalSec);
    }

    protected function onCreatePromiseFunction(): ToPromiseFunction
    {
        return tpf(function () {
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
        });
    }
}
