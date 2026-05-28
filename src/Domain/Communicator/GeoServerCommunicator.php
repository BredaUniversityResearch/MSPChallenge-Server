<?php

namespace App\Domain\Communicator;

use App\Domain\Common\CacheItemConfig;
use App\Entity\SessionAPI\Layer;
use App\VersionsProvider;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;
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

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly VersionsProvider $versionsProvider,
        private readonly ?CacheInterface $downloadsCache = null,
        private readonly ?CacheInterface $resultsCache = null
    ) {
        parent::__construct($httpClient);
        $this->setCacheLifeTimeDefaults();
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
            return $this->call(
                'GET',
                $endPoint,
                [],
                ['Msp-Server-Version' => $this->versionsProvider->getVersion()],
                $asArray
            );
        }

        // Try to use cache
        return $this->resultsCache->get(
            $cacheItemConfig->getKey(),
            function (ItemInterface $item) use ($endPoint, $asArray, $cacheLifetime) {
                // update cache
                if ($cacheLifetime > 0) {
                    $item->expiresAfter($cacheLifetime);
                }
                return $this->call(
                    'GET',
                    $endPoint,
                    [],
                    ['Msp-Server-Version' => $this->versionsProvider->getVersion()],
                    $asArray
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
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function getRasterMetaData(string $workspace, string $layerName, ?int $cacheLifetime = null): array
    {
        // Step 1: DescribeLayer to get owsURL and owsType (JSON, works for non-admin users)
        $describeLayer = $this->getResource(
            "ows?service=WMS&version=1.1.1&request=DescribeLayer&layers={$workspace}:{$layerName}".
            '&outputFormat=application/json',
            true,
            new CacheItemConfig("DescribeLayer~{$workspace}~{$layerName}", $cacheLifetime)
        );

        $layerDescriptions = $describeLayer['layerDescriptions'] ?? throw new Exception(
            "DescribeLayer returned no layerDescriptions for {$layerName}"
        );
        $description = $layerDescriptions[0] ?? throw new Exception(
            "DescribeLayer returned empty layerDescriptions for {$layerName}"
        );

        $rawOwsURL = $description['owsURL'] ?? throw new \Exception(
            "DescribeLayer returned no owsURL for {$layerName}"
        );
        // Strip everything up to and including 'geoserver/' to get a relative URL
        $owsURL = preg_replace('#^.*geoserver/#', '', $rawOwsURL);
        $owsType = strtoupper($description['owsType'] ?? '');
        $typeName = $description['typeName'] ?? "{$workspace}:{$layerName}";

        // Step 2: fetch bounding box via the appropriate OGC describe operation
        $bb = match ($owsType) {
            'WCS' => $this->getBoundingBoxFromWCS($owsURL, $typeName, $workspace, $layerName, $cacheLifetime),
            default => throw new Exception(
                "Unsupported owsType '{$owsType}' for layer {$layerName}"
            ),
        };

        return [
            "url" => "{$layerName}.png",
            "boundingbox" => [
                [$bb['minx'], $bb['miny']],
                [$bb['maxx'], $bb['maxy']]
            ]
        ];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws InvalidArgumentException
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    private function getBoundingBoxFromWCS(
        string $owsURL,
        string $typeName,
        string $workspace,
        string $layerName,
        ?int $cacheLifetime
    ): array {
        $url = $owsURL . "service=WCS&version=1.1.1&request=DescribeCoverage&identifiers=" . urlencode($typeName);

        $xml = $this->getResource(
            $url,
            false,
            new CacheItemConfig("WCSDescribeCoverage~{$workspace}~{$layerName}", $cacheLifetime)
        );

        $crawler = new Crawler($xml);

        // Prefer the native/projected EPSG:3035 bbox, allowing for slight CRS string variations
        $bboxNode = $crawler->filterXPath(
            '//ows:BoundingBox[contains(@crs, "EPSG::3035") or contains(@crs, "EPSG:3035")]'
        )->first();

        if (!$bboxNode->count()) {
            // Fall back to any bbox if no EPSG:3035 bbox is found
            $bboxNode = $crawler->filterXPath('//ows:BoundingBox')->first();
        }

        if (!$bboxNode->count()) {
            throw new Exception(
                "WCS DescribeCoverage returned no BoundingBox for {$layerName}"
            );
        }

        $lower = preg_split('/\s+/', trim($bboxNode->filterXPath('.//ows:LowerCorner')->text()));
        $upper = preg_split('/\s+/', trim($bboxNode->filterXPath('.//ows:UpperCorner')->text()));

        if (count($lower) < 2 || count($upper) < 2) {
            throw new Exception(
                "WCS DescribeCoverage BoundingBox has unexpected format for {$layerName}"
            );
        }

        return [
            'minx' => (float) $lower[1],
            'miny' => (float) $lower[0],
            'maxx' => (float) $upper[1],
            'maxy' => (float) $upper[0],
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
            throw new Exception('Missing required "layer_height" in layer data');
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
            ?? throw new Exception('Could not obtain layer description from GeoServer.');
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
