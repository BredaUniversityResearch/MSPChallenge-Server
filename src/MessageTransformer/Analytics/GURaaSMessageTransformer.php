<?php

/*
 * Request structure for GURaaS messages:
 *
 * POST Request URL: /games/{id_game: UUID}/data/
 * POST Request Body:
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

namespace App\MessageTransformer\Analytics;

use App\Message\Analytics\AnalyticsMessageBase;
use App\Message\Analytics\UserLogOnOffSessionMessage;
use App\Message\Analytics\SessionCreatedMessage;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class GURaaSMessageTransformer
{
    const GURAAS_DATETIME_FORMAT = "Y-m-d H:i:s";
    const GURAAS_MAX_TAG_SIZE = 32;
    const GURAAS_MAX_DATA_SIZE = 4096;

    private Uuid $guraasSessionId;
    private DateTimeImmutable $guraasSessionStart;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        ?Uuid $guraasSessionId = null,
        ?DateTimeImmutable $guraasSessionStart = null
    ) {
        $this->guraasSessionId = $guraasSessionId ?? Uuid::v1();
        $this->guraasSessionStart = $guraasSessionStart ?? new DateTimeImmutable();
        $this->logger = $logger;
    }

    public function transformMessageToRequestBody(AnalyticsMessageBase $message) : ?array
    {
        if ($message instanceof SessionCreatedMessage) {
            return $this->transformSessionCreated($message);
        }
        if ($message instanceof UserLogOnOffSessionMessage) {
            return $this->transformClientJoinedSession($message);
        }
        $this->logger->error(
            "Can not transform analytics message of class: ". get_class($message).
            " to a GURaaS POST request body"
        );
        return null;
    }

    private function transformSessionCreated(SessionCreatedMessage $message) : ?array
    {
        $tag3 = strval($message->session->id);
        $data = json_encode($message);
        return $this->createPostRequestBody(
            $message->timeStamp,
            strval($message->type),
            self::tagFromUuid($message->serverManagerId),
            $tag3,
            null,
            $data
        );
    }

    private function transformClientJoinedSession(UserLogOnOffSessionMessage $message) : ?array
    {
        $tag3 = strval($message->gameSessionId);
        $tag4 = strval($message->countryId);
        $data = json_encode($message);
        return $this->createPostRequestBody(
            $message->timeStamp,
            strval($message->type),
            self::tagFromUuid($message->serverManagerId),
            $tag3,
            $tag4,
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
    ) : ?array {
        if (!(
            self::checkValidStringLength($tag1, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag2, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag3, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($tag4, self::GURAAS_MAX_TAG_SIZE) &&
            self::checkValidStringLength($data, self::GURAAS_MAX_DATA_SIZE)
        )) {
            $tagLengths =
                [
                    (is_null($tag1) ? 0 : iconv_strlen($tag1)),
                    (is_null($tag2) ? 0 : iconv_strlen($tag2)),
                    (is_null($tag3) ? 0 : iconv_strlen($tag3)),
                    (is_null($tag4) ? 0 : iconv_strlen($tag4)),
                ];

            $this->logger->error(
                "Can not create GURaaS POST request body with provided arguments due to invalid argument length.".
                " \nMax tag length: ".strval(self::GURAAS_MAX_TAG_SIZE).
                ", Max data length: ".strval(self::GURAAS_MAX_DATA_SIZE).
                "\n Tag lengths: ". implode(',', $tagLengths).
                "\n Data length: ". (is_null($data) ? 0 : iconv_strlen($data))
            );
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

    private static function tagFromUuid(Uuid $id) : string
    {
        return $id->toBase32();
    }
}
