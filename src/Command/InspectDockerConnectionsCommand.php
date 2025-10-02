<?php
namespace App\Command;

use App\Message\Docker\InspectDockerConnectionsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:inspect-docker-connections',
    description: 'Dispatch a "Inspect Docker connections"-message to the message bus',
)]
class InspectDockerConnectionsCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = new InspectDockerConnectionsMessage();
        $this->bus->dispatch($message);
        $output->writeln('Message dispatched!');
        return Command::SUCCESS;
    }
}