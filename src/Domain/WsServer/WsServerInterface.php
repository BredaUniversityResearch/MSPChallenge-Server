<?php

namespace App\Domain\WsServer;

use App\Domain\WsServer\Plugins\PluginInterface;
use React\EventLoop\LoopInterface;

interface WsServerInterface
{
    public function getId(): string;
    public function setId(?string $id): self;
    public function registerLoop(LoopInterface $loop);
    public function registerPlugin(PluginInterface $plugin): self;
    public function unregisterPlugin(PluginInterface $plugin);
    public function setGameSessionIdFilter(int $gameSessionIdFilter): self;
}
