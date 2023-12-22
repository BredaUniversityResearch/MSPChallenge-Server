<?php

namespace App\MessageHandler\Analytics;

use Exception;
use App\Message\Analytics\AnalyticsMessageBase;
use App\MessageTransformer\Analytics\GURaaSMessageTransformer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;
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
        string $guraasAnalyticsVersion,
    ) {
        $this->guraasGameId = Uuid::fromString($guraasGameId);
        $this->guraasRequestTransformer = new GURaaSMessageTransformer($logger, $guraasAnalyticsVersion);
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
        if (!$result) {
            throw new RecoverableMessageHandlingException();
            //TODO: decide if always wanna try forever on failure to POST.
        }
    }

    /**
     * @throws Exception
     */
    private function postRequestToGURaaS($requestBody) : bool
    {
        if (!Uuid::isValid($this->guraasGameId)) {
            return false;
        }

        if (!$requestBody) {
            $this->logger->error('No body supplied for GURaaS POST request!');
            return false;
        }

        $url = "https://grg.service.guraas.com/v1/games/{$this->guraasGameId}/data";

        try {
            $response = $this->httpClient->request('POST', $url, ['json' => $requestBody]);
            $statusCode = $response->getStatusCode();
            return $statusCode == 201;
        } catch (Exception $e) {
            $this->logGURaaSPostRequestException($e, $url, $requestBody);
            throw $e;
        }
    }

    private function logGURaaSPostRequestException(
        Exception $exception,
        $requestUrl,
        $requestBody,
    ) {
        $this->logger->error(
            "Exception occured during POST request to GURaaS.".
            "\n Request URI: ". $requestUrl.
            "\n Request Body: ".json_encode($requestBody).
            "\n Exception: ".$exception->getMessage()
        );
    }
}
