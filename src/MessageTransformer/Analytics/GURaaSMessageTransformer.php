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
use App\Message\Analytics\Helper\AnalyticsDataType;
use App\Message\Analytics\UserLogOnOffSessionMessage;
use App\Message\Analytics\SessionCreatedMessage;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class GURaaSMessageTransformer
{
    const GURAAS_DATETIME_FORMAT = "Y-m-d H:i:s";
    const GURAAS_MAX_TAG_SIZE = 32;
    const GURAAS_MAX_DATA_SIZE = 4096;

    private Uuid $guraasSessionId;
    private string $guraasAnalyticsVersion;
    private DateTimeImmutable $guraasSessionStart;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        string $guraasAnalyticsVersion,
        ?Uuid $guraasSessionId = null,
        ?DateTimeImmutable $guraasSessionStart = null
    ) {
        $this->guraasSessionId = $guraasSessionId ?? Uuid::v1();
        $this->guraasAnalyticsVersion = $guraasAnalyticsVersion;
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

    private function transformSessionCreated(SessionCreatedMessage $message) : array
    {
        $tag1 = strval($message->session->id);
        $data = json_encode($message);
        assert($data, new Exception("Failed to encode SessionCreatedMessage as json data"));

        return $this->createPostRequestBody(
            $message->timeStamp,
            $message->serverManagerId,
            $message->type,
            $tag1,
            null,
            $data
        );
    }

    private function transformClientJoinedSession(UserLogOnOffSessionMessage $message) : array
    {
        $tag1 = strval($message->gameSessionId);
        $tag2 = strval($message->countryId);
        $data = json_encode($message);
        assert($data, new Exception("Failed to encode UserLogOnOffSessionMessage as json data"));

        return $this->createPostRequestBody(
            $message->timeStamp,
            $message->serverManagerId,
            $message->type,
            $tag1,
            $tag2,
            $data
        );
    }

    private function createPostRequestBody(
        DateTimeImmutable $timeStamp,
        Uuid $serverManagerId,
        AnalyticsDataType $dataType,
        ?string $tag1,
        ?string $tag2,
        ?string $data
    ) : array {
        $dataVersionTag = strval($dataType)."-".$this->guraasAnalyticsVersion;
        self::assertValidTagsAndData($tag1, $tag2, $dataVersionTag, $data);

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
                    'tag3' => self::tagFromUuid($serverManagerId),
                    'tag4' => $dataVersionTag,
                    'data' => $data ?? ""
                ]
            ]
        ];
    }

    private static function assertValidTagsAndData(
        ?string $tag1,
        ?string $tag2,
        string $dataVersionTag,
        ?string $data
    ) {
        assert(
            !($tag1 && iconv_strlen($tag1) > self::GURAAS_MAX_TAG_SIZE),
            new Exception(
                "Tag 1 for GURaaS POST request body is not a valid length: ".
                (is_null($tag1) ? 0 : iconv_strlen($tag1))
            )
        );
        assert(
            !($tag2 && iconv_strlen($tag2) > self::GURAAS_MAX_TAG_SIZE),
            new Exception(
                "Tag 2 for GURaaS POST request body is not a valid length: ".
                (is_null($tag2) ? 0 : iconv_strlen($tag2))
            )
        );
        assert(
            iconv_strlen($dataVersionTag) <= self::GURAAS_MAX_TAG_SIZE,
            new Exception(
                "Data version tag for GURaaS POST request body is not a valid length: ".
                (iconv_strlen($dataVersionTag))
            )
        );

        assert(
            !($data && iconv_strlen($data) > self::GURAAS_MAX_DATA_SIZE),
            new Exception(
                "Data for GURaaS POST request body is not a valid length: ".
                (is_null($data) ? 0 : iconv_strlen($data))
            )
        );
    }

    private static function tagFromUuid(Uuid $id) : string
    {
        return $id->toBase32();
    }
}
