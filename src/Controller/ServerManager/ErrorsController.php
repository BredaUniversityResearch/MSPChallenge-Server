<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Helper\Util;
use App\Domain\Services\DockerApiService;
use App\Entity\ServerManager\DockerApi;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{manager}/error', requirements: ['manager' => 'manager|ServerManager'], defaults: ['manager' => 'manager'])]
class ErrorsController extends BaseController
{
    /**
     * @throws Exception
     */
    #[Route(name: 'manager_errors')]
    public function index(): Response
    {
        return $this->render('manager/errors_page.html.twig');
    }

    /**
     * @throws Exception
     */
    #[Route('/logs', name: 'manager_error_logs')]
    public function getLogs(
        Request $request,
        DockerApiService $dockerApiService,
        LoggerInterface $logger
    ): JsonResponse {
        $localDockerApi = new DockerApi();
        $localDockerApi
            ->setPort(2375)
            ->setAddress('localhost')
            ->setScheme('http');
        $fluentBitContainerId = $dockerApiService->dockerApiCall($localDockerApi, 'GET', '/containers/json', [
            'query' => [
                'filters' => '{"label": ["com.docker.compose.service=fluent-bit"]}'
            ],
        ])['0']['Id'] ?? null;
        if ($fluentBitContainerId === null) {
            return $this->json([
                'lines' => [],
                'lastLine' => 0
            ]);
        }
        $fluentBitLogPath = $dockerApiService->dockerApiCall(
            $localDockerApi,
            'GET',
            "/containers/$fluentBitContainerId/json"
        )['LogPath'] ?? null;
        if ($fluentBitLogPath === null) {
            return $this->json([
                'lines' => [],
                'lastLine' => 0
            ]);
        }
        $offset = (int) $request->query->get('offset', '0');
        $file = new \SplFileObject($fluentBitLogPath);
        $file->seek($offset); // $offset is the last line number returned

        $lines = [];
        $lineCount = $offset;
        while (!$file->eof()) {
            $line = $file->current();
            if ((trim($line) !== '') &&
                ($line = json_decode($line, true)) &&
                ($log = json_decode($line['log'], true))
            ) {
                $diff = array_diff(['message', 'level_name', 'datetime', 'channel'], array_keys($log));
                if (!empty($diff)) {
                    $log = [
                        'message' => 'Malformed log line, missing fields: ' . implode(', ', $diff),
                        'level_name' => 'WARNING',
                        'datetime' => $line['datetime'] ?? date('c'),
                        'channel' => 'fluent-bit',
                        'context' => ['log' => $log],
                    ];
                }
                $log['extra'] = array_merge($log['extra'] ?? [], $log['context'] ?? []);
                unset($log['context']);
                if (empty($log['extra'])) {
                    unset($log['extra']);
                }
                $lines[] = $log;
            }
            $file->next();
            $lineCount++;
            // Let's prevent getting out of memory
            if (memory_get_usage(true) / Util::getMemoryLimit() > 0.8) {
                break;
            }
        }
        return $this->json([
            'lines' => $lines,
            'lastLine' => $lineCount,
        ]);
    }
}
