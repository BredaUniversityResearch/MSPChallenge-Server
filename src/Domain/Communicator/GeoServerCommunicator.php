<?php

namespace App\Domain\Communicator;

use App\Domain\Common\CacheItemConfig;
use App\Entity\SessionAPI\Layer;
use App\VersionsProvider;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoServerCommunicator extends AbstractCommunicator
{
    private ?int $downloadsCacheLifetime = null;
    private ?int $resultsCacheLifetime = null;
    private string $exchangeLogDir;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly VersionsProvider $versionsProvider,
        private readonly ?CacheInterface $downloadsCache = null,
        private readonly ?CacheInterface $resultsCache = null
    ) {
        parent::__construct($httpClient);
        $this->setCacheLifeTimeDefaults();
        $this->exchangeLogDir = rtrim($_ENV['GEO_SERVER_LOG_DIR'] ?? 'var/geoserver_logs', '/');
    }

    /**
     * Writes the request and response of a GeoServer call to a local log file whose name is
     * derived from the endpoint, so exchanges are easy to find by the resource they hit.
     * Binary responses (images, etc.) are saved as a separate file with a detected extension,
     * sitting alongside the log file (same base name), rather than being inlined as text.
     *
     * @param string $method
     * @param string $endPoint
     * @param array $requestHeaders
     * @param string|array $response
     */
    private function logExchange(string $method, string $endPoint, array $requestHeaders, string|array $response): void
    {
        try {
            if (!is_dir($this->exchangeLogDir) && !mkdir($this->exchangeLogDir, 0775, true) &&
                !is_dir($this->exchangeLogDir)) {
                return;
            }

            // Turn the endpoint into a filesystem-safe, still-recognizable slug.
            $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $endPoint);
            $slug = trim($slug, '_');
            $slug = substr($slug, 0, 150) ?: 'root';

            $timestamp = (new \DateTimeImmutable())->format('Ymd_His_u');
            $baseName = "{$timestamp}__{$slug}";
            $filePath = "{$this->exchangeLogDir}/{$baseName}.log";

            $responseSection = '';
            if (is_array($response)) {
                $responseSection = json_encode($response, JSON_PRETTY_PRINT);
            } elseif ($this->looksBinary($response)) {
                $extension = $this->detectExtension($response);
                $binaryFileName = "{$baseName}.{$extension}";
                file_put_contents("{$this->exchangeLogDir}/{$binaryFileName}", $response);
                $responseSection = "[binary response, " . strlen($response) . " bytes, saved to {$binaryFileName}]";
            } else {
                $responseSection = $response;
            }

            $contents = "=== REQUEST ===\n"
                . "Method: {$method}\n"
                . "Endpoint: {$endPoint}\n"
                . "Headers: " . json_encode($requestHeaders, JSON_PRETTY_PRINT) . "\n"
                . "\n=== RESPONSE ===\n"
                . $responseSection . "\n";

            file_put_contents($filePath, $contents);
        } catch (\Throwable $e) {
            // Logging must never break the actual GeoServer call.
        }
    }

    /**
     * Heuristic check for whether a string response is binary data rather than plain text.
     *
     * @param string $data
     * @return bool
     */
    private function looksBinary(string $data): bool
    {
        if ($data === '') {
            return false;
        }
        // A NUL byte, or a high proportion of non-printable characters, indicates binary content.
        if (str_contains($data, "\0")) {
            return true;
        }
        $sample = substr($data, 0, 1000);
        $nonPrintable = preg_match_all('/[^\x20-\x7E\t\r\n]/', $sample);
        return $nonPrintable > (strlen($sample) * 0.3);
    }

    /**
     * Detects a file extension for binary response data using its MIME type, falling back
     * to "bin" if it cannot be determined.
     *
     * @param string $data
     * @return string
     */
    private function detectExtension(string $data): string
    {
        $mimeToExtension = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/tiff' => 'tiff',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/gzip' => 'gz',
        ];

        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = finfo_buffer($finfo, $data) ?: null;
                finfo_close($finfo);
            }
        }

        return $mimeToExtension[$mimeType] ?? 'bin';
    }

    /**
     * @param int|null $downloadsCacheLifetime The default lifetime of the file cache in seconds.
     *   If null, caching is disabled. 0 = infinite.
     * @param int|null $resultsCacheLifetime The default lifetime of the result cache in seconds.
     *   If null, caching is disabled. 0 = infinite.
     * @return GeoServerCommunicator
     */
    public function setCacheLifeTimeDefaults(
        ?int $downloadsCacheLifetime = null,
        ?int $resultsCacheLifetime = null
    ): self {
        $this->downloadsCacheLifetime = $downloadsCacheLifetime ?? $_ENV['GEO_SERVER_DOWNLOADS_CACHE_LIFETIME'] ?? null;
        $this->resultsCacheLifetime = $resultsCacheLifetime ?? $_ENV['GEO_SERVER_RESULTS_CACHE_LIFETIME'] ?? null;
        return $this;
    }

    /**
     * @param string $endPoint
     * @param bool $asArray
     * @param CacheItemConfig|null $cacheItemConfig
     * @return string|array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function getResource(
        string $endPoint,
        bool $asArray = true,
        ?CacheItemConfig $cacheItemConfig = null
    ): string|array {
        if (is_null($this->getUsername()) || is_null($this->getPassword()) || is_null($this->getBaseURL())) {
            return [];
        }

        // Do not use cache at all
        $cacheLifetime = $cacheItemConfig?->getLifeTime() ?? $this->resultsCacheLifetime;
        if ($this->resultsCache === null || // there is no cache pool
            $cacheItemConfig === null || // no cache item config, so no cache
            $cacheLifetime === null) { // cache lifetime is null, so disabled.
            $headers = ['Msp-Server-Version' => $this->versionsProvider->getVersion()];
            $response = $this->call('GET', $endPoint, [], $headers, $asArray);
            $this->logExchange('GET', $endPoint, $headers, $response);
            return $response;
        }

        // Try to use cache
        return $this->resultsCache->get(
            $cacheItemConfig->getKey(),
            function (ItemInterface $item) use ($endPoint, $asArray, $cacheLifetime) {
                // update cache
                if ($cacheLifetime > 0) {
                    $item->expiresAfter($cacheLifetime);
                }
                $headers = ['Msp-Server-Version' => $this->versionsProvider->getVersion()];
                $response = $this->call('GET', $endPoint, [], $headers, $asArray);
                $this->logExchange('GET', $endPoint, $headers, $response);
                return $response;
            },
            0
        );
    }

    /**
     * @param string $workspace
     * @param string $layerName
     * @param int|null $cacheLifetime The lifetime of the cache in seconds.
     *   If null, default values are used, see setCacheLifeTimeDefaults(). 0 = infinite.
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getRasterMetaData(string $workspace, string $layerName, ?int $cacheLifetime = null): array
    {
        $metaCheckType = $this->getResource(
            "rest/workspaces/${workspace}/layers/${layerName}",
            true,
            new CacheItemConfig("layers~${workspace}~${layerName}", $cacheLifetime)
        );
        $layerType = $metaCheckType['layer']['type'] ?? throw new \Exception(
            "Layer type (raster or WMS store) could not be ascertained, so cannot continue with ${layerName}"
        );
        switch ($layerType) {
            case "RASTER":
                $meta = $this->getResource(
                    "rest/workspaces/${workspace}/coverages/${layerName}.json",
                    true,
                    new CacheItemConfig("coverages~${workspace}~${layerName}", $cacheLifetime)
                );
                $bb = $meta['coverage']['nativeBoundingBox'] ??
                throw new \Exception(
                    "Native bounding box could not be ascertained for local raster layer ${layerName}"
                );
            break;
            case "WMS":
                $meta = $this->getResource(
                    "rest/workspaces/${workspace}/wmslayers/${layerName}",
                    true,
                    new CacheItemConfig("wmslayers~${workspace}~${layerName}", $cacheLifetime)
                );
                $bb = $meta['wmsLayer']['nativeBoundingBox'] ??
                throw new \Exception(
                    "Native bounding box could not be ascertained for WMS raster layer ${layerName}"
                );
                break;
            default:
                throw new \Exception(
                    "Layer ${layerName} returned an unsupported type {$metaCheckType['layer']['type']}"
                );
        }
        return [
            "url" => "${layerName}.png",
            "boundingbox" => [
                [$bb['minx'], $bb['miny']],
                [$bb['maxx'], $bb['maxy']]
            ]
        ];
    }

    /**
     * @param string $workspace
     * @param Layer $layer
     * @param array $rasterMetaData
     * @param int|null $cacheLifetime The lifetime of the cache in seconds.
     *   If null, default values are used, see setCacheLifeTimeDefaults(). 0 = infinite.
     * @return string
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getRasterDataByMetaData(
        string $workspace,
        Layer $layer,
        array $rasterMetaData,
        ?int $cacheLifetime = null
    ): string {
        $deltaSizeX = $rasterMetaData["boundingbox"][1][0] - $rasterMetaData["boundingbox"][0][0];
        $deltaSizeY = $rasterMetaData["boundingbox"][1][1] - $rasterMetaData["boundingbox"][0][1];
        $widthRatioMultiplier = $deltaSizeX / $deltaSizeY;

        if (empty($layer->getLayerHeight())) {
            throw new \Exception('Missing required "layer_height" in layer data');
        }

        $width = round($layer->getLayerHeight() * $widthRatioMultiplier);
        $bounds = $rasterMetaData["boundingbox"][0][0].",".$rasterMetaData["boundingbox"][0][1].",".
            $rasterMetaData["boundingbox"][1][0].",".$rasterMetaData["boundingbox"][1][1];

        $endPoint = "${workspace}/wms/reflect?layers=${workspace}:{$layer->getLayerName()}&format=image/png".
            "&transparent=FALSE&width=${width}&height={$layer->getLayerHeight()}&bbox=${bounds}";
        // Do not use cache at all
        $cacheLifetime ??= $this->downloadsCacheLifetime;
        if ($this->downloadsCache === null || $cacheLifetime === null) {
            return $this->getResource(
                $endPoint,
                false,
                null // never use result cache, as it is too large for in-memory
            );
        }

        // Try to use cache
        return $this->downloadsCache->get(
            "reflect~${workspace}~{$layer->getLayerName()}",
            function (ItemInterface $item) use ($endPoint, $cacheLifetime) {
                // update cache
                if ($cacheLifetime > 0) {
                    $item->expiresAfter($cacheLifetime);
                }
                return $this->getResource(
                    $endPoint,
                    false,
                    null // never use result cache, as it is too large for in-memory
                );
            },
            0
        );
    }

    /**
     * @param string $workspace
     * @param string $layerName
     * @param int|null $cacheLifetime The lifetime of the cache in seconds.
     *   If null, default values are used, see setCacheLifeTimeDefaults(). 0 = infinite.
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getLayerDescription(string $workspace, string $layerName, ?int $cacheLifetime = null): array
    {
        $response = $this->getResource(
            "ows?service=WMS&version=1.1.1&request=DescribeLayer&layers=${workspace}:${layerName}".
            "&outputFormat=application/json",
            true,
            new CacheItemConfig("DescribeLayer~${workspace}~${layerName}", $cacheLifetime)
        );
        return $response["layerDescriptions"]
            ?? throw new \Exception('Could not obtain layer description from GeoServer.');
    }

    /**
     * @param string $layerName
     * @param int|null $cacheLifetime The lifetime of the cache in seconds.
     *   If null, default values are used, see setCacheLifeTimeDefaults(). 0 = infinite.
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getLayerGeometryFeatures(string $layerName, ?int $cacheLifetime = null): array
    {
        return $this->getResource(
            "ows?service=WFS&version=1.0.0&outputFormat=json&request=GetFeature&typeName=${layerName}".
            "&maxFeatures=1000000",
            true,
            new CacheItemConfig("GetFeature~${layerName}", $cacheLifetime)
        );
    }
}
