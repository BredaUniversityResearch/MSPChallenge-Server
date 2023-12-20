<?php

namespace App\MessageHandler\Analytics;

use App\Message\Analytics\AnalyticsMessageBase;
use App\MessageTransformer\Analytics\GURaaSMessageTransformer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class AnalyticsMessageHandler
{
    private Uuid $guraasGameId;
    private GURaaSMessageTransformer $guraasRequestTransformer;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $guraasGameId,
    ) {
        $this->guraasGameId = Uuid::fromString($guraasGameId);
        $this->guraasRequestTransformer = new GURaaSMessageTransformer($logger);
        $this->httpClient = $httpClient;
        $this->logger = $logger;

        if (!Uuid::isValid($this->guraasGameId)) {
            $this->logger->error('Invalid GURaaS Game ID configured as parameter to the AnalyticsMessageHandler!');
        }
    }

    public function __invoke(AnalyticsMessageBase $message)
    {
        $requestBody = $this->guraasRequestTransformer->transformMessageToRequestBody($message);
        if (empty($requestBody)) {
            return;
        }

        $result = $this->postRequestToGURaaS($requestBody);
        //TODO: properly handle result, retry certain amount of times when failing to post.
        // maybe supported by the message handler system already?
    }

    private function postRequestToGURaaS($requestBody) : bool
    {
        if (!Uuid::isValid($this->guraasGameId)) {
            return false;
        }

        if (!$requestBody) {
            $this->logger->error('No body supplied for GURaaS POST request!');
            return false;
        }

        $url = "https://grg.service.guraas.com/v1/games/$this->guraasGameId/data";
        try {
            $response = $this->httpClient->request('POST', $url, ['json' => $requestBody]);
            $statusCode = $response->getStatusCode();
            return $statusCode == 201;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error("POST request to GURaaS failed with exception: " . $e->getMessage());
        }
        return false;
    }
}
