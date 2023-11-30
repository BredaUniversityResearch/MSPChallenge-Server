<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
use React\MySQL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-pov-config',
    description: 'Create a POV config json file for a session by region.',
)]
class CreatePOVConfigCommand extends Command
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'session-id',
                's',
                InputOption::VALUE_REQUIRED,
                'The ID of the game session'
            );
        $this
            ->addOption(
                'output-dir',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The path to the output directory. Default is the current working directory.',
                getcwd()
            );
        $this
            ->addOption(
                'output-filename',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The filename of output json file. Default is: pov-config.json',
                'pov-config.json'
            );
        $this
            ->addArgument(
                'coordinates',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                '4 floats representing the coordinates of the region, in the order: coordinate0 x, -y, ' .
                    'coordinate1 x, -y'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!extension_loaded('imagick')) {
            $io->error('The imagick extension is not loaded.');
            return Command::FAILURE;
        }
        $sessionId = $input->getOption('session-id');
        if (!ctype_digit($sessionId)) {
            $io->error('Session ID must be an integer.');
            return Command::FAILURE;
        }
        $outputDir = $input->getOption('output-dir');
        if (!is_dir($outputDir)) {
            $io->error('Output directory does not exist.');
            return Command::FAILURE;
        }
        /** @var array $coordinates */
        $coordinates = $input->getArgument('coordinates');
        if (count($coordinates) !== 4) {
            $io->error('You must specify 4 floats: coordinate0 x, -y, coordinate1 x, -y.');
            return Command::FAILURE;
        }
        try {
            $connection = $this->connectionManager->getCachedGameSessionDbConnection((int)$sessionId);
        } catch (\Exception $e) {
            $io->error(
                'Could not connect to the game session database with id: ' . $sessionId . '. Error: ' . $e->getMessage()
            );
            return Command::FAILURE;
        }

        $jsonString = false;
        try {
            $result = $connection->executeQuery(<<<'SQL'
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
SQL
                , array_combine(
                    [
                        'topLeftX',
                        'topLeftY',
                        'bottomRightX',
                        'bottomRightY'
                    ],
                    $coordinates
                ));
            $jsonString = $result->fetchOne();
        } catch (\Exception $e) {
            $io->error(
                'Could not query the game session database with id: ' . $sessionId . '. Error: ' . $e->getMessage()
            );
            return Command::FAILURE;
        }

        if (!$jsonString) {
            $io->error('No data found for the given session and coordinates.');
            return Command::FAILURE;
        }

        try {
            $json = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('Could not decode the json string. Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            $this->processRasterLayers($json['datamodel']['raster_layers'], $sessionId, $coordinates);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $outputDir = $input->getOption('output-dir') . '/' . $input->getOption('output-filename');
        try {
            if (!file_put_contents($outputDir, json_encode(
                $json,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ))) {
                $io->error('Could not write to output file: ' . $outputDir);
                return Command::FAILURE;
            }
        } catch (\JsonException $e) {
            $io->error('Could not encode the json string. Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function processRasterLayers(array &$rasterLayers, int $sessionId, array $regionCoordinates): void
    {
        // cut raster according to coordinates
        foreach ($rasterLayers as &$layer) {
            $pathInfo = pathinfo($layer['data']);
            // add _Cut in the filename before the extension
            $targetFile = $pathInfo['filename'] . '_Cut.' . $pathInfo['extension'];

            $targetDir = getcwd() . DIRECTORY_SEPARATOR . 'Rastermaps';
            @mkdir($targetDir, 0777, true);
            try {
                $this->extractRegionFromImageByCoordinates(
                    $this->projectDir . DIRECTORY_SEPARATOR . 'raster' . DIRECTORY_SEPARATOR . $sessionId .
                    DIRECTORY_SEPARATOR . $layer['data'],
                    $layer['coordinate0'][0],
                    $layer['coordinate0'][1],
                    $layer['coordinate1'][0],
                    $layer['coordinate1'][1],
                    $targetDir . DIRECTORY_SEPARATOR . $targetFile,
                    $regionCoordinates[0],
                    $regionCoordinates[1],
                    $regionCoordinates[2],
                    $regionCoordinates[3],
                );
            } catch (\Exception $e) {
                throw new Exception('Could not extract region from image: ' . $e->getMessage(), 0, $e);
            }
            $layer['data'] = $targetFile;
            // now set the region coordinates, clamp by input coordinates
            $layer['coordinate0'] = [
                min($regionCoordinates[0], $layer['coordinate1'][0]),
                min($regionCoordinates[1], $layer['coordinate1'][1])
            ];
            $layer['coordinate1'] = [
                max($regionCoordinates[2], $layer['coordinate0'][0]),
                max($regionCoordinates[3], $layer['coordinate0'][1])
            ];

            $this->handleRasterDisplayMethods($layer);
        }
        unset($layer);
    }

    private function handleRasterDisplayMethods(array &$rasterLayer)
    {
        if (count($rasterLayer['display_methods']) == 0) {
            return;
        }

        foreach ($rasterLayer['display_methods'] as &$displayMethod) {
            if ($displayMethod['method'] === 'TypeMap') {
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
    }

    private function extractRegionFromImageByCoordinates(
        string $inputFilePath,
        float $inputCoordinate0X, // (3450000.000, 3123736.631)
        float $inputCoordinate0Y,
        float $inputCoordinate1X, // (4390000.000, 4350000.000)
        float $inputCoordinate1Y,
        string $outputFilePath,
        float $outputCoordinate0X, // 4048486.327 , 3470173.348
        float $outputCoordinate0Y,
        float $outputCoordinate1X, // 4063795.673 , 3488657.652
        float $outputCoordinate1Y
    ): void {

        //correct y of input coordinate, the y of input coordinate 0 should be greater than input coordinate 1
        if ($inputCoordinate0Y < $inputCoordinate1Y) {
            $tmp = $inputCoordinate0Y;
            $inputCoordinate0Y = $inputCoordinate1Y;
            $inputCoordinate1Y = $tmp;
        }
        //correct y of output coordinate, the y of output coordinate 0 should be greater than output coordinate 1
        if ($outputCoordinate0Y < $outputCoordinate1Y) {
            $tmp = $outputCoordinate0Y;
            $outputCoordinate0Y = $outputCoordinate1Y;
            $outputCoordinate1Y = $tmp;
        }

        $image = new \Imagick();
        $image->readImage($inputFilePath);
        $inputWidth = $image->getImageWidth();
        $inputHeight = $image->getImageHeight();

        $coordinateToPixelWidthFactor = $inputWidth / ($inputCoordinate1X - $inputCoordinate0X);
        $coordinateToPixelHeightFactor = $inputHeight / ($inputCoordinate1Y - $inputCoordinate0Y);

        // map coordinate to the pixel coordinate of the image
        $outputPixel0X = (int)round(($outputCoordinate0X - $inputCoordinate0X) * $coordinateToPixelWidthFactor);
        $outputPixel0Y = (int)round(($outputCoordinate0Y -  $inputCoordinate0Y) * $coordinateToPixelHeightFactor);
        $outputPixel1X = (int)round(($outputCoordinate1X - $inputCoordinate0X) * $coordinateToPixelWidthFactor);
        $outputPixel1Y = (int)round(($outputCoordinate1Y -  $inputCoordinate0Y) * $coordinateToPixelHeightFactor);

        // Check if output coordinates are within input range
        $outputPixel0X = max(0, min($inputWidth - 1, $outputPixel0X));
        $outputPixel0Y = max(0, min($inputHeight - 1, $outputPixel0Y));
        $outputPixel1X = max(0, min($inputWidth - 1, $outputPixel1X));
        $outputPixel1Y = max(0, min($inputHeight - 1, $outputPixel1Y));

        // Calculate the size of the extracted region
        $regionWidth = $outputPixel1X - $outputPixel0X + 1;
        $regionHeight = $outputPixel1Y - $outputPixel0Y + 1;

        if ($regionWidth <= 0 || $regionHeight <= 0) {
            throw new \RuntimeException('Invalid region size: ' . $regionWidth . 'x' . $regionHeight);
        }

        $image->cropImage($regionWidth, $regionHeight, $outputPixel0X, $outputPixel0Y);
        $image->setImageFormat('PNG');
        $image->writeImage($outputFilePath);
    }
}
