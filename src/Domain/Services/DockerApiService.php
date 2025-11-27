<?php

namespace App\Domain\Services;

use App\Entity\ServerManager\DockerApi;
use Exception;
use Psr\Log\LoggerInterface;

readonly class DockerApiService
{
    public function __construct(
        private LoggerInterface $dockerLogger
    ) {
    }

    /**
     * @throws Exception
     */
    public function dockerApiCall(DockerApi $dockerApi, string $method, string $path, array $options = []): ?array
    {
        // Build the query string
        $queryString = http_build_query($options['query'] ?? []);
        $fullUrl = $dockerApi->createUrl() . $path . ($queryString ? '?' . $queryString : '');

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        $address = $dockerApi->getAddress();
        // if the request will go through the Unix socket, the address + port will be ignored,
        //   but the URL is still needed
        if ($address === 'localhost' || $address === '127.0.0.1') {
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/docker.sock');
        }
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream the response directly
        $responseContent = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$responseContent) {
            $this->dockerLogger->info($data);
            $responseContent .= $data;
            return strlen($data);
        });

        // Set the POST fields if there is json data
        if (!empty($options['json'] ?? [])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        // Execute the request
        curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('curl error: ' . $error);
        }

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL handle
        curl_close($ch);

        // Check for HTTP errors
        if ($httpCode >= 400) {
            throw new \RuntimeException('Docker API error: ' . $httpCode . ': ' . $responseContent);
        }

        return json_decode($responseContent, true);
    }
}
