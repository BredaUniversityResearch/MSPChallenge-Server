<?php

namespace App\Domain\POV;

use App\Domain\API\v1\Game;
use App\Domain\Helper\Util;
use App\Domain\Services\ConnectionManager;
use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerInterface;

class ConfigCreator
{
    const DEFAULT_CONFIG_FILENAME = 'pov-config.json';

    const SUB_DIR = 'POV';

    public function __construct(
        private readonly string $projectDir,
        private readonly int $sessionId,
        private readonly LoggerInterface $logger
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
        $zipFilename = self::getDefaultOutputBaseDir($this->projectDir) . DIRECTORY_SEPARATOR .
            self::getDefaultCompressedFilename($region);
        $this->log('Creating zip file: ' . $zipFilename);
        Util::createZipFromFolder($zipFilename, $outputDir);
        Util::removeDirectory($outputDir); // clean-up
        $this->log('Zip file created: ' . $zipFilename);
        return $zipFilename;
    }

    private function log(string $message): void
    {
        $this->logger->notice($message);
    }

    public static function getDefaultOutputBaseDir(string $projectDir): string
    {
        return (php_sapi_name() == 'cli' ? getcwd() : $projectDir) . DIRECTORY_SEPARATOR . self::SUB_DIR;
    }

    public static function getFolderNameFromRegion(Region $region): string
    {
        return implode('-', $region->toArray());
    }

    public static function getDefaultCompressedFilename(Region $region): string
    {
        return self::getFolderNameFromRegion($region) . '.zip';
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
        $dir ??= self::getDefaultOutputBaseDir($this->projectDir);
        $dir .= DIRECTORY_SEPARATOR . self::getFolderNameFromRegion($region);
        if (!extension_loaded('imagick')) {
            throw new Exception('The required imagick extension is not loaded.');
        }
        $this->log('query json from ' . $this->getDatabaseName() . ' for region: ' . $region);
        $jsonString = $this->queryJson($region);
        $this->log('json retrieved, decoding json');
        try {
            $json = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new Exception('Could not decode the json string retrieved from ' . $this->getDatabaseName() .
                '. Error: ' . $e->getMessage());
        }
        $this->log('json decoded, extracting region from raster layers');
        $this->extractRegionFromRasterLayers($region, $json['datamodel']['raster_layers'], $dir);
        if (false !== $bathymetryLayer = current(array_filter(
            $json['datamodel']['raster_layers'],
            fn($x) => $x['name'] === 'Bathymetry'
        ))) {
            $json['datamodel']['bathymetry'] = $bathymetryLayer;
        }
        $this->log('region extracted, creating json config file');
        $this->createJsonConfigFile($json, $dir, $configFilename);
        $this->log('json config file created: ' . realpath($dir . DIRECTORY_SEPARATOR . $configFilename));
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
        $game = new Game();
        $game->setGameSessionId($this->sessionId);
        $dataModel = $game->GetGameConfigValues();

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
  LatestGeometryInRegion AS (
    SELECT gf.*
    FROM LatestGeometryFinal gf
    # create and join dynamic table that holds all the geometry points as records for each geometry id
    INNER JOIN (
      SELECT
          g.geometry_id,
          jt.x,
          jt.y
      FROM
          geometry g
      CROSS JOIN
          JSON_TABLE(
              g.geometry_geometry,
              '$[*]' COLUMNS (
                  x double PATH '$[0]',
                  y double PATH '$[1]'
              )
          ) AS jt
    # only geometry with points inside the region
    ) AS p ON p.geometry_id=gf.geometry_id AND
      p.x BETWEEN :bottomLeftX AND :topRightX AND p.y BETWEEN :bottomLeftY AND :topRightY
    # since get records per point, group by geometry id
    GROUP BY gf.geometry_id
  ),
  # filter out active layers that are not in a plan or in an implemented and active plan
  LayerStep1 AS (
      SELECT l.layer_id, lorg.layer_raster, lorg.layer_type, lorg.layer_short, lorg.layer_name, lorg.layer_geotype,
             lorg.layer_tags, lorg.layer_category, lorg.layer_subcategory
      FROM layer l
      LEFT JOIN plan_layer pl ON l.layer_id=pl.plan_layer_layer_id
      LEFT JOIN plan p ON p.plan_id=pl.plan_layer_plan_id
      LEFT JOIN layer lorg ON IFNULL(l.layer_original_id,l.layer_id)=lorg.layer_id
      WHERE l.layer_active = 1 AND lorg.layer_active = 1 AND
        p.plan_id IS NULL OR (p.plan_state IN ('IMPLEMENTED') AND p.plan_active = 1) AND
      (
        lorg.layer_geotype IN ('line','point','polygon') OR (
          # if raster, return only if the region overlaps with its bounding box data
          -- l.layer_geotype IN ('raster')
          JSON_EXTRACT(lorg.layer_raster, '$.boundingbox[0][0]') < :topRightX
          AND JSON_EXTRACT(lorg.layer_raster, '$.boundingbox[0][1]') < :topRightY
          AND JSON_EXTRACT(lorg.layer_raster, '$.boundingbox[1][0]') > :bottomLeftX
          AND JSON_EXTRACT(lorg.layer_raster, '$.boundingbox[1][1]') > :bottomLeftY
        )
      )
      GROUP BY l.layer_id
  ),
  # include kpi value if layer is an ecology layer and
  #   extract new data columns from layer_type json data
  LayerStep2 AS (
      SELECT
        l.*,
        k.kpi_value,
        JSON_ARRAYAGG(JSON_OBJECT(
          'channel', 'r',
          'max', t.value,
          'type', t.id-1
        )) AS layer_type_mapping,
        JSON_ARRAYAGG(JSON_OBJECT(
          'name', t.display_name,
          'approval', t.approval,            
          'value', t.value,
          'map_type', t.map_type,
          'displayPolygon', t.displayPolygon,
          'polygonColor', t.polygonColor,
          'polygonPatternName', t.polygonPatternName, 
          'innerGlowEnabled', t.innerGlowEnabled,
          'innerGlowRadius', t.innerGlowRadius,
          'innerGlowIterations', t.innerGlowIterations,
          'innerGlowMultiplier', t.innerGlowMultiplier,
          'innerGlowPixelSize', t.innerGlowPixelSize,
          'displayLines', t.displayLines,
          'lineColor', t.lineColor,
          'lineWidth', t.lineWidth,
          'lineIcon', t.lineIcon,
          'linePatternType', t.linePatternType,
          'displayPoints', t.displayPoints,
          'pointColor', t.pointColor,
          'pointSize', t.pointSize,
          'pointSpriteName', t.pointSpriteName,
          'description', t.description,
          'capacity', t.capacity, 
          'investmentCost', t.investmentCost,
          'availability', t.availability,
          'media', t.media 
        )) AS layer_type_types,
        MIN(t.value) AS layer_type_value_min,
        MAX(t.value) AS layer_type_value_max,
        COUNT(t.id) as layer_type_value_count,
        CAST((MAX(t.value) / (COUNT(t.id)-1)) as INT) as layer_type_value_step
      FROM LayerStep1 l
      # join as json table to control which fields we want to extract and alias afterwards
      #   see https://community.mspchallenge.info/wiki/Configuration_data_schema_documentation
      INNER JOIN JSON_TABLE(
        JSON_EXTRACT(l.layer_type, '$.*'),
          '$[*]' COLUMNS (
            id for ordinality,
            display_name VARCHAR(255) PATH '$.displayName',
            approval VARCHAR(255) PATH '$.approval',            
            value int PATH '$.value',
            map_type VARCHAR(255) PATH '$.map_type',
            displayPolygon bool PATH '$.displayPolygon',
            polygonColor VARCHAR(255) PATH '$.polygonColor',
            polygonPatternName VARCHAR(255) PATH '$.polygonPatternName', 
            innerGlowEnabled bool PATH '$.innerGlowEnabled',
            innerGlowRadius int PATH '$.innerGlowRadius',
            innerGlowIterations int PATH '$.innerGlowIterations',
            innerGlowMultiplier double PATH '$.innerGlowMultiplier',
            innerGlowPixelSize double PATH '$.innerGlowPixelSize',
            displayLines bool PATH '$.displayLines',
            lineColor VARCHAR(255) PATH '$.lineColor',
            lineWidth double PATH '$.lineWidth',
            lineIcon VARCHAR(255) PATH '$.lineIcon',
            linePatternType VARCHAR(255) PATH '$.linePatternType',
            displayPoints bool PATH '$.displayPoints',
            pointColor VARCHAR(255) PATH '$.pointColor',
            pointSize double PATH '$.pointSize',
            pointSpriteName VARCHAR(255) PATH '$.pointSpriteName',
            description TEXT PATH '$.description',
            capacity long PATH '$.capacity', 
            investmentCost float PATH '$.investmentCost',
            availability int PATH '$.availability',
            media VARCHAR(255) PATH '$.media'     
        )
      ) AS t
      LEFT JOIN LatestEcologyKpiFinal k ON (
        l.layer_short != '' AND CONCAT('mel_',LOWER(REPLACE(k.kpi_name,' ','_'))) = l.layer_name
      )
      GROUP BY l.layer_id
  ),
  LayerFinal AS (
      SELECT
        l.*,
        JSON_ARRAYAGG(
          JSON_OBJECT(
            'points', JSON_EXTRACT(g.geometry_geometry, '$'),
            'types', JSON_ARRAY(JSON_EXTRACT(CONCAT('[',g.geometry_type,']'), '$')),
            'gaps', IF(g.geometry_gaps=JSON_ARRAY(null),JSON_ARRAY(), g.geometry_gaps),
            # just add some aliases to the metadata
            'metadata', JSON_EXTRACT(g.geometry_data, '$')
          )        
        ) AS layer_data
      FROM LayerStep2 l
      # left join since raster layers do not ever have geometry data
      LEFT JOIN LatestGeometryInRegion as g ON g.geometry_layer_id=l.layer_id
      WHERE l.layer_geotype IN ('raster') OR g.geometry_layer_id IS NOT NULL
      GROUP BY l.layer_id
  )
SELECT
  JSON_OBJECT(
    'metadata',
    JSON_OBJECT(
      'data_modified', CONCAT(UTC_DATE(), ' ', UTC_TIME()),
      'region', :region
    ),
    'datamodel',
      JSON_MERGE_PRESERVE(
        JSON_OBJECT(
          'coordinate0', JSON_ARRAY(CAST(:bottomLeftX AS DOUBLE), CAST(:bottomLeftY AS DOUBLE)),
          'coordinate1', JSON_ARRAY(CAST(:topRightX AS DOUBLE), CAST(:topRightY AS DOUBLE)),
          'projection', :projection
        ),
        JSON_OBJECTAGG(subquery.prop, JSON_EXTRACT(subquery.value, '$.*'))
      )      
  ) as json
FROM (
  SELECT
    IF(l.layer_geotype = 'raster', 'raster_layers', 'vector_layers') as prop,
    # return the latest geometry for each layer as a json object in POV config json format
    JSON_EXTRACT(JSON_OBJECTAGG(
      l.layer_id,
      IF(
        l.layer_geotype = 'raster',
        JSON_MERGE_PRESERVE(
          JSON_OBJECT(
            #'db-layer-id', l.layer_id,
            'name', REPLACE(l.layer_short,'mel_',''),
            'coordinate0', JSON_EXTRACT(l.layer_raster, '$.boundingbox[0]'),
            'coordinate1', JSON_EXTRACT(l.layer_raster, '$.boundingbox[1]'),
            'mapping', l.layer_type_mapping,
            'types', l.layer_type_types,
            'data', CONCAT(JSON_UNQUOTE(JSON_EXTRACT(l.layer_raster, '$.url'))),
            'tags', JSON_EXTRACT(l.layer_tags, '$')
          ),
          IF(
            l.kpi_value IS NOT NULL,
            JSON_OBJECT(
              'scale', JSON_OBJECT(
                'min_value', 0,
                # convert from t/km2 to kg/km2, times 2 since 0.5 is the reference value
                'max_value', l.kpi_value * 1000 * 2,
                'interpolation', 'lin'
              )
            ),
            JSON_OBJECT()
          )
        ),
        JSON_OBJECT(
          #'db-layer-id', l.layer_id,
          #'db-geometry-ids', (
          #   SELECT JSON_ARRAYAGG(JSON_EXTRACT(geometry_id, '$'))
          #   FROM LatestGeometryInRegion WHERE geometry_Layer_id=l.layer_id
          # ),
          'name', l.layer_name,
          'tags', JSON_EXTRACT(l.layer_tags, '$'),
          'types', l.layer_type_types,
          'data', l.layer_data
        )
      )
    ), '$') as value
  # start from layer since it holds both raster and vector layers.
  FROM LayerFinal l
  GROUP BY IF(l.layer_geotype = 'raster', 'raster_layers', 'vector_layers')
  ORDER BY FIELD(l.layer_geotype, 'raster', 'polygon', 'point', 'line')
) as subquery
SQL,
                array_merge(
                    $region->toArray(),
                    [
                        'region' => $dataModel['region'],
                        'projection' => $dataModel['projection']
                    ]
                )
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
            $outputRegion = clone $region;
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
                    $outputRegion
                );
            } catch (Exception $e) {
                throw new Exception('Could not extract region from image: ' . $e->getMessage(), 0, $e);
            }
            $layer['data'] = 'Rastermaps/' . $targetFile;
            // now set the region coordinates, clamped by input coordinates (see extractPartFromImageByRegion)
            $layer['coordinate0'] = $outputRegion->getBottomLeft();
            $layer['coordinate1'] = $outputRegion->getTopRight();
            $this->handleRasterMapping($layer['mapping']);
        }
        unset($layer);
    }

    private function handleRasterMapping(array &$mapping): void
    {
        $m = &$mapping;
        if (count($m) == 0) {
            return;
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
        list($inputBottomLeftX, $inputBottomLeftY, $inputTopRightX, $inputTopRightY) =
            array_values($inputRegion->toArray());

        // clamp by input coordinates.
        if (null === $clampedOutputRegion = $outputRegion->createClampedBy($inputRegion)) {
            // this should be impossible, since the region coordinates were used for a query input resulting into this
            //  layer
            throw new Exception("Specified region does not overlap with the input image: $inputImageFilePath");
        }
        list($outputBottomLeftX, $outputBottomLeftY, $outputTopRightX, $outputTopRightY) =
            array_values($clampedOutputRegion->toArray());

        $image = new \Imagick();
        $image->readImage($inputImageFilePath);
        $inputWidth = $image->getImageWidth();
        $inputHeight = $image->getImageHeight();

        $coordinateToPixelWidthFactor = $inputWidth / ($inputTopRightX - $inputBottomLeftX);
        $coordinateToPixelHeightFactor = $inputHeight / ($inputTopRightY - $inputBottomLeftY);

        // map coordinate to the pixel "size", still as real number
        $outputPixel0XRealNumber = ($outputBottomLeftX - $inputBottomLeftX) * $coordinateToPixelWidthFactor;
        $outputPixel0YRealNumber = ($outputBottomLeftY - $inputBottomLeftY) * $coordinateToPixelHeightFactor;
        $outputPixel1XRealNumber = ($outputTopRightX - $inputBottomLeftX) * $coordinateToPixelWidthFactor;
        $outputPixel1YRealNumber = ($outputTopRightY - $inputBottomLeftY) * $coordinateToPixelHeightFactor;

        // now convert to actual pixel "size", so int
        $outputPixel0X = (int)($outputPixel0XRealNumber);
        $outputPixel0Y = (int)($outputPixel0YRealNumber);
        $outputPixel1X = (int)ceil($outputPixel1XRealNumber);
        $outputPixel1Y = (int)ceil($outputPixel1YRealNumber);

        // Calculate the size of the extracted region in pixel "size"
        $regionWidth = $outputPixel1X - $outputPixel0X + 1;
        $regionHeight = $outputPixel1Y - $outputPixel0Y + 1;

        if ($regionWidth <= 0 || $regionHeight <= 0) {
            throw new Exception('Invalid region size: ' . $regionWidth . 'x' . $regionHeight);
        }

        // set the actual outputted region coordinates
        $pixelToCoordinateWidthFactor = ($inputTopRightX - $inputBottomLeftX) / $inputWidth;
        $pixelToCoordinateHeightFactor = ($inputTopRightY - $inputBottomLeftY) / $inputHeight;
        $outputRegion->setBottomLeftX(
            $outputBottomLeftX - ($outputPixel0XRealNumber - $outputPixel0X) * $pixelToCoordinateWidthFactor
        );
        $outputRegion->setBottomLeftY(
            $outputBottomLeftY - ($outputPixel0YRealNumber - $outputPixel0Y) * $pixelToCoordinateHeightFactor
        );
        $outputRegion->setTopRightX(
            $outputTopRightX + ($outputPixel1X - $outputPixel1XRealNumber) * $pixelToCoordinateWidthFactor
        );
        $outputRegion->setTopRightY(
            $outputTopRightY + ($outputPixel1Y - $outputPixel1YRealNumber) * $pixelToCoordinateHeightFactor
        );

        // finally convert to pixel coordinates.
        $outputPixel0Y = $inputHeight - $regionHeight - $outputPixel0Y;

        // clamp by image input size
        $outputPixel0X = max(0, min($inputWidth - 1, $outputPixel0X));
        $outputPixel0Y = max(0, min($inputHeight - 1, $outputPixel0Y));

        $image->cropImage($regionWidth, $regionHeight, $outputPixel0X, $outputPixel0Y);
        $image->setImageFormat('PNG');
        $image->writeImage($outputImageFilePath);
    }
}
