<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function App\chain;
use function App\tpf;

class SequencerWsServerPlugin extends Plugin implements EventSubscriberInterface
{
    /**
     * @var Plugin[] array
     */
    private array $plugins = [];

    public static function getDefaultMinIntervalSec(): float
    {
        return 0; // 0 meaning no interval, no repeating
    }

    /**
     * @param array{string|array{class: string, constructor_params?: array}} $pluginConstructionDataContainer
     * @param float|null $minIntervalSec
     */
    public function __construct(array $pluginConstructionDataContainer, ?float $minIntervalSec = null)
    {
        foreach ($pluginConstructionDataContainer as $d) {
            if (is_string($d)) {
                $class = $d;
                $constructorParams = [];
            } else {
                assert(is_array($d), 'Encountered invalid element for array $pluginConstructionDataContainer');
                $class = $d['class'];
                $constructorParams = $d['constructor_params'] ?? [];
            }
            // overriding minIntervalSec to zero, meaning no interval, no repeating, since that's what this sequencer
            //  will take care of
            $constructorParams['minIntervalSec'] = 0;
            /** @var Plugin $plugin */
            $plugin = new ($class)(...($constructorParams));
            $plugin->addSubscriber($this);
            $this->plugins[] = $plugin;
        }

        if ($minIntervalSec == null) {
            // use the minimum of all the plugin's minIntervalSec
            $minIntervalSec = collect($this->plugins)->reduce(
                fn($carry, Plugin $item) => min($carry, call_user_func([$item, 'getDefaultMinIntervalSec'])),
                PHP_INT_MAX
            );
        }

        parent::__construct(
            'sequencer_' . collect($this->plugins)->map(fn(Plugin $x) => $x->getName())->implode('_'),
            $minIntervalSec
        );
    }

    protected function onCreatePromiseFunction(string $executionId): ToPromiseFunction
    {
        return tpf(function () use ($executionId) {
            $toPromiseFunctions = collect($this->plugins)->map(function (Plugin $p) use ($executionId) {
                $p
                    ->setLoop($this->getLoop())
                    ->setGameSessionIdFilter($this->getGameSessionIdFilter())
                    ->setMeasurementCollectionManager($this->getMeasurementCollectionManager())
                    ->setClientConnectionResourceManager($this->getClientConnectionResourceManager())
                    ->setServerManager($this->getServerManager())
                    ->setWsServer($this->getWsServer());
                return $p->createPromiseFunction($executionId);
            })->all();
            return chain($toPromiseFunctions);
        });
    }

    public function onPluginExecutionEnableProbe(NameAwareEvent $event): void
    {
        $this->dispatch(
            new NameAwareEvent(self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE, $this, $event->getArguments()),
            self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::EVENT_PLUGIN_EXECUTION_ENABLE_PROBE => 'onPluginExecutionEnableProbe'
        ];
    }
}
