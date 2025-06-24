<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\WsServer\ClientConnectionResourceManagerInterface;
use Closure;
use Exception;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\assertFulfilled;

class PluginHelper
{
    private static ?PluginHelper $instance = null;
    private int $nextDumpNo = 1;
    private string $dumpDir;

    public function __construct(
        private readonly string $projectDir,
        private readonly ClientConnectionResourceManagerInterface $clientConnectionResourceManager
    ) {
        // constructor will be called by Symfony services mechanism.
        self::$instance = $this;
        $this->dumpDir = $projectDir . '\\var\\log\\dump\\' . date('YmdHis') . '\\';
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new Exception(__CLASS__  . ' instance has not been initialised');
        }
        return self::$instance;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function dump(int $connResourceId, mixed &$data): void
    {
        $clientInfo = $this->clientConnectionResourceManager->getClientInfo($connResourceId);
        $clientInfo['conn_resource_id'] = $connResourceId;
        $data['debug']['client_info'] = $clientInfo;
        if (array_key_exists('WS_SERVER_PAYLOAD_DUMP', $_ENV) && $_ENV['WS_SERVER_PAYLOAD_DUMP']) {
            @mkdir($this->dumpDir, 0777, true);
            file_put_contents(
                $this->dumpDir . date('YmdHis') . 'payload' . ($this->nextDumpNo++) . '.log',
                json_encode($data, JSON_PRETTY_PRINT)
            );
        }
        // remove any debug info not matching client needs after dump
        unset($data['debug']);
    }

    public function createRepeatedFunction(
        PluginInterface $plugin,
        LoopInterface $loop,
        Closure $promiseFunction
    ): Closure {
        return function () use ($plugin, $loop, $promiseFunction) {
            $startTime = microtime(true);
            assertFulfilled(
                $promiseFunction(),
                $this->createRepeatedOnFulfilledFunction(
                    $plugin,
                    $loop,
                    $startTime,
                    $this->createRepeatedFunction($plugin, $loop, $promiseFunction)
                )
            );
        };
    }

    private function createRepeatedOnFulfilledFunction(
        PluginInterface $plugin,
        LoopInterface $loop,
        float $startTime,
        Closure $repeatedFunction
    ): Closure {
        return function () use ($plugin, $loop, $startTime, $repeatedFunction) {
            // plugin should be unregistered from loop
            if (!$plugin->isRegisteredToLoop()) {
                $plugin->addOutput(
                    'Unregistered from loop: "' . $plugin->getName() .'"',
                    OutputInterface::VERBOSITY_DEBUG
                );
                return;
            }
            $elapsedSec = (microtime(true) - $startTime) * 0.000001;
            if ($elapsedSec > $plugin->getMinIntervalSec()) {
                $plugin->addOutput(
                    'starting new future "' . $plugin->getName() .'"',
                    OutputInterface::VERBOSITY_DEBUG
                );
                $loop->futureTick($repeatedFunction);
                return;
            }
            $waitingSec = $plugin->getMinIntervalSec() - $elapsedSec;
            $plugin->addOutput(
                'awaiting new future "' . $plugin->getName() . '" for ' . $waitingSec . ' sec',
                OutputInterface::VERBOSITY_DEBUG
            );
            $loop->addTimer($waitingSec, function () use ($plugin, $loop, $repeatedFunction) {
                $plugin->addOutput(
                    'starting new future "' . $plugin->getName() . '"',
                    OutputInterface::VERBOSITY_DEBUG
                );
                $loop->futureTick($repeatedFunction);
            });
        };
    }
}
