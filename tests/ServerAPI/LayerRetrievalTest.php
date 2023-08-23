<?php

namespace App\Tests\ServerAPI;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LayerRetrievalTest extends WebTestCase
{

    private const SESSION_ID = 6;
    private const LEGACY_SWITCH = true;
    private KernelBrowser $client;
    private ?int $token;

    public function testLayerRetrieval(): void
    {
        $this->client = static::createClient();
        $this->requestMSPEndpoint('POST', 'Layer/get', ['layer_id' => 1], false);
        $this->assertResponseStatusCodeSame(401);
        $this->requestMSPEndpoint('POST', 'Layer/get', ['layer_id' => 1]);
        $this->assertMSPServerSuccessWithPayloadResponse();
    }


    private function requestMSPEndpoint($method, $endPoint, $data = [], $useToken = true): void
    {
        $headers = $useToken ? [
            self::LEGACY_SWITCH ? 'HTTP_MSP_API_TOKEN' : 'HTTP_AUTHORIZATION' => $this->getToken()
        ] : [];
        $this->client->request(
            $method,
            sprintf('/%d/api/%s', self::SESSION_ID, $endPoint),
            $data,
            [],
            $headers
        );
    }

    private function getToken(): ?int
    {
        if (empty($this->token)) {
            $this->obtainAPIToken();
        }
        return $this->token;
    }

    private function obtainAPIToken(): void
    {
        $this->requestMSPEndpoint(
            'POST',
            'User/RequestSession',
            [
                'build_timestamp' => '2023-06-01 00:00:00',
                'country_id' => 3,
                'user_name' => 'test'
            ],
            false
        );
        $this->assertMSPServerSuccessWithPayloadResponse();
        $responseArr = json_decode($this->client->getResponse()->getContent(), true);
        $this->token = $responseArr['payload']['api_access_token'] ?? null;
    }

    private function assertMSPServerResponse(): void
    {
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $responseJson = $this->client->getResponse()->getContent();
        $this->assertJson($responseJson);
        $returnArr = json_decode($responseJson, true);
        $this->assertArrayHasKey('success', $returnArr);
        $this->assertArrayHasKey('message', $returnArr);
        $this->assertArrayHasKey('payload', $returnArr);
    }

    private function assertMSPServerSuccessResponse(): void
    {
        $this->assertMSPServerResponse();
        $responseArr = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(
            true,
            $responseArr['success'],
            "{$this->client->getRequest()->getUri()} was valid, but returned success false, with message".
            PHP_EOL."{$responseArr['message']}."
        );
    }

    private function assertMSPServerSuccessWithPayloadResponse(): void
    {
        $this->assertMSPServerSuccessResponse();
        $responseArr = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotNull(
            $responseArr['payload'],
            "{$this->client->getRequest()->getUri()} was valid, but payload was unexpectedly null."
        );
    }
}
