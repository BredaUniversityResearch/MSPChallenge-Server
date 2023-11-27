<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
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
        private readonly ConnectionManager $connectionManager
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
  # group geometries by persistent id and give row number based on geometry_id, row number 1 is the latest geometry
  LatestGeometryStep1 AS (
    SELECT
      *,
      ROW_NUMBER() OVER (PARTITION BY geometry_persistent ORDER BY geometry_id DESC) AS rn
    FROM
      geometry
    WHERE geometry_deleted = 0 AND geometry_active = 1
  ),
  # filter out geometries that are not the latest, so only with row number 1
  LatestGeometryStep2 AS (
    SELECT * FROM LatestGeometryStep1 WHERE rn = 1
  ),
  # filter out geometries that are in a deleted plan, plan needs to be implemented and active
  LatestGeometryFinal AS (
    SELECT
      g.*
    FROM
      LatestGeometryStep2 g
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
  LayerFinal AS (
      SELECT l.*
      FROM layer l
      LEFT JOIN plan_layer pl ON l.layer_id=pl.plan_layer_layer_id
      LEFT JOIN plan p ON p.plan_id=pl.plan_layer_plan_id
      WHERE l.layer_active = 1 AND  p.plan_id IS NULL OR (p.plan_state IN ('IMPLEMENTED') AND p.plan_active = 1) AND
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
      'data_modified', DATE_FORMAT(NOW(), '%d/%m/%Y')
    ),
    'datamodel',
    JSON_ARRAYAGG(JSON_EXTRACT(subquery.json_per_layer_type, '$'))
  ) as json
FROM (
  SELECT
    # return the latest geometry for each layer as a json object in POV config json format
    JSON_OBJECT(
      IF(l.layer_geotype = 'raster', 'raster_layers', 'vector_layers'),
        JSON_EXTRACT(JSON_OBJECTAGG(
          l.layer_id,
          IF(
            l.layer_geotype = 'raster',
            JSON_OBJECT(
              #'db-layer-id', l.layer_id,
              'name', l.layer_short,
              'coordinate0', JSON_EXTRACT(l.layer_raster, '$.boundingbox[0]'),
              'coordinate1', JSON_EXTRACT(l.layer_raster, '$.boundingbox[1]'),
              'db-layer-type//mapping//types', JSON_EXTRACT(l.layer_type, '$.*'),
              'data', CONCAT('RasterMaps/',JSON_UNQUOTE(JSON_EXTRACT(l.layer_raster, '$.url')))
            ),
            JSON_OBJECT(
              #'db-layer-id', l.layer_id,
              #'db-geometry-ids', (
              #   SELECT JSON_ARRAYAGG(JSON_EXTRACT(geometry_id, '$'))
              #   FROM LatestGeometryInRegion WHERE geometry_Layer_id=l.layer_id
              # ),
              'name', l.layer_name,
#               'display_methods', JSON_OBJECT(
#                 'stages', JSON_EXTRACT(l.layer_states, '$')
#               ),
              'db-types', JSON_EXTRACT(l.layer_type, '$.*'),
              'data', JSON_OBJECT(
                'points', (
                  SELECT JSON_ARRAYAGG(JSON_EXTRACT(geometry_geometry, '$'))
                  FROM LatestGeometryInRegion WHERE geometry_Layer_id=l.layer_id
                ),
                'db-geometry-data//metadata', JSON_EXTRACT(g.geometry_data, '$')
              )
            )
          )
        ), '$')
    ) as json_per_layer_type
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

        // todo: cut geometry according to coordinates
        // todo: cut raster according to coordinates

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
}
