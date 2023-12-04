<?php

namespace App\Domain\POV;

use App\Domain\Helper\Util;
use App\Domain\Services\ConnectionManager;
use Doctrine\DBAL\Connection;
use Exception;

class ConfigCreator
{
    const DEFAULT_CONFIG_FILENAME = 'pov-config.json';

    const SUBDIR = 'POV';

    public function __construct(
        private readonly string $projectDir,
        private readonly int $sessionId
    ) {
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function getConnection(): Connection
    {
        return ConnectionManager::getInstance()->getCachedGameSessionDbConnection($this->sessionId);
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getDatabaseName(): string
    {
        $databaseName = $this->getConnection()->getDatabase();
        if ($databaseName === null) {
            throw new Exception('Could not get the database name from the connection.');
        }
        return $databaseName;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function createAndZip(
        Region $region,
        ?string $dir = null,
        string $configFilename = self::DEFAULT_CONFIG_FILENAME
    ): string {
        $outputDir = $this->create($region, $dir, $configFilename);
        $zipFilename = self::getDefaultOutputBaseDir() . DIRECTORY_SEPARATOR .
            self::getDefaultCompressedFilename($region);
        Util::createZipFromFolder($zipFilename, $outputDir);
        Util::removeDirectory($outputDir); // clean-up
        return $zipFilename;
    }

    public static function getDefaultOutputBaseDir(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . self::SUBDIR;
    }

    public static function getDefaultOutputDir(Region $region): string
    {
        return self::getDefaultOutputBaseDir() . DIRECTORY_SEPARATOR . self::getFoldernameFromRegion($region);
    }

    public static function getFoldernameFromRegion(Region $region): string
    {
        return implode('-', $region->toArray());
    }

    public static function getDefaultCompressedFilename(Region $region): string
    {
        return self::getFoldernameFromRegion($region) . '.zip';
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function create(
        Region $region,
        ?string $dir = null,
        string $configFilename = self::DEFAULT_CONFIG_FILENAME
    ): string {
        $dir ??= self::getDefaultOutputDir($region);
        if (!extension_loaded('imagick')) {
            throw new Exception('The required imagick extension is not loaded.');
        }
        $jsonString = $this->queryJson($region);
        try {
            $json = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new Exception('Could not decode the json string retrieved from ' . $this->getDatabaseName() .
                '. Error: ' . $e->getMessage());
        }
        $this->extractRegionFromRasterLayers($region, $json['datamodel']['raster_layers'], $dir);
        $this->createJsonConfigFile($json, $dir, $configFilename);
        return $dir;
    }

    /**
     * @throws Exception
     */
    private function createJsonConfigFile(array &$json, string $dir, string $configFilename): void
    {
        $outputFilepath = $dir . DIRECTORY_SEPARATOR . $configFilename;
        try {
            if (!file_put_contents($outputFilepath, json_encode(
                $json,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ))) {
                throw new Exception('Could not write to output file: ' . $dir);
            }
        } catch (Exception $e) {
            throw new Exception('Could not encode the json string. Error: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function queryJson(Region $region): string
    {
        try {
            $result = $this->getConnection()->executeQuery(
                <<<'SQL'
WITH
  # group ecology kpis by name and give row number based on month, row number 1 is the latest kpi
  LatestEcologyKpiStep1 AS (
    SELECT
      *,
      ROW_NUMBER() OVER (PARTITION BY kpi_name ORDER BY kpi_month DESC) AS rn
    FROM
      kpi
    WHERE kpi_type = 'ECOLOGY'
  ),
  # filter latest ecology kpis, so only with row number 1
  LatestEcologyKpiFinal AS (
    SELECT * FROM LatestEcologyKpiStep1 WHERE rn = 1
  ),
  # group non-subtractive geometries by persistent id and give row number based on geometry_id,
  #   row number 1 is the latest geometry
  LatestGeometryStep1 AS (
    SELECT
      *,
      ROW_NUMBER() OVER (PARTITION BY geometry_persistent ORDER BY geometry_id DESC) AS rn
    FROM
      geometry
    WHERE geometry_deleted = 0 AND geometry_active = 1 AND geometry_subtractive = 0
  ),
  # filter latest geometries, so only with row number 1
  LatestGeometryStep2 AS (
    SELECT * FROM LatestGeometryStep1 WHERE rn = 1
  ),
  # join all substractive geometries to the latest geometries and aggregate them as a json array "gaps"
  LatestGeometryStep3 AS (
    SELECT g.*, JSON_ARRAYAGG(JSON_EXTRACT(gs.geometry_geometry, '$')) AS geometry_gaps
    FROM LatestGeometryStep2 g
    LEFT JOIN geometry gs ON gs.geometry_subtractive = g.geometry_id
    GROUP BY g.geometry_id
  ),
  # filter out geometries that are in a deleted plan, plan needs to be implemented and active
  LatestGeometryFinal AS (
    SELECT
      g.*
    FROM
      LatestGeometryStep3 g
    LEFT JOIN layer ON layer.layer_id=g.geometry_layer_id
    LEFT JOIN plan_layer ON plan_layer.plan_layer_layer_id=layer.layer_id
    LEFT JOIN plan ON (
      plan.plan_id=plan_layer.plan_layer_plan_id AND plan.plan_state IN ('IMPLEMENTED') AND plan.plan_active = 1
    )
    LEFT JOIN plan_delete pd ON (
      pd.plan_delete_plan_id=plan.plan_id AND pd.plan_delete_geometry_persistent=g.geometry_persistent
    )
    WHERE pd.plan_delete_geometry_persistent IS NULL
    GROUP BY g.geometry_persistent
  ),
  # filter out active layers that are not in a plan or in an implemented and active plan
  LayerStep1 AS (
      SELECT l.*
      FROM layer l
      LEFT JOIN plan_layer pl ON l.layer_id=pl.plan_layer_layer_id
      LEFT JOIN plan p ON p.plan_id=pl.plan_layer_plan_id
      WHERE l.layer_active = 1 AND p.plan_id IS NULL OR (p.plan_state IN ('IMPLEMENTED') AND p.plan_active = 1) AND
      (
        l.layer_geotype IN ('line','point','polygon') OR (
          # if raster, return only if the region overlaps with its bounding box data
          -- l.layer_geotype IN ('raster')
          JSON_EXTRACT(l.layer_raster, '$.boundingbox[0][0]') < :bottomRightX
          AND JSON_EXTRACT(l.layer_raster, '$.boundingbox[0][1]') < :bottomRightY
          AND JSON_EXTRACT(l.layer_raster, '$.boundingbox[1][0]') > :topLeftX
          AND JSON_EXTRACT(l.layer_raster, '$.boundingbox[1][1]') > :topLeftY
        )
      )
      GROUP BY l.layer_id
  ),
  # include kpi value if layer is an ecology layer and
  #   extract new data columns from layer_type json data
  LayerFinal AS (
      SELECT
        l.*,
        k.kpi_value,
        JSON_ARRAYAGG(JSON_OBJECT(
          'channel', 'r',
          'max', t.value,
          'type', t.id
        )) AS layer_type_mapping,
        JSON_ARRAYAGG(JSON_OBJECT(
          'name', t.display_name,
          '//material', 'todo'
        )) AS layer_type_types,
        MIN(t.value) AS layer_type_value_min,
        MAX(t.value) AS layer_type_value_max,
        COUNT(t.id) as layer_type_value_count,
        CAST((MAX(t.value) / (COUNT(t.id)-1)) as INT) as layer_type_value_step
      FROM LayerStep1 l
      INNER JOIN JSON_TABLE(
        JSON_EXTRACT(l.layer_type, '$.*'),
          '$[*]' COLUMNS (
            id for ordinality,
            display_name VARCHAR(255) PATH '$.displayName',
            value int PATH '$.value'
        )
      ) AS t
      LEFT JOIN LatestEcologyKpiFinal k ON (
        l.layer_short != '' AND CONCAT('mel_',LOWER(REPLACE(k.kpi_name,' ','_'))) = l.layer_name
      )
      GROUP BY l.layer_id
  ),
  LatestGeometryInRegion AS (
    SELECT *
    FROM LatestGeometryFinal
    # if geometry, return only the ones inside the region based on its geometry point data
    WHERE
      JSON_EXTRACT(geometry_geometry, '$[0][0]') BETWEEN :topLeftX AND :bottomRightX
      AND JSON_EXTRACT(geometry_geometry, '$[0][1]') BETWEEN :topLeftY AND :bottomRightY
  )
SELECT
  JSON_OBJECT(
    'metadata',
    JSON_OBJECT(
      'data_modified', CONCAT(UTC_DATE(), ' ', UTC_TIME())
    ),
    'datamodel',
    JSON_OBJECTAGG(subquery.prop, JSON_EXTRACT(subquery.value, '$.*'))
  ) as json
FROM (
  SELECT
    IF(l.layer_geotype = 'raster', 'raster_layers', 'vector_layers') as prop,
    # return the latest geometry for each layer as a json object in POV config json format
    JSON_EXTRACT(JSON_OBJECTAGG(
      l.layer_id,
      IF(
        l.layer_geotype = 'raster',
        JSON_OBJECT(
          #'db-layer-id', l.layer_id,
          'name', REPLACE(l.layer_short,'mel_',''),
          'coordinate0', JSON_EXTRACT(l.layer_raster, '$.boundingbox[0]'),
          'coordinate1', JSON_EXTRACT(l.layer_raster, '$.boundingbox[1]'),
          'display_methods', JSON_MERGE_PRESERVE(
            IF(
              # detecting a kpi layer
              l.kpi_value IS NOT NULL,
              JSON_ARRAY(
                JSON_OBJECT(
                  'method', 'DensityFish',
                  'scale', JSON_OBJECT(
                    'min_value', 0,
                    # convert from t/km2 to kg/km2, times 2 since 0.5 is the reference value
                    'max_value', l.kpi_value * 1000 * 2,
                    'interpolation', 'lin',
                    '//model', 'todo, eg: \'Cod\'',
                    '//unit_mass', 'todo, eg: 10'
                  )
                )
              ),
              JSON_ARRAY()
            ),
            IF (
              # detecting a non-kpi layer
              l.kpi_value IS NULL,
              JSON_ARRAY(
                  JSON_OBJECT(
                    'method', 'TypeMap',
                    'mapping', l.layer_type_mapping,
                    '//type_material', 'todo, eg: \'material\'',
                    '//seabottom', 'todo, eg: true'
                  )
              ),
              JSON_ARRAY()
            )
          ),
          'types', l.layer_type_types,
          'data', CONCAT(JSON_UNQUOTE(JSON_EXTRACT(l.layer_raster, '$.url')))
        ),
        JSON_OBJECT(
          #'db-layer-id', l.layer_id,
          #'db-geometry-ids', (
          #   SELECT JSON_ARRAYAGG(JSON_EXTRACT(geometry_id, '$'))
          #   FROM LatestGeometryInRegion WHERE geometry_Layer_id=l.layer_id
          # ),
          'name', l.layer_name,
          'display_methods', JSON_MERGE_PRESERVE(
              IF(
                l.layer_geotype = 'polygon',
                JSON_ARRAY(
                  JSON_OBJECT(
                    'method', 'AreaModelScatter',
                    '//type_model', 'todo, eg: \'default_model\'',
                    '//meta_amount', 'todo, eg: \'turbines\'',
                    '//stages', 'todo',
                    '//stage_distribution', 'todo'
                  ),
                  JSON_OBJECT(
                    'method', 'AreaColour',
                    '//colour', 'todo, eg: \'#ffb30f\''
                  )
                ),
                JSON_ARRAY()
              ),
              IF(
                l.layer_geotype = 'point',
                JSON_ARRAY(
                  JSON_OBJECT(
                    'method', 'PointModel',
                    '//model', 'todo, eg: ConverterStationModel'
                  )
                ),
                JSON_ARRAY()
              ),
              IF(
                l.layer_geotype = 'line',
                JSON_ARRAY(
                  JSON_OBJECT(
                    'method', 'LineModel',
                    '//model', 'todo, eg: EnergyCableModel',
                    '//meta_amount', 'todo, eg: \'number_cables\''
                  ),
                  JSON_OBJECT(
                    'method', 'LineColour',
                    '//colour', 'todo, eg: #ebff0f',
                    '//meta_width', 'todo, eg: \'width\''
                  )
                ),
                JSON_ARRAY()
              )
          ),
          'types', l.layer_type_types,     
          'data', JSON_OBJECT(
             'points', JSON_EXTRACT(geometry_geometry, '$'),
             'types', JSON_ARRAY(JSON_EXTRACT(g.geometry_type, '$')),
             'gaps', IF(g.geometry_gaps=JSON_ARRAY(null),JSON_ARRAY(), g.geometry_gaps),
             # just add some aliases to the metadata
             'metadata', JSON_MERGE_PATCH(
               g.geometry_data,
               JSON_OBJECT(
                 'name', JSON_EXTRACT(g.geometry_data, '$.Name'),
                 'turbines', JSON_EXTRACT(g.geometry_data, '$.N_TURBINES'),   
                 '//width', 'todo, eg: 1',
                 '//direction', 'todo, eg: true',
                 '//number_cables', 'todo, eg: 1'
               )
             )
          )
        )
      )
    ), '$') as value
  # start from layer since it holds both raster and vector layers.
  FROM LayerFinal l
  # left join since raster layers do not ever have geometry data
  LEFT JOIN LatestGeometryInRegion as g ON g.geometry_layer_id=l.layer_id
  WHERE l.layer_geotype IN ('raster') OR g.geometry_layer_id IS NOT NULL
  GROUP BY IF(l.layer_geotype = 'raster', 'raster_layers', 'vector_layers')
  ORDER BY FIELD(l.layer_geotype, 'raster', 'polygon', 'point', 'line')
) as subquery
SQL,
                $region->toArray()
            );
            $jsonString = $result->fetchOne();
        } catch (Exception $e) {
            throw new Exception(
                'Could not query from ' . $this->getDatabaseName() . '. Error: ' . $e->getMessage()
            );
        }
        if (!$jsonString) {
            throw new Exception('No data found from ' . $this->getDatabaseName() . ' for the given region: ' . $region);
        }
        return $jsonString;
    }

    /**
     * @throws Exception
     */
    private function extractRegionFromRasterLayers(Region $region, array &$rasterLayers, string $dir): void
    {
        $targetDir = $dir . DIRECTORY_SEPARATOR . 'Rastermaps';
        Util::removeDirectory($targetDir);
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception('Could not create directory: ' . $targetDir);
        }
        // cut raster according to coordinates
        foreach ($rasterLayers as &$layer) {
            $pathInfo = pathinfo($layer['data']);
            // add _Cut in the filename before the extension
            $targetFile = $pathInfo['filename'] . '_Cut.png';
            try {
                $this->extractPartFromImageByRegion(
                    $this->projectDir . DIRECTORY_SEPARATOR . 'raster' . DIRECTORY_SEPARATOR .
                        $this->sessionId . DIRECTORY_SEPARATOR . $layer['data'],
                    new Region(
                        $layer['coordinate0'][0],
                        $layer['coordinate0'][1],
                        $layer['coordinate1'][0],
                        $layer['coordinate1'][1]
                    ),
                    $targetDir . DIRECTORY_SEPARATOR . $targetFile,
                    $region
                );
            } catch (Exception $e) {
                throw new Exception('Could not extract region from image: ' . $e->getMessage(), 0, $e);
            }
            $layer['data'] = $targetFile;
            // now set the region coordinates, clamped by input coordinates (see extractPartFromImageByRegion)
            $layer['coordinate0'] = $region->getTopLeft();
            $layer['coordinate1'] = $region->getBottomRight();
            $this->handleRasterDisplayMethods($layer);
        }
        unset($layer);
    }

    private function handleRasterDisplayMethods(array &$rasterLayer): void
    {
        if (count($rasterLayer['display_methods']) == 0) {
            return;
        }

        foreach ($rasterLayer['display_methods'] as &$displayMethod) {
            if ($displayMethod['method'] !== 'TypeMap') {
                continue;
            }
            $m = &$displayMethod['mapping'];
            if (count($m) == 0) {
                continue;
            }
            $maxValue = $m[count($m)-1]['max'];
            // normalise the max values , up to 255 max. And:
            //   set the "min" value based on the previous mapping entry's max
            $m[0]['min'] = 0;
            $m[0]['max'] = (int)ceil(($m[0]['max'] / $maxValue) * 255);
            for ($n = 1; $n < count($m); ++$n) {
                $prevMappingEntry = &$m[$n - 1];
                $mappingEntry = &$m[$n];
                $mappingEntry['min'] = $prevMappingEntry['max']+1;
                $m[$n]['max'] = (int)ceil(($m[$n]['max'] / $maxValue) * 255);
            }
        }
    }

    /**
     * @throws \ImagickException
     * @throws Exception
     */
    private function extractPartFromImageByRegion(
        string $inputImageFilePath,
        Region $inputRegion,
        string $outputImageFilePath,
        Region $outputRegion
    ): void {
        list($inputTopLeftX, $inputTopLeftY, $inputBottomRightX, $inputBottomRightY) =
            array_values($inputRegion->toArray());
        // clamp by input coordinates. The output region should be fully covered by the input region
        $outputRegion->setTopLeftX(min($outputRegion->getTopLeftX(), $inputRegion->getBottomRightX()));
        $outputRegion->setTopLeftY(min($outputRegion->getTopLeftY(), $inputRegion->getBottomRightY()));
        $outputRegion->setBottomRightX(max($outputRegion->getBottomRightX(), $inputRegion->getTopLeftX()));
        $outputRegion->setBottomRightY(max($outputRegion->getBottomRightY(), $inputRegion->getTopLeftY()));
        list($outputTopLeftX, $outputTopLeftY, $outputBottomRightX, $outputBottomRightY) =
            array_values($outputRegion->toArray());

        //correct y of input coordinate, the y of input coordinate 0 should be greater than input coordinate 1
        if ($inputTopLeftY < $inputBottomRightY) {
            $tmp = $inputTopLeftY;
            $inputTopLeftY = $inputBottomRightY;
            $inputBottomRightY = $tmp;
        }
        //correct y of output coordinate, the y of output coordinate 0 should be greater than output coordinate 1
        if ($outputTopLeftY < $outputBottomRightY) {
            $tmp = $outputTopLeftY;
            $outputTopLeftY = $outputBottomRightY;
            $outputBottomRightY = $tmp;
        }

        $image = new \Imagick();
        $image->readImage($inputImageFilePath);
        $inputWidth = $image->getImageWidth();
        $inputHeight = $image->getImageHeight();

        $coordinateToPixelWidthFactor = $inputWidth / ($inputBottomRightX - $inputTopLeftX);
        $coordinateToPixelHeightFactor = $inputHeight / ($inputBottomRightY - $inputTopLeftY);

        // map coordinate to the pixel coordinate of the image
        $outputPixel0X = (int)round(($outputTopLeftX - $inputTopLeftX) * $coordinateToPixelWidthFactor);
        $outputPixel0Y = (int)round(($outputTopLeftY -  $inputTopLeftY) * $coordinateToPixelHeightFactor);
        $outputPixel1X = (int)round(($outputBottomRightX - $inputTopLeftX) * $coordinateToPixelWidthFactor);
        $outputPixel1Y = (int)round(($outputBottomRightY -  $inputTopLeftY) * $coordinateToPixelHeightFactor);

        // Check if output coordinates are within input range
        $outputPixel0X = max(0, min($inputWidth - 1, $outputPixel0X));
        $outputPixel0Y = max(0, min($inputHeight - 1, $outputPixel0Y));
        $outputPixel1X = max(0, min($inputWidth - 1, $outputPixel1X));
        $outputPixel1Y = max(0, min($inputHeight - 1, $outputPixel1Y));

        // Calculate the size of the extracted region
        $regionWidth = $outputPixel1X - $outputPixel0X + 1;
        $regionHeight = $outputPixel1Y - $outputPixel0Y + 1;

        if ($regionWidth <= 0 || $regionHeight <= 0) {
            throw new Exception('Invalid region size: ' . $regionWidth . 'x' . $regionHeight);
        }

        $image->cropImage($regionWidth, $regionHeight, $outputPixel0X, $outputPixel0Y);
        $image->setImageFormat('PNG');
        $image->writeImage($outputImageFilePath);
    }
}
