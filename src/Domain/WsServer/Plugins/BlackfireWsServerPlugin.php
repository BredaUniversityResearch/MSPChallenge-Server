<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\Common\ToPromiseFunction;
use Blackfire\Client;
use Blackfire\LoopClient;
use Blackfire\Profile\Configuration as ProfileConfiguration;
use React\Promise\Deferred;
use function App\resolveOnFutureTick;
use function App\tpf;

class BlackfireWsServerPlugin extends Plugin
{
    private ?LoopClient $blackfire = null;
    private ProfileConfiguration $profileConfig;

    public function __construct()
    {
        assert(extension_loaded('pcntl'));
        $this->blackfire = new LoopClient(new Client(), 10);
        $this->blackfire->setSignal(SIGUSR1);
        $this->profileConfig = new ProfileConfiguration();
        $this->profileConfig->setTitle('MSP Challenge Websocket server');
        parent::__construct('blackfire');
    }

    protected function onCreatePromiseFunction(): ToPromiseFunction
    {
        return tpf(function () {
            return resolveOnFutureTick(new Deferred())->promise()->then(function () {
                if ($profile = $this->blackfire->endLoop()) {
                    print $profile->getUrl().PHP_EOL;
                }
                $this->blackfire->startLoop($this->profileConfig);
            });
        });
    }

    public static function getDefaultMinIntervalSec(): float
    {
        return PHP_FLOAT_EPSILON * 2; // like as quick as possible
    }
}
