<?php

namespace App\Domain\WsServer;

use App\Domain\WsServer\Plugins\PluginInterface;
use React\EventLoop\LoopInterface;

interface WsServerInterface
{
    public function registerLoop(LoopInterface $loop);
    public function registerPlugin(PluginInterface $plugin): self;
    public function unregisterPlugin(PluginInterface $plugin);
    public function setGameSessionIdFilter(int $gameSessionIdFilter): self;
}
