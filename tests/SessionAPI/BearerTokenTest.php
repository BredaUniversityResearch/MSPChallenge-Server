<?php

namespace App\Tests\SessionAPI;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BearerTokenTest extends WebTestCase
{

    private const SESSION_ID = 1;
    private KernelBrowser $client;
    private ?string $access_token;

    private ?string $refresh_token;

    public function testGameState(): void
    {
        $this->client = static::createClient();
        // @todo: add check that there is a game session with ID 1, and that it's a North Sea edition
        $this->obtainAPIToken();
        $this->assertNotNull($this->access_token);
        $this->assertNotNull($this->refresh_token);
        $this->requestMSPEndpoint('POST', 'Game/State', ['state' => 'pause']);
        $this->assertMSPServerSuccessResponse();
    }
    public function testLayerRetrieval(): void
    {
        $this->client = static::createClient();
        // @todo: add check that there is a game session with ID 1, and that it's a North Sea edition
        $this->obtainAPIToken();
        $this->assertNotNull($this->access_token);
        $this->assertNotNull($this->refresh_token);
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1], false);
        $this->assertResponseStatusCodeSame(401);
        $this->requestMSPEndpoint('POST', 'Game/Config', [], false);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $this->requestMSPEndpoint('POST', 'Layer/MetaByName', ['name' => 'NS_EEZ'], false);
        $this->assertMSPServerSuccessWithPayloadResponse();
        // this tests a partial Authorization header
        $accessToken = $this->access_token;
        $this->access_token = '';
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1]);
        $this->assertResponseStatusCodeSame(401);
        // end of partial Authorization header test
        // this tests confusing access and refresh tokens
        $refreshToken = $this->refresh_token;
        $this->access_token = $refreshToken;
        $this->refresh_token = $accessToken;
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1]);
        $this->assertResponseStatusCodeSame(401);
        $this->access_token = $accessToken;
        $this->refresh_token = $refreshToken;
        // end of confusion access and refresh tokens test
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 1]);
        $this->assertMSPServerSuccessWithPayloadResponse();
        $this->requestMSPEndpoint('POST', 'Layer/Get', ['layer_id' => 2]);
        $this->assertMSPServerSuccessWithPayloadResponse();
        // this endpoint is required to make sure MSW/MEL/SEL/CEL/... can also communicate with the session API
        $this->requestMSPEndpoint('POST', 'Simulations/GetWatchdogTokenForServer', [], false);
        $this->assertMSPServerSuccessWithSpecificPayloadStringsResponse(['watchdog_token']);
        // sleep required because otherwise there's a risk that the newly created tokens are identical to the old ones
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
        $this->setAccessAndRefreshTokens('api_access_token', 'api_refresh_token');
        $this->assertNotSame($oldAccessToken, $this->access_token, 'Access token was not renewed');
        $this->assertNotSame($oldRefreshToken, $this->refresh_token, 'Refresh token was not renewed');
        $this->requestMSPEndpoint('POST', 'Layer/get', ['layer_id' => 1]);
        $this->assertMSPServerSuccessWithPayloadResponse();
    }


    private function requestMSPEndpoint($method, $endPoint, $data = [], $useToken = true): void
    {
        $headers = $useToken ? ['HTTP_AUTHORIZATION' => 'Bearer '.$this->access_token] : [];
        $this->client->request(
            $method,
            sprintf('/%d/api/%s', self::SESSION_ID, $endPoint),
            $data,
            [],
            $headers
        );
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
        $this->setAccessAndRefreshTokens('api_access_token', 'api_refresh_token');
    }

    private function setAccessAndRefreshTokens(
        string $accessTokenStringName,
        ?string $refreshTokenStringName = null
    ): void {
        $responseArr = json_decode($this->client->getResponse()->getContent(), true);
        $this->access_token = $responseArr['payload'][$accessTokenStringName] ?? null;
        if (!is_null($refreshTokenStringName)) {
            $this->refresh_token = $responseArr['payload'][$refreshTokenStringName] ?? null;
        }
    }

    private function assertMSPServerResponse(): array
    {
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $responseJson = $this->client->getResponse()->getContent();
        $this->assertJson($responseJson);
        $returnArr = json_decode($responseJson, true);
        $this->assertArrayHasKey('success', $returnArr);
        $this->assertArrayHasKey('message', $returnArr);
        $this->assertArrayHasKey('payload', $returnArr);
        return $returnArr;
    }

    private function assertMSPServerSuccessResponse(): array
    {
        $responseArr = $this->assertMSPServerResponse();
        $this->assertSame(
            true,
            $responseArr['success'],
            "{$this->client->getRequest()->getUri()} was valid, but returned success false, with message".
            PHP_EOL."{$responseArr['message']}."
        );
        return $responseArr;
    }

    private function assertMSPServerSuccessWithPayloadResponse(): array
    {
        $responseArr = $this->assertMSPServerSuccessResponse();
        $this->assertNotNull(
            $responseArr['payload'],
            "{$this->client->getRequest()->getUri()} was valid, but payload was unexpectedly null."
        );
        return $responseArr;
    }

    private function assertMSPServerSuccessWithSpecificPayloadStringsResponse(array $payloadVariables): void
    {
        $responseArr = $this->assertMSPServerSuccessWithPayloadResponse();
        foreach ($payloadVariables as $payloadVariable) {
            $this->assertArrayHasKey(
                $payloadVariable,
                $responseArr['payload'],
                "{$this->client->getRequest()->getUri()} was valid, but expected payload was missing."
            );
            $this->assertNotNull(
                $responseArr['payload'][$payloadVariable],
                "{$this->client->getRequest()->getUri()} was valid, but expected payload was null."
            );
        }
    }
}
