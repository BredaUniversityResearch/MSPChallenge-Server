<?php

namespace App\MessageHandler\Analytics;

use App\Message\Analytics\AnalyticsMessageBase;
use App\MessageTransformer\Analytics\GURaaSMessageTransformer;
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

    public function __construct(
        HttpClientInterface $httpClient
    ) {
        $this->guraasGameId = Uuid::fromString('3318cf30-f78d-4284-b530-a329005c248a');
        $this->guraasRequestTransformer = new GURaaSMessageTransformer(null, null);
        $this->httpClient = $httpClient;
    }

    public function __invoke(AnalyticsMessageBase $message)
    {
        $requestBody = $this->guraasRequestTransformer->transformMessageToRequestBody($message);
        if (!$requestBody) {
            return;
        }

        $result = $this->postRequestToGURaaS($requestBody);
    }

    private function postRequestToGURaaS($requestBody) : bool
    {
        if (!$requestBody) {
            return false;
        }

        $url = "https://grg.service.guraas.com/v1/games/$this->guraasGameId/data";
        try {
            $response = $this->httpClient->request('POST', $url, ['json' => $requestBody]);
            $statusCode = $response->getStatusCode();
            return $statusCode == 201;
        } catch (TransportExceptionInterface $e) {
            //TODO: figure out how to log errors/exceptions
        }
        return false;
    }
}
