<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\API\v1\Security;
use App\Domain\Common\ToPromiseFunction;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientDisconnectedException;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Domain\WsServer\EPayloadDifferenceType;
use App\Domain\WsServer\Plugins\Plugin;
use App\Domain\WsServer\Plugins\PluginHelper;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Exception;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\tpf;
use function React\Promise\all;
use function React\Promise\reject;

class LatestWsServerPlugin extends Plugin
{
    private const LATEST_CLIENT_UPDATE_SPEED = 60.0;

    /**
     * @var GameLatest[]
     */
    private array $gameLatestInstances = [];

    public static function getDefaultMinIntervalSec(): float
    {
        return 0.2;
    }

    public function __construct(?float $minIntervalSec = null)
    {
        parent::__construct('latest', $minIntervalSec);
    }

    protected function onCreatePromiseFunction(): ToPromiseFunction
    {
        return tpf(function () {
            return $this->latest()
                ->then(function (array $payloadContainer) {
                    $this->addOutput(
                        'just finished "latest" for connections: ' . implode(', ', array_keys($payloadContainer)),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $payloadContainer = array_filter($payloadContainer);
                    if (empty($payloadContainer)) {
                        return;
                    }
                    $this->addOutput(json_encode($payloadContainer));
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        });
    }

    private function latestForClient(int $connResourceId, array $clientInfo): PromiseInterface
    {
        $latestTimeStart = microtime(true);
        $this->addOutput('Starting "latest" for: ' . $connResourceId, OutputInterface::VERBOSITY_VERY_VERBOSE);
        return $this->getGameLatest($connResourceId)->latest(
            $clientInfo['team_id'],
            $clientInfo['last_update_time'],
            $clientInfo['user'],
            $this->isDebugOutputEnabled()
        )
        ->then(function ($payload) use ($connResourceId, $latestTimeStart, $clientInfo) {
            if ($payload === null) {
                $this->addOutput('no payload', OutputInterface::VERBOSITY_VERY_VERBOSE);
                return [];
            }
            $this->addOutput(
                'Created "latest" payload for: ' . $connResourceId,
                OutputInterface::VERBOSITY_VERY_VERBOSE
            );
            $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                $this->getName(),
                (string)$connResourceId,
                microtime(true) - $latestTimeStart
            );
            if (empty($payload)) {
                $this->addOutput('empty payload', OutputInterface::VERBOSITY_VERY_VERBOSE);
                return [];
            }
            if (null === $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)) {
                // disconnected while running this async code, nothing was sent
                $this->addOutput('disconnected while running this async code, nothing was sent');
                $e = new ClientDisconnectedException();
                $e->setConnResourceId($connResourceId);
                throw $e;
            }
            $clientInfoContainer = $this->getClientConnectionResourceManager()->getClientInfo($connResourceId);
            if ($clientInfo['last_update_time'] != $clientInfoContainer['last_update_time']) {
                // encountered another issue: mismatch between the "used" client info's last_update_time
                //   and the "latest", so this payload will not be accepted, and should not be sent anymore...
                $this->addOutput(
                    'mismatch between the "used" client info\'s last_update_time and the "latest"'
                );
                // just return empty payload, nothing was sent...
                return [];
            }

            if (isset($clientInfoContainer['prev_payload']) &&
                in_array(
                    (string)$this->comparePayloads(
                        $clientInfoContainer['prev_payload'],
                        $payload
                    ),
                    [
                        EPayloadDifferenceType::NO_DIFFERENCES,
                        EPayloadDifferenceType::NONESSENTIAL_DIFFERENCES
                    ]
                )
            ) {
                // no essential payload differences compared to the previous one, no need to send it now
                $this->addOutput(
                    'no essential payload differences compared to the previous one, ' .
                        'no need to send it now',
                    OutputInterface::VERBOSITY_VERY_VERBOSE
                );
                return []; // no need to send
            }

            $this->addOutput('send payload to: ' . $connResourceId);
            $data = [
                'header_type' => 'Game/Latest',
                'header_data' => null,
                'success' => true,
                'message' => null,
                'payload' => $payload
            ];
            PluginHelper::getInstance()->dump($connResourceId, $data);
            $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->sendAsJson($data);
            $this->getClientConnectionResourceManager()->setClientInfo(
                $connResourceId,
                'prev_payload',
                $payload
            );
            $this->getClientConnectionResourceManager()->setClientInfo(
                $connResourceId,
                'last_update_time',
                $payload['update_time']
            );
            return $payload;
        });
    }

    /**
     * @throws Exception
     */
    private function latest(): Promise
    {
        $clientInfoPerSessionContainer = $this->getClientConnectionResourceManager()
            ->getClientInfoPerSessionCollection();
        $gameSessionId = $this->getGameSessionIdFilter();
        if ($gameSessionId != null) {
            $clientInfoPerSessionContainer = $clientInfoPerSessionContainer->only($gameSessionId);
        }
        $promises = [];
        foreach ($clientInfoPerSessionContainer as $clientInfoContainer) {
            foreach ($clientInfoContainer as $connResourceId => $clientInfo) {
                $accessTimeRemaining = 0; // not used
                if (false === $this->getClientConnectionResourceManager()->getSecurity($connResourceId)->validateAccess(
                    Security::ACCESS_LEVEL_FLAG_FULL,
                    $accessTimeRemaining,
                    $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId)[
                    ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN
                    ]
                )) {
                    // Client's token has been expired, let the client re-connected with a new token
                    $this->addOutput(
                        'Client\'s token has been expired, let the client re-connected with a new token'
                    );
                    $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->close();
                    continue;
                }
                $promises[$connResourceId] = $this->latestForClient($connResourceId, $clientInfo);
            }
        }
        /** @var PromiseInterface&Promise $promise */
        $promise = all($promises);
        return $promise;
    }

    private function comparePayloads(array $p1, array $p2): EPayloadDifferenceType
    {
        // any state change is essential
        if ($p1['tick']['state'] !== $p2['tick']['state']) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        // remember for later
        $p1TickEraTimeleft = $p1['tick']['era_timeleft'] ?? 0;
        $p2TickEraTimeleft = $p2['tick']['era_timeleft'] ?? 0;
        $eraTimeLeftDiff = abs($p1TickEraTimeleft - $p2TickEraTimeleft);

        // if there are any other changes then "time" or "debug" fields, it is essential
        unset(
            $p1['prev_update_time'],
            $p1['update_time'],
            $p1TickEraTimeleft,
            $p1['tick']['era_timeleft'],
            $p1['debug'],
            $p2['prev_update_time'],
            $p2['update_time'],
            $p2TickEraTimeleft,
            $p2['tick']['era_timeleft'],
            $p2['debug'],
        );
        if (0 != strcmp(json_encode($p1), json_encode($p2))) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        // or if the difference in era_timeleft is larger than self::LATEST_CLIENT_UPDATE_SPEED
        if ($eraTimeLeftDiff > self::LATEST_CLIENT_UPDATE_SPEED) {
            return new EPayloadDifferenceType(EPayloadDifferenceType::ESSENTIAL_DIFFERENCES);
        }

        return $eraTimeLeftDiff < PHP_FLOAT_EPSILON ?
            // note that prev_update_time and update_time are not part of the difference comparison
            new EPayloadDifferenceType(EPayloadDifferenceType::NO_DIFFERENCES) :
            // era time is different, but nonessential
            new EPayloadDifferenceType(EPayloadDifferenceType::NONESSENTIAL_DIFFERENCES);
    }

    /**
     * @throws Exception
     */
    private function getGameLatest(int $connResourceId): GameLatest
    {
        $clientHeaders = $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId);
        $gameSessionId = $clientHeaders[ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->gameLatestInstances)) {
            $gameLatest = new GameLatest();
            $gameLatest->setAsync(true);
            $gameLatest->setGameSessionId($gameSessionId);
            $gameLatest->setAsyncDatabase($this->getServerManager()->getGameSessionDbConnection($gameSessionId));
            $gameLatest->setToken($clientHeaders[ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN]);

            $this->gameLatestInstances[$connResourceId] = $gameLatest;
        }
        return $this->gameLatestInstances[$connResourceId];
    }

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        if ($event->getEventName() == WsServerEventDispatcherInterface::EVENT_ON_CLIENT_DISCONNECTED) {
            unset($this->gameLatestInstances[$event->getSubject()]);
        }
    }
}
