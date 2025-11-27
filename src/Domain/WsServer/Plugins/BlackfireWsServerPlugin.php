<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\Context;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use Blackfire\Client;
use Blackfire\Probe;
use Blackfire\Profile\Configuration as ProfileConfiguration;
use React\Promise\Deferred;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function App\resolveOnFutureTick;
use function App\tpf;

class BlackfireWsServerPlugin extends Plugin implements EventSubscriberInterface
{
    const OUTPUT_PREFIX = 'Blackfire plugin -----> ';
    const STATE_SETUP = 'SETUP';
    const STATE_WAIT_FOR_PLUGIN_EXECUTION = 'WAIT_FOR_PLUGIN_EXECUTION';
    const STATE_WAIT_FOR_ENABLE_PROBE = 'WAIT_FOR_ENABLE_PROFILING';
    const STATE_PROFILING = 'PROFILING';

    private ?Client $blackfire = null;
    private ProfileConfiguration $profileConfig;
    private ?string $pluginNameToProfile = null;
    private ?string $pluginExecutionId = null;
    private string $state = self::STATE_SETUP;
    private ?Probe $probe = null;
    private array $pluginNames = [];

    public function __construct()
    {
        assert(extension_loaded('pcntl'));
        $this->blackfire = new Client();

        // set signals
        $fn = function (int $signalId) {
            $this->signalHandler($signalId);
        };
        pcntl_signal(SIGUSR1, $fn);
        pcntl_signal(SIGUSR2, $fn);

        $this->profileConfig = new ProfileConfiguration();
        $this->profileConfig->setTitle('MSP Challenge Websocket server');
        $this->profileConfig->setSamples(1);
        parent::__construct('blackfire');
        $this->startStateSetup();
    }

    private function signalHandler(int $signalId)
    {
        assert(extension_loaded('pcntl'));
        switch ($signalId) {
            case SIGUSR1:
                $this->addOutput('SIGUSR1 received');
                $this->nextPluginNameToProfile();
                break;
            case SIGUSR2:
                $this->addOutput('SIGUSR2 received');
                $this->tryEnableProfiling();
                break;
            default:
                // nothing to do.
                break;
        }
    }

    private function nextPluginNameToProfile(): void
    {
        if (null === $this->pluginNameToProfile) {
            $this->pluginNameToProfile = $this->pluginNames[0] ?? null;
        } else {
            $key = array_search($this->pluginNameToProfile, $this->pluginNames);
            $this->pluginNameToProfile = $this->pluginNames[++$key % count($this->pluginNames)];
        }
        $this->addOutput('Next profile: '. ($this->pluginNameToProfile ?? 'none available'));
    }

    private function tryEnableProfiling(): void
    {
        if ($this->pluginNameToProfile == null) {
            $this->addOutput('Please select plugin to profile first');
            return;
        }
        $this->state = self::STATE_WAIT_FOR_PLUGIN_EXECUTION;
        $this->addOutput('Awaiting next execution of plugin: '. $this->pluginNameToProfile);
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function (?Context $context) {
            return resolveOnFutureTick(new Deferred())->promise()->then(function () {
                // nothing to do here.
            });
        });
    }

    public static function getDefaultMinIntervalSec(): float
    {
        return PHP_FLOAT_EPSILON * 2; // like as quick as possible
    }

    public function onPluginRegistered(NameAwareEvent $event): void
    {
        /** @var Plugin $plugin */
        $plugin = $event->getSubject();
        $this->pluginNames[] = $plugin->getName();
    }

    public function onPluginExecutionStarted(NameAwareEvent $event): void
    {
        // Is Blackfire plugin waiting for a plugin to be executed?
        if ($this->state !== self::STATE_WAIT_FOR_PLUGIN_EXECUTION) {
            return;
        }
        // Yes, does it match our plugin name to profile?
        /** @var Plugin $plugin */
        $plugin = $event->getSubject();
        if ($this->pluginNameToProfile !== $plugin->getName()) {
            return;
        }
        // Yes, prepare probe
        try {
            $this->probe = $this->blackfire->createProbe($this->profileConfig, false);
        } catch (\Blackfire\Exception\OfflineException $e) {
            // Oh no, internet connection seems down, cancel current profile
            $this->addOutput('Blackfire offline: ' . $e->getMessage());
            $this->startStateSetup();
            return;
        }
        // Remember the exact plugin execution to match on EnableProbe and Finished event
        $this->pluginExecutionId = $event->getArgument(self::EVENT_ARG_EXECUTION_ID);
        $this->state = self::STATE_WAIT_FOR_ENABLE_PROBE;
        $this->addOutput(
            'Plugin execution started, awaiting for enable probe',
            OutputInterface::VERBOSITY_VERY_VERBOSE
        );
    }

    private function startStateSetup(): void
    {
        $this->addOutput('Awaiting signals to setup profiling');
        $this->addOutput('Run "kill -SIGUSR1 '.getmypid().'" to select next plugin to profile');
        $this->addOutput('Run "kill -SIGUSR2 '.getmypid().'" to enable profiling after selecting plugin');
        $this->state = self::STATE_SETUP;
    }

    public function onPluginExecutionEnableProbe(NameAwareEvent $event): void
    {
        // Is Blackfire plugin waiting for a plugin execution to enable probe?
        if ($this->state !== self::STATE_WAIT_FOR_ENABLE_PROBE) {
            return;
        }
        // Yes, does the plugin execution id match?
        if ($this->pluginExecutionId !== $event->getArgument(self::EVENT_ARG_EXECUTION_ID)) {
            return;
        }
        // Yes, enable the probe
        $this->probe->enable();
        $this->state = self::STATE_PROFILING;
        $this->addOutput('Probe enabled, starting profiling');
    }

    public function onPluginExecutionFinished(NameAwareEvent $event): void
    {
        // Yes, does the plugin execution id match?
        if ($this->pluginExecutionId !== $event->getArgument(self::EVENT_ARG_EXECUTION_ID)) {
            return;
        }
        $this->pluginExecutionId = null;
        $this->addOutput('Plugin execution finished, closing probe', OutputInterface::VERBOSITY_VERY_VERBOSE);
        // Did we do some actually profiling?
        if ($this->state != self::STATE_PROFILING) {
            // No, automatically setup for a next plugin execution.
            $this->addOutput(
                'Warning: probe was not enabled, no profile created, awaiting next execution',
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
            $this->state = self::STATE_WAIT_FOR_PLUGIN_EXECUTION;
            return;
        }
        // Yes, close probe, then output the profile result
        $this->probe->close();
        $profile = $this->blackfire->endProbe($this->probe);
        $this->probe = null;
        $this->addOutput('Profile created: '.$profile->getUrl());
        $this->startStateSetup();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::EVENT_PLUGIN_REGISTERED => 'onPluginRegistered',
            self::EVENT_PLUGIN_EXECUTION_STARTED => 'onPluginExecutionStarted',
            self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE => 'onPluginExecutionEnableProbe',
            self::EVENT_PLUGIN_EXECUTION_FINISHED => 'onPluginExecutionFinished'
        ];
    }

    public function addOutput(string $output, ?int $verbosity = null): static
    {
        parent::addOutput(self::OUTPUT_PREFIX.$output, $verbosity);
        return $this;
    }
}
