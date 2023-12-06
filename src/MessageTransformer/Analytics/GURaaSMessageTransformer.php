<?php

/*
 * Request structure for GURaaS messages:
 *
 * POST Request URL: /games/{id_game: UUID}/data/
 *
 *  {
 *      id_session: UUID
 *      id_player: (optional?) string (max 63/64 chars inc null terminator)
 *      version: (optional?) string (max 63/64 chars inc null terminator)
 *      start: date-time - format: yyyy-MM-dd hh:mm:ss
 *      end: date-time - format: yyyy-MM-dd hh:mm:ss
 *      context: (optional?) string (max 4068/4069 chars inc null terminator)
 *      data:
 *      [
 *          {
 *              time: date-time - format: yyyy-MM-dd hh:mm:ss
 *              tag1: string (max 31/32 chars inc null terminator)
 *              tag2: string (max 31/32 chars inc null terminator)
 *              tag3: string (max 31/32 chars inc null terminator)
 *              tag4: string (max 31/32 chars inc null terminator)
 *              data: string (max 4096 chars inc null terminator)
 *          }, ...
 *      ]
 *  }
 *
 */

//1. Event happens
// Event's message is only concerned with the data about the event.
//2. This class gets notified of event through a message
//3. message's data should be used to construct a GURaaS POST Request.
//problem: different type of messages need different construction methods.
//differences: member variables in messages
//common: structure that the resulting POST Request should have
//solution: select different construction method based on type.
//how? A GURaaSPostRequest factory
//TODO:
//1. Transform message to GURaaS message
//2. Send message to GURaaS via HTTP POST request

namespace App\MessageTransformer\Analytics;

use App\Message\Analytics\AnalyticsMessageBase;
use App\Message\Analytics\ClientJoinedSession;
use App\Message\Analytics\SessionCreated;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

class GURaaSMessageTransformer
{
    const GURAAS_DATETIME_FORMAT = "Y-m-d H:i:s";
    const GURAAS_MAX_TAG_SIZE = 32;
    const GURAAS_MAX_DATA_SIZE = 4096;

    private Uuid $guraasSessionId;
    private DateTimeImmutable $guraasSessionStart;

    public function __construct(
        ?Uuid $guraasSessionId = null,
        ?DateTimeImmutable $guraasSessionStart = null
    ) {
        $this->guraasSessionId = $guraasSessionId ?? Uuid::v1();
        $this->guraasSessionStart = $guraasSessionStart ?? new DateTimeImmutable();
    }

    public function transformMessageToRequestBody(AnalyticsMessageBase $message) : array | null
    {
        if ($message instanceof SessionCreated) {
            return $this->transformSessionCreated($message);
        }
        if ($message instanceof ClientJoinedSession) {
            return $this->transformClientJoinedSession($message);
        }
        //TODO: log unsupported message type transformation.
        return null;
    }

    private function transformSessionCreated(SessionCreated $message) : array | null
    {
        $tag1 = "GameSessionCreated";
        //TODO: tag2 needs to be a ServerManager uuid (serialized, only 31 chars)
        // so different messages can be associated with each other
        $tag3 = strval($message->id);
        $data = json_encode($message);
        return $this->createPostRequestBody(
            $message->timeStamp,
            $tag1,
            null,
            $tag3,
            null,
            $data
        );
    }
    public function transformClientJoinedSession(ClientJoinedSession $message) : array | null
    {
        $tag1 = "ClientJoinedSession";
        //TODO: tag2 needs to be a ServerManager uuid (serialized, only 31 chars)
        // so different messages can be associated with each other
        $tag3 = strval($message->id);
        $data = json_encode($message);
        return $this->createPostRequestBody(
            $message->timeStamp,
            $tag1,
            null,
            $tag3,
            null,
            $data
        );
    }
    private function createPostRequestBody(
        DateTimeImmutable $timeStamp,
        ?string $tag1,
        ?string $tag2,
        ?string $tag3,
        ?string $tag4,
        ?string $data
    ) : array | null {
        if (!(
            self::checkValidStringLength($tag1, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag2, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag3, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag4, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($data, self::GURAAS_MAX_DATA_SIZE)
        )) {
            //TODO: throw exception or log error somehow.
            return null;
        }

        return
        [
            'id_session' => $this->guraasSessionId,
            'start' => $this->guraasSessionStart->format(self::GURAAS_DATETIME_FORMAT),
            'end' => (new DateTimeImmutable())->format(self::GURAAS_DATETIME_FORMAT),
            'data' =>
            [
                [
                    'time' => $timeStamp->format(self::GURAAS_DATETIME_FORMAT),
                    'tag1' => $tag1 ?? "",
                    'tag2' => $tag2 ?? "",
                    'tag3' => $tag3 ?? "",
                    'tag4' => $tag4 ?? "",
                    'data' => $data ?? ""
                ]
            ]
        ];
    }

    private static function checkValidStringLength(?string $string, int $maxSize): bool
    {
        return !($string && iconv_strlen($string)> $maxSize);
    }
}
