<?php

namespace App\Domain\Communicator;

use App\Entity\Layer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoServerCommunicator extends AbstractCommunicator
{

    public function __construct(
        HttpClientInterface $client
    ) {
        $this->client = $client;
    }

    /**
     * @param string $endPoint
     * @param bool $asArray
     * @return string|array
     */
    public function getResource(string $endPoint, bool $asArray = true): string|array
    {
        if (is_null($this->getUsername()) || is_null($this->getPassword()) || is_null($this->getBaseURL())) {
            return [];
        }

        return $this->call(
            'GET',
            $endPoint,
            [],
            [],
            $asArray
        );
    }

    /**
     * @param string $workspace
     * @param string $layerName
     * @return array
     * @throws \Exception
     */
    public function getRasterMetaData(string $workspace, string $layerName): array
    {
        $metaCheckType = $this->getResource("rest/workspaces/" . $workspace . "/layers/" . $layerName);
        $layerType = $metaCheckType['layer']['type'] ?? throw new \Exception(
            "Layer type (raster or WMS store) could not be ascertained, so cannot continue with ".$layerName
        );
        switch ($layerType) {
            case "RASTER":
                $meta = $this->getResource(
                    'rest/workspaces/'.
                    $workspace.
                    '/coverages/'.
                    $layerName.
                    '.json'
                );
                $bb = $meta['coverage']['nativeBoundingBox'] ??
                throw new \Exception(
                    "Native bounding box could not be ascertained for local raster layer " . $layerName
                );
            break;
            case "WMS":
                $meta = $this->getResource(
                    'rest/workspaces/'.
                    $workspace.
                    '/wmslayers/'.
                    $layerName
                );
                $bb = $meta['wmsLayer']['nativeBoundingBox'] ??
                throw new \Exception(
                    "Native bounding box could not be ascertained for WMS raster layer " . $layerName
                );
                break;
            default:
                throw new \Exception(
                    "Layer ".$layerName." returned an unsupported type ".$metaCheckType['layer']['type']
                );
        }
        return [
            "url" => $layerName.".png",
            "boundingbox" => [
                [$bb['minx'], $bb['miny']],
                [$bb['maxx'], $bb['maxy']]
            ]
        ];
    }

    /**
     * @param string $workspace
     * @param array $layerMetaData
     * @param array $rasterMetaData
     * @return string
     * @throws \Exception
     */
    public function getRasterDataThroughMetaData(
        string $workspace,
        Layer $layer,
        array $rasterMetaData
    ): string {
        $deltaSizeX = $rasterMetaData["boundingbox"][1][0] - $rasterMetaData["boundingbox"][0][0];
        $deltaSizeY = $rasterMetaData["boundingbox"][1][1] - $rasterMetaData["boundingbox"][0][1];
        $widthRatioMultiplier = $deltaSizeX / $deltaSizeY;

        if (empty($layer->getLayerHeight())) {
            throw new \Exception('Missing required "layer_height" in layer data');
        }

        $width = $layer->getLayerHeight() * $widthRatioMultiplier;
        $bounds = $rasterMetaData["boundingbox"][0][0].",".$rasterMetaData["boundingbox"][0][1].",".
            $rasterMetaData["boundingbox"][1][0].",".$rasterMetaData["boundingbox"][1][1];
        return $this->getResource(
            $workspace.
            "/wms/reflect?layers=".
            $workspace.
            ":".
            $layer->getLayerName().
            "&format=image/png&transparent=FALSE".
            "&width=".round($width).
            "&height=".$layer->getLayerHeight().
            "&bbox=".$bounds,
            false
        );
    }

    /**
     * @param string $workspace
     * @param string $layerName
     * @return array
     * @throws \Exception
     */
    public function getLayerDescription(string $workspace, string $layerName): array
    {
        $response = $this->getResource(
            "/ows?service=WMS&version=1.1.1&request=DescribeLayer&layers=".
            $workspace.
            ":".
            $layerName.
            "&outputFormat=application/json"
        );
        return $response["layerDescriptions"]
            ?? throw new \Exception('Could not obtain layer description from GeoServer.');
    }

    /**
     * @param string $layerName
     * @return array
     */
    public function getLayerGeometry(string $layerName): array
    {
        return $this->getResource(
            "/ows?service=WFS&version=1.0.0&outputFormat=json&request=GetFeature&typeName=".
            $layerName.
            "&maxFeatures=1000000"
        );
    }
}
