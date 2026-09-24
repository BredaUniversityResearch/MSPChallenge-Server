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
    /**
     * CRS codes (normalized to "EPSG:xxxx") known to declare their axis order as
     * (Northing, Easting) rather than the usual (Easting, Northing) — this affects how
     * an ows:LowerCorner/UpperCorner pair from a WCS DescribeCoverage response must be
     * read. EPSG:3035 (ETRS89-LAEA Europe) is a documented case of this. Only add a code
     * here once its axis order has actually been confirmed against real DescribeCoverage
     * output — the default for anything not listed is natural (Easting, Northing) order,
     * which is what most regional/projected CRSes (e.g. EPSG:5629) use.
     */
    private const NORTHING_FIRST_CRS = ['EPSG:3035'];

    private ?int $downloadsCacheLifetime = null;
    private ?int $resultsCacheLifetime = null;
    /** @var array<string, string> */
    private array $wmsCapabilitiesXmlByWorkspace = [];
    /** @var array<string, array<string, array<string, float>>> */
    private array $wmsBoundingBoxByWorkspaceAndLayer = [];
    private string $exchangeLogDir;
    private bool $exchangeLoggingEnabled;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly VersionsProvider $versionsProvider,
        private readonly ?CacheInterface $downloadsCache = null,
        private readonly ?CacheInterface $resultsCache = null
    ) {
        parent::__construct($httpClient);
        $this->setCacheLifeTimeDefaults();
        $this->exchangeLogDir = rtrim($_ENV['GEO_SERVER_LOG_DIR'] ?? 'var/geoserver_logs', '/');
        // Off unless explicitly enabled — undefined/empty/"0"/"false" all mean disabled.
        $this->exchangeLoggingEnabled = filter_var(
            $_ENV['GEO_SERVER_LOG_ENABLED'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Writes the request and response of a GeoServer call to a local log file whose name is
     * derived from the endpoint, so exchanges are easy to find by the resource they hit.
     * Binary responses (images, etc.) are saved as a separate file with a detected extension,
     * sitting alongside the log file (same base name), rather than being inlined as text.
     * No-op unless the GEO_SERVER_LOG_ENABLED env var is set to a truthy value.
     *
     * @param string $method
     * @param string $endPoint
     * @param array $requestHeaders
     * @param string|array $response
     */
    private function logExchange(string $method, string $endPoint, array $requestHeaders, string|array $response): void
    {
        if (!$this->exchangeLoggingEnabled) {
            return;
        }

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
        if (is_null($this->getBaseURL())) {
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

        $rawOwsURL = (string) ($description['owsURL'] ?? '');
        // Strip everything up to and including 'geoserver/' to get a relative URL
        $owsURL = $rawOwsURL !== '' ? preg_replace('#^.*geoserver/#', '', $rawOwsURL) : '';
        $owsType = strtoupper((string) ($description['owsType'] ?? ''));
        $typeName = $description['typeName'] ?? "{$workspace}:{$layerName}";

        // Step 2: fetch bounding box via the appropriate OGC describe operation
        $bb = match ($owsType) {
            'WCS' => $this->getBoundingBoxFromWCS($owsURL, $typeName, $workspace, $layerName, $cacheLifetime),
            // Some GeoServer setups return empty owsType/owsURL for raster WMS layers.
            'WMS', '' => $this->getBoundingBoxFromWMSCapabilities($workspace, $layerName, $cacheLifetime),
            default => throw new Exception(
                "Unsupported owsType '{$owsType}' for layer {$layerName}"
            ),
        };

        return [
            "url" => "{$layerName}.png",
            "boundingbox" => [
                [$bb['minx'], $bb['miny']],
                [$bb['maxx'], $bb['maxy']]
            ],
            "srs" => $bb['srs'] ?? null,
        ];
    }

    /**
     * Normalizes a CRS/SRS identifier (which may come as a bare code like "EPSG:3035" or a
     * URN form like "urn:ogc:def:crs:EPSG::3035") into the plain "EPSG:xxxx" form expected by
     * WMS GetMap/reflect requests.
     *
     * @param string|null $crs
     * @return string|null
     */
    private function normalizeCrsIdentifier(?string $crs): ?string
    {
        if ($crs === null) {
            return null;
        }
        if (preg_match('/EPSG[:]{1,2}(\d+)/i', $crs, $matches)) {
            return 'EPSG:' . $matches[1];
        }
        if (preg_match('/CRS[:]{1,2}84/i', $crs) || stripos($crs, 'CRS84') !== false) {
            return 'CRS:84';
        }
        return $crs;
    }

    /**
     * Whether a CRS/SRS identifier refers to a geographic (degree-based) CRS such as
     * EPSG:4326 or CRS:84/CRS84 — including their URN forms, e.g.
     * "urn:ogc:def:crs:EPSG::4326" or "urn:ogc:def:crs:OGC:1.3:CRS84". A short-form exact
     * match isn't enough: GeoServer can advertise the very same geographic CRS as either a
     * bare code or a full URN depending on the layer/version, and both must be rejected as
     * "native" bounding boxes since their values are in degrees, not the layer's projected
     * units.
     *
     * @param string|null $crs
     * @return bool
     */
    private function isGeographicCrs(?string $crs): bool
    {
        if ($crs === null) {
            return true;
        }
        return (bool) preg_match('/(EPSG[:]{1,2}4326|CRS[:]{1,2}84|CRS84)/i', $crs);
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

        // Reject geographic (degree-based) bounding boxes. DescribeCoverage responses list
        // a CRS84 bbox alongside the layer's actual projected/native one — often the CRS84
        // one first — and a WMS reflect bbox= needs the projected values, not degrees. Do
        // not assume any particular projected EPSG code (different regions/layers use
        // different native CRSes).
        $bboxNode = $crawler->filterXPath('//ows:BoundingBox')
            ->reduce(function (Crawler $node) {
                return !$this->isGeographicCrs($node->attr('crs'));
            })
            ->first();

        if (!$bboxNode->count()) {
            throw new Exception(
                "WCS DescribeCoverage returned no projected (non-geographic) BoundingBox for {$layerName}"
            );
        }

        $lower = preg_split('/\s+/', trim($bboxNode->filterXPath('.//ows:LowerCorner')->text()));
        $upper = preg_split('/\s+/', trim($bboxNode->filterXPath('.//ows:UpperCorner')->text()));

        if (count($lower) < 2 || count($upper) < 2) {
            throw new Exception(
                "WCS DescribeCoverage BoundingBox has unexpected format for {$layerName}"
            );
        }

        $srs = $this->normalizeCrsIdentifier($bboxNode->attr('crs'));

        // Per the OGC URN CRS convention, some CRSes (e.g. EPSG:3035) declare their axis
        // order as (Northing, Easting) rather than the usual (Easting, Northing), so the
        // corner components must be swapped for those specific, known cases only.
        $isNorthingFirst = $srs !== null && in_array($srs, self::NORTHING_FIRST_CRS, true);

        return [
            'minx' => (float) ($isNorthingFirst ? $lower[1] : $lower[0]),
            'miny' => (float) ($isNorthingFirst ? $lower[0] : $lower[1]),
            'maxx' => (float) ($isNorthingFirst ? $upper[1] : $upper[0]),
            'maxy' => (float) ($isNorthingFirst ? $upper[0] : $upper[1]),
            'srs' => $srs,
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
    private function getBoundingBoxFromWMSCapabilities(
        string $workspace,
        string $layerName,
        ?int $cacheLifetime
    ): array {
        $qualifiedLayerName = "{$workspace}:{$layerName}";

        // Fast path: re-use a bbox resolved earlier in this same PHP request.
        $cachedBoundingBoxes = $this->wmsBoundingBoxByWorkspaceAndLayer[$workspace] ?? [];
        if (isset($cachedBoundingBoxes[$qualifiedLayerName])) {
            return $cachedBoundingBoxes[$qualifiedLayerName];
        }
        if (isset($cachedBoundingBoxes[$layerName])) {
            return $cachedBoundingBoxes[$layerName];
        }

        // Re-use the (large) capabilities XML for this workspace within this request.
        if (!isset($this->wmsCapabilitiesXmlByWorkspace[$workspace])) {
            $this->wmsCapabilitiesXmlByWorkspace[$workspace] = $this->getResource(
                "{$workspace}/wms?service=WMS&version=1.1.1&request=GetCapabilities",
                false,
                new CacheItemConfig("WMSGetCapabilities~{$workspace}", $cacheLifetime)
            );
        }

        $xml = $this->wmsCapabilitiesXmlByWorkspace[$workspace];

        $crawler = new Crawler($xml);
        $candidateLayerNames = [$qualifiedLayerName, $layerName];
        $layerNode = null;
        $matchedLayerName = null;

        foreach ($candidateLayerNames as $candidateLayerName) {
            $candidateNode = $crawler->filterXPath(
                sprintf(
                    '//*[local-name()="Layer" and ./*[local-name()="Name" and normalize-space(text())="%s"]]',
                    $candidateLayerName
                )
            )->first();

            if ($candidateNode->count()) {
                $layerNode = $candidateNode;
                $matchedLayerName = $candidateLayerName;
                break;
            }
        }

        if ($layerNode === null || !$layerNode->count()) {
            throw new Exception(
                "WMS GetCapabilities returned no Layer entry for {$qualifiedLayerName} (or {$layerName})"
            );
        }

        // Geographic CRSes describe the bbox in degrees, not the layer's projected units.
        // Passing degree values straight into a WMS reflect bbox= (which expects the layer's
        // native units) distorts/misplaces the image, so a geographic BoundingBox must never
        // be accepted here as if it were the native one — regardless of which one happens to
        // appear first in the capabilities document, and regardless of whether GeoServer wrote
        // it as a bare code ("CRS:84") or a full URN ("urn:ogc:def:crs:OGC:1.3:CRS84").
        $bboxNode = $layerNode->filterXPath('.//*[local-name()="BoundingBox"]')
            ->reduce(function (Crawler $node) {
                $srs = $node->attr('SRS') ?? $node->attr('CRS');
                return !$this->isGeographicCrs($srs);
            })
            ->first();

        if (!$bboxNode->count()) {
            // Every BoundingBox for this layer (including LatLonBoundingBox, which per the
            // WMS spec is always EPSG:4326) is geographic — there is no projected box to use.
            throw new Exception(
                "WMS GetCapabilities returned no projected (non-geographic) BoundingBox for " .
                "{$qualifiedLayerName}; only geographic (lat/long) bounding boxes were found"
            );
        }

        $srs = $this->normalizeCrsIdentifier($bboxNode->attr('SRS') ?? $bboxNode->attr('CRS'));

        $minx = $bboxNode->attr('minx');
        $miny = $bboxNode->attr('miny');
        $maxx = $bboxNode->attr('maxx');
        $maxy = $bboxNode->attr('maxy');

        if ($minx === null || $miny === null || $maxx === null || $maxy === null) {
            throw new Exception("WMS BoundingBox has unexpected format for {$qualifiedLayerName}");
        }

        $bbox = [
            'minx' => (float) $minx,
            'miny' => (float) $miny,
            'maxx' => (float) $maxx,
            'maxy' => (float) $maxy,
            'srs' => $srs,
        ];

        // Store under both aliases to maximize in-request cache hits.
        $this->wmsBoundingBoxByWorkspaceAndLayer[$workspace][$qualifiedLayerName] = $bbox;
        $this->wmsBoundingBoxByWorkspaceAndLayer[$workspace][$layerName] = $bbox;
        if ($matchedLayerName !== null) {
            $this->wmsBoundingBoxByWorkspaceAndLayer[$workspace][$matchedLayerName] = $bbox;
        }

        return $bbox;
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
        // Always state the CRS the bbox values are in explicitly — relying on GeoServer's
        // implicit default (rather than a stated srs=) is what let a degree-based bbox get
        // misinterpreted as the layer's native units in the first place.
        if (!empty($rasterMetaData['srs'])) {
            $endPoint .= "&srs=" . urlencode($rasterMetaData['srs']);
        }
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
