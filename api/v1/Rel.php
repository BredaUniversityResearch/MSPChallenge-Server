<?php

namespace App\Domain\API\v1;

use Exception;

class Rel extends Base
{
    private const ALLOWED = array(
        "GetRestrictionGeometry",
        "GetConfiguration"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup REL
     * @throws Exception
     * @api {POST} /rel/GetRestrictionGeometry
     * @apiDescription Returns all restriction geometry that appears on a configured restriction layer.
     *   Geometry_type is translated into geometry type that corresponds to the type configured and communicated
     *   to Marin API.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetRestrictionGeometry(): array
    {
        //TODO: Check with Marin what  we should send here... Just windfarms or all of the geometry that SEL uses...
        $result = Database::GetInstance()->query(
            "SELECT geometry_id as geometry_id,
			geometry_geometry as geometry, 
			geometry_type as geometry_type
			FROM geometry
			WHERE geometry_layer_id IN (SELECT layer_id FROM layer WHERE layer_name LIKE ?)",
            array("NS_Wind_Farms_Implemented")
        );
        
        foreach ($result as &$val) {
            $val["geometry"] = json_decode($val["geometry"]);
        }
            
        return $result;
    }

    /**
     * @apiGroup REL
     * @throws Exception
     * @api {POST} /rel/GetConfiguration GetConfiguration
     * @apiDescription Returns object containing configuration values for REL.
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetConfiguration(): ?array
    {
        return $this->GetRELConfigValues();
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetRELConfigValues(): ?array
    {
        $game = new Game();
        $config = $game->GetGameConfigValues();
        if (isset($config["REL"])) {
            return $config["REL"];
        }
        return null;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function OnReimport(): void
    {
        $config = $this->GetRELConfigValues();
        if ($config === null) {
            self::Debug("REL config values not set, but reimport is called on REL class... Session will be all kinds of 
                broken when it comes to REL");
            return;
        }
        $this->SetupRasterLayer($config["contacts_output_layer"]);
        $this->SetupRasterLayer($config["collision_output_layer"]);
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function SetupRasterLayer(string $rasterLayerName): void
    {
        $rasterLayerData = [];
        $rasterLayerData["layer_name"] = $rasterLayerName;

        $existingLayer = Database::GetInstance()->query(
            "SELECT layer_id, layer_raster FROM layer WHERE layer_name = ?",
            array($rasterLayerData["layer_name"])
        );
        if (count($existingLayer) > 0) {
            $rasterData = json_decode($existingLayer[0]["layer_raster"], true);
        } else {
            $rasterData = $rasterLayerData;
        }

        $rasterData['layer_download_from_geoserver'] = false;
        $rasterData["url"] = $rasterLayerName.".png";

        $jsonRasterData = json_encode($rasterData);

        if (count($existingLayer) > 0) {
            Database::GetInstance()->query(
                "UPDATE layer SET layer_raster = ? WHERE layer_id = ?",
                array($jsonRasterData, $existingLayer[0]["layer_id"])
            );
        } else {
            $defaultEntityTypes = "{
				\"0\": {
					\"displayName\": \"default\",
					\"displayPolygon\": true,
					\"polygonColor\": \"#6CFF1C80\",
					\"polygonPatternName\": 5,
					\"displayLines\": true,
					\"lineColor\": \"#7AC943FF\",
					\"displayPoints\": false,
					\"pointColor\": \"#7AC943FF\",
					\"pointSize\": 1.0,
					\"availability\" : 0,
					\"value\" : 0
				}
			}";

            self::Debug(
                "REL Adding in raster layer " . $rasterData["layer_name"] .
                " with default values. Defining this layer in the config file will allow you to modify the values."
            );
            Database::GetInstance()->query(
                "
                INSERT INTO layer (
                    layer_name, layer_short, layer_geotype, layer_category, layer_subcategory, layer_raster, layer_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ",
                array(
                    $rasterData["layer_name"], $rasterData["layer_name"], "raster", "Activities", "Shipping",
                    $jsonRasterData, $defaultEntityTypes
                )
            );
        }
    }
}
