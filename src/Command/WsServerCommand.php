<?php

namespace App\Command;

use App\Domain\WsServer\WsServer;
use App\Domain\WsServer\WsServerConsoleHelper;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WsServerCommand extends Command {
    protected static $defaultName = 'app:ws-server';

    private WsServer $wsServer;

    public function __construct(WsServer $wsServer)
    {
        $this->wsServer = $wsServer;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Run the websocket server for MSP Challenge');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // the console helper will handle the console output using events dispatched by the wsServer
        $consoleHelper = new WsServerConsoleHelper($this->wsServer, $output);

        $server = IoServer::factory(new HttpServer(new \Ratchet\WebSocket\WsServer($this->wsServer)), 8080);
        $this->wsServer->registerLoop($server->loop);
        $server->run();
        return Command::SUCCESS;
    }
}
