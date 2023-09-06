<?php

namespace App\Tests\ServerAPI;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LayerRetrievalTest extends WebTestCase
{

    private const SESSION_ID = 1;
    private KernelBrowser $client;
    private ?string $access_token;

    private ?string $refresh_token;

    public function testLayerRetrieval(): void
    {
        $this->client = static::createClient();
        $this->obtainAPIToken();
        $this->assertNotNull($this->access_token);
        $this->assertNotNull($this->refresh_token);
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1], false);
        $this->assertResponseStatusCodeSame(401);
        $this->requestMSPEndpoint('POST', 'Game/Config', [], false);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $this->requestMSPEndpoint('POST', 'Layer/MetaByName', ['name' => 'NS_EEZ'], false);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $temp = $this->getToken();
        $this->access_token = '';
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1]);
        $this->assertResponseStatusCodeSame(401);
        $this->access_token = $temp;
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1]);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 2]);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 3]);
        $this->assertMSPServerSuccessWithPayloadResponse();
        // required because otherwise there's a risk that the newly created tokens are identical to the old ones
        sleep(1);
        $this->requestMSPEndpoint('POST', 'User/RequestToken', [], false);
        $this->assertResponseStatusCodeSame(500);
        $this->requestMSPEndpoint(
            'POST',
            'User/RequestToken',
            ['api_refresh_token' => $this->refresh_token],
            false
        );
        $this->assertMSPServerSuccessWithPayloadResponse();
        // reset access & refresh tokens, and check
        $oldAccessToken = $this->access_token;
        $oldRefreshToken = $this->refresh_token;
        $this->setAccessAndRefreshTokens();
        $this->assertNotSame($oldAccessToken, $this->access_token, 'Access token was not renewed');
        $this->assertNotSame($oldRefreshToken, $this->refresh_token, 'Refresh token was not renewed');
        $this->requestMSPEndpoint('POST', 'Layer/get', ['layer_id' => 1]);
        $this->assertMSPServerSuccessWithPayloadResponse();
    }


    private function requestMSPEndpoint($method, $endPoint, $data = [], $useToken = true): void
    {
        $headers = $useToken ? ['HTTP_AUTHORIZATION' => 'Bearer '.$this->getToken()] : [];
        $this->client->request(
            $method,
            sprintf('/%d/api/%s', self::SESSION_ID, $endPoint),
            $data,
            [],
            $headers
        );
    }

    private function getToken(): ?string
    {
        return $this->access_token;
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
        $this->setAccessAndRefreshTokens();
    }

    private function setAccessAndRefreshTokens(): void
    {
        $responseArr = json_decode($this->client->getResponse()->getContent(), true);
        $this->access_token = $responseArr['payload']['api_access_token'] ?? null;
        $this->refresh_token = $responseArr['payload']['api_refresh_token'] ?? null;
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
