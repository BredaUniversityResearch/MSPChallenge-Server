<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Predis\Client;
use Symfony\Component\Console\Input\InputOption;

class RedisItemsCommand extends Command
{
    protected static $defaultName = 'app:redis-items';

    protected function configure()
    {
        $this
            ->setDescription('Retrieve all items from Redis')
            ->setHelp('This command retrieves all items from Redis')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Get the value of a specific key');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getOption('key');

        // Connect to Redis server
        $redis = new Client([
            'scheme' => 'tcp',
            'host'   => 'redis',
            'port'   => 6379,
        ]);

        // If a key is provided, get the value of that key
        if ($key !== null) {
            $value = $redis->get($key);
            if ($value === null) {
                $output->writeln("Key '$key' not found in Redis.");
            } else {
                $ttl = $redis->ttl($key);
                $output->writeln('* Value:'.PHP_EOL.$value);
                if ($ttl === -2) {
                    $output->writeln("The key '$key' does not have an expire.");
                } elseif ($ttl === -1) {
                    $output->writeln("The key '$key' does not expire.");
                } else {
                    $output->writeln("* Time to live (TTL): $ttl seconds");
                }
            }
            return Command::SUCCESS;
        }

        // Get all keys from Redis
        $keys = $redis->keys('*');

        if (empty($keys)) {
            $output->writeln("No items found in Redis.");
        } else {
            $output->writeln("Items in Redis:");
            foreach ($keys as $key) {
                $output->writeln($key);
            }
        }

        return Command::SUCCESS;
    }
}
