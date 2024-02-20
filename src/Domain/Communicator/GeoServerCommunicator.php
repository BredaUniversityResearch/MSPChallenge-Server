<?php

namespace App\Domain\Communicator;

use App\Domain\Common\CacheItemConfig;
use App\Entity\Layer;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeoServerCommunicator extends AbstractCommunicator
{
    private ?int $downloadsCacheLifetime = null;
    private ?int $resultsCacheLifetime = null;

    public function __construct(
        HttpClientInterface $httpClient,
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
            return $this->call('GET', $endPoint, [], [], $asArray);
        }

        // Try to use cache
        return $this->resultsCache->get(
            $cacheItemConfig?->getKey() ?? md5($endPoint),
            function (ItemInterface $item) use ($endPoint, $asArray, $cacheLifetime) {
                // update cache
                if ($cacheLifetime > 0) {
                    $item->expiresAfter($cacheLifetime);
                }
                return $this->call('GET', $endPoint, [], [], $asArray);
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
