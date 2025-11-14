<?php

namespace App\Command;

use App\Domain\ApmListener\ApmListener;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:apm-listener',
    description: 'Start the APM listener. This is a background process that listens for APM events and sends them any '.
        'connected web socket clients.'
)]
class ApmListenerCommand extends Command
{
    const OPTION_TCP_PORT = 'tcp_port';
    const OPTION_WS_PORT = 'ws_port';

    protected static $defaultName = 'app:apm-listener';

    protected function configure(): void
    {
        $this
            ->addOption(
                self::OPTION_TCP_PORT,
                'p',
                InputOption::VALUE_REQUIRED,
                '(p)ort: the listening TCP port for incoming APM events',
                45100
            )
            ->addOption(
                self::OPTION_WS_PORT,
                'w',
                InputOption::VALUE_REQUIRED,
                '(w)ebsocket server port to which clients can connect to receive APM events',
                45101
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $listener = new ApmListener(
            (int)$input->getOption(self::OPTION_TCP_PORT),
            (int)$input->getOption(self::OPTION_WS_PORT)
        );
        $listener->run();
        return Command::SUCCESS;
    }
}
