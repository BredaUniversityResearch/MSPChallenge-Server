<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use Exception;
use React\Promise\Deferred;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function App\chain;
use function App\resolveOnFutureTick;
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

    protected function onCreatePromiseFunction(): ToPromiseFunction
    {
        return tpf(function () {
            $toPromiseFunctions = collect($this->plugins)->map(function (Plugin $p) {
                return tpf(function () use ($p) {
                    $this->addOutput(
                        $this->getName().': registering plugin '.$p->getName(),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $this->getWsServer()->registerPlugin($p);
                    // meaning sequencer plugin itself will not register any promises, but since it calls registerPlugin
                    //   on other plugins, those will be registered
                    return resolveOnFutureTick(new Deferred())->promise();
                });
            })->all();
            return chain($toPromiseFunctions);
        });
    }

    /**
     * @throws Exception
     */
    public function onEvent(NameAwareEvent $event)
    {
        if ($event->getEventName() != self::EVENT_PLUGIN_EXECUTION_FINISHED) {
            return;
        }
        /** @var Plugin $plugin */
        $plugin = $event->getSubject(); // the plugin that just finished
        $this->getWsServer()->unregisterPlugin($plugin);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::EVENT_PLUGIN_EXECUTION_FINISHED => 'onEvent'
        ];
    }
}
