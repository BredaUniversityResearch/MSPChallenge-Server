<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Log;
use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Domain\Common\MessageJsonResponse;
use App\MessageHandler\GameList\SessionLogHandler;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/{log}', requirements: ['log' => '[lL]og'])]
#[OA\Tag(
    name: 'Log',
    description: 'log events'
)]
#[OA\Parameter(
    name: 'log',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        default: 'log',
        enum: ['log', 'Log']
    )
)]
class LogController extends BaseController
{
    #[Route('/Event', name: 'session_api_log_event', methods: ['POST'])]
    #[OA\Post(
        summary: 'Posts an \'error\' event in the server log',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'source',
                            description: 'Source component of the error. Examples: Server, MEL, CEL, SEL etc.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'severity',
                            description: 'Severity of the error ["Warning"|"Error"|"Fatal"]',
                            type: 'string',
                            enum: ['Warning', 'Error', 'Fatal']
                        ),
                        new OA\Property(
                            property: 'message',
                            description: 'Debugging information associated with this event',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'stack_trace',
                            description: 'Debug stacktrace where the error occurred. Optional.',
                            type: 'string',
                            nullable: true
                        ),
                    ]
                )
            )
        )
    )]
    public function logEvent(
        Request $request,
        LoggerInterface $gameSessionLogger
    ): JsonResponse {
        if ((null === $message = $request->request->get('message')) || '' === trim($message)) {
            return new MessageJsonResponse(
                status: 400,
                message: 'Message parameter is required and cannot be empty.'
            );
        }
        $severityInput = $request->request->get('severity', EventLogSeverity::WARNING->value);
        $severity = EventLogSeverity::tryFrom($severityInput);
        if (null === $severity) {
            return new MessageJsonResponse(
                status: 400,
                message: 'Invalid severity value. Allowed values are: '. implode(', ', array_map(
                    fn($case) => $case->value,
                    EventLogSeverity::cases()
                ))
            );
        }
        $source = $request->request->get('source', 'Unknown');
        $stackTrace = $request->request->get('stack_trace', '');
        $context = ['source' => $source];
        if (!empty($stackTrace)) {
            $context = [
                'stack_trace' => $stackTrace,
                'headers' => array_intersect(
                    ['x-server-id','msp-client-version','x-simulation-name'],
                    array_keys($request->headers->all())
                )
            ];
        }

        // external log call, so log to monolog as well
        $logger = new SessionLogHandler($gameSessionLogger);
        $logger->setGameSessionId($this->getSessionIdFromRequest($request));
        $logger->log($this->getLogLevel($severity), $message, $context);

        // log to database
        try {
            $log = new Log();
            $log->Event(
                source: $source,
                severity: $severity->value,
                message: $message,
                stack_trace: $stackTrace
            );
            return new JsonResponse(status: 200);
        } catch (\Exception $e) {
            return new MessageJsonResponse(
                status: $e->getCode() ?: 500,
                message: $e->getMessage()
            );
        }
    }

    private function getLogLevel(EventLogSeverity $severity): string
    {
        return match ($severity) {
            EventLogSeverity::WARNING => LogLevel::WARNING,
            EventLogSeverity::ERROR => LogLevel::ERROR,
            EventLogSeverity::FATAL => LogLevel::CRITICAL,
        };
    }
}
