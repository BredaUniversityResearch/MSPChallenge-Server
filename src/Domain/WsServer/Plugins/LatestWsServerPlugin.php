<?php

namespace App\Domain\WsServer\Plugins;

use App\Domain\API\v1\Game;
use App\Domain\API\v1\Security;
use App\Domain\Event\NameAwareEvent;
use App\Domain\WsServer\ClientDisconnectedException;
use App\Domain\WsServer\ClientHeaderKeys;
use App\Domain\WsServer\EPayloadDifferenceType;
use App\Domain\WsServer\WsServerEventDispatcherInterface;
use Closure;
use Exception;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;

class LatestWsServerPlugin extends Plugin
{
    private const LATEST_MIN_INTERVAL_SEC = 0.2;
    private const LATEST_CLIENT_UPDATE_SPEED = 60.0;

    /**
     * @var Game[]
     */
    private array $gameInstances = [];

    public function __construct()
    {
        parent::__construct('latest', self::LATEST_MIN_INTERVAL_SEC);
    }

    protected function onCreatePromiseFunction(): Closure
    {
        return function () {
            return $this->latest()
                ->then(function (array $payloadContainer) {
                    wdo('just finished "latest" for connections: ' . implode(', ', array_keys($payloadContainer)));
                    $payloadContainer = array_filter($payloadContainer);
                    wdo(json_encode($payloadContainer));
                    $this->getMeasurementCollectionManager()->endMeasurementCollection('latest');
                })
                ->otherwise(function ($reason) {
                    if ($reason instanceof ClientDisconnectedException) {
                        return null;
                    }
                    return reject($reason);
                });
        };
    }

    /**
     * @throws Exception
     */
    private function latest(): PromiseInterface
    {
        wdo('starting "latest"');
        $clientInfoPerSessionContainer = $this->getClientConnectionResourceManager()
            ->getClientInfoPerSessionCollection();
        $gameSessionId = $this->getGameSessionId();
        if ($gameSessionId != null) {
            $clientInfoPerSessionContainer = $clientInfoPerSessionContainer->only($gameSessionId);
        }
        $promises = [];
        $this->getMeasurementCollectionManager()->startMeasurementCollection('latest');
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
                    wdo('Client\'s token has been expired, let the client re-connected with a new token');
                    $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->close();
                    continue;
                }
                $latestTimeStart = microtime(true);
                wdo('Starting "latest" for: ' . $connResourceId);
                $promises[$connResourceId] = $this->getGame($connResourceId)->Latest(
                    $clientInfo['team_id'],
                    $clientInfo['last_update_time'],
                    $clientInfo['user']
                )
                ->then(function ($payload) use ($connResourceId, $latestTimeStart, $clientInfo) {
                    wdo('Created "latest" payload for: ' . $connResourceId);
                    $this->getMeasurementCollectionManager()->addToMeasurementCollection(
                        'latest',
                        $connResourceId,
                        microtime(true) - $latestTimeStart
                    );
                    if (empty($payload)) {
                        wdo('empty payload');
                        return [];
                    }
                    if (null === $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)) {
                        // disconnected while running this async code, nothing was sent
                        wdo('disconnected while running this async code, nothing was sent');
                        $e = new ClientDisconnectedException();
                        $e->setConnResourceId($connResourceId);
                        throw $e;
                    }
                    $clientInfoContainer = $this->getClientConnectionResourceManager()->getClientInfo($connResourceId);
                    if ($clientInfo['last_update_time'] != $clientInfoContainer['last_update_time']) {
                        // encountered another issue: mismatch between the "used" client info's last_update_time
                        //   and the "latest", so this payload will not be accepted, and should not be sent anymore...
                        wdo('mismatch between the "used" client info\'s last_update_time and the "latest"');
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
                        wdo(
                            'no essential payload differences compared to the previous one, ' .
                            'no need to send it now'
                        );
                        return []; // no need to send
                    }

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

                    $json = json_encode([
                        'header_type' => 'Game/Latest',
                        'header_data' => null,
                        'success' => true,
                        'message' => null,
                        'payload' => $payload
                    ]);
                    wdo('send payload to: ' . $connResourceId);
                    $this->getClientConnectionResourceManager()->getClientConnection($connResourceId)->send($json);
                    return $payload;
                });
            }
        }
        return all($promises);
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

        // if there are any other changes then "time" fields, it is essential
        unset(
            $p1['prev_update_time'],
            $p1['update_time'],
            $p1TickEraTimeleft,
            $p1['tick']['era_timeleft'],
            $p2['prev_update_time'],
            $p2['update_time'],
            $p2TickEraTimeleft,
            $p2['tick']['era_timeleft']
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
    private function getGame(int $connResourceId): Game
    {
        $clientHeaders = $this->getClientConnectionResourceManager()->getClientHeaders($connResourceId);
        $gameSessionId = $clientHeaders[ClientHeaderKeys::HEADER_KEY_GAME_SESSION_ID];
        if (!array_key_exists($connResourceId, $this->gameInstances)) {
            $game = new Game();
            $game->setAsync(true);
            $game->setGameSessionId($gameSessionId);
            $game->setAsyncDatabase($this->getServerManager()->getAsyncDatabase($gameSessionId));
            $game->setToken($clientHeaders[ClientHeaderKeys::HEADER_KEY_MSP_API_TOKEN]);

            // do some PRE CACHING calls
            $game->GetWatchdogAddress(true);
            $game->LoadConfigFile();

            $this->gameInstances[$connResourceId] = $game;
        }
        return $this->gameInstances[$connResourceId];
    }

    public function onWsServerEventDispatched(NameAwareEvent $event): void
    {
        if ($event->getEventName() == WsServerEventDispatcherInterface::EVENT_ON_CLIENT_DISCONNECTED) {
            unset($this->gameInstances[$event->getSubject()]);
        }
    }
}
