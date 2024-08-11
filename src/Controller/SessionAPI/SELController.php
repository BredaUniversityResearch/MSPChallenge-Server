<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Entity\Geometry;
use App\Entity\Layer;
use App\Repository\LayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

class SELController extends BaseController
{
    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws Exception
     */
    public static function calculateAlignedSimulationBounds(
        array $config,
        Geometry $playAreaGeometry
    ): ?array {
        $bounds = $playAreaGeometry->calculateBounds();
        if (empty($config["MEL"]) || empty($config["MEL"]["x_min"]) || empty($config["MEL"]["y_min"])
            || empty($config["MEL"]["x_max"]) || empty($config["MEL"]["y_max"]) || empty($config["MEL"]['cellsize'])
        ) {
            return $bounds;
        }
        $xOffset = $config["MEL"]["x_min"] - $bounds["x_min"];
        $yOffset = $config["MEL"]["y_min"] - $bounds["y_min"];

        $melCellSize = $config["MEL"]["cellsize"];
        $simulationAreaShift = array(fmod($xOffset, $melCellSize), fmod($yOffset, $melCellSize));

        $xSize = ceil(($bounds["x_max"] - $bounds["x_min"]) / $melCellSize) * $melCellSize;
        $ySize = ceil(($bounds["y_max"] - $bounds["y_min"]) / $melCellSize) * $melCellSize;

        $bounds["x_min"] += $simulationAreaShift[0];
        $bounds["y_min"] += $simulationAreaShift[1];
        $bounds["x_max"] = $bounds["x_min"] + $xSize;
        $bounds["y_max"] = $bounds["y_min"] + $ySize;
        return $bounds;
    }

    /**
     * @param Geometry[] $geometries
     * @return Geometry|null
     * @throws Exception
     */
    public static function getGeometryWithLargestBounds(array $geometries): ?Geometry
    {
        return collect($geometries)->reduce(
            function (?Geometry $result, Geometry $geo, $key) {
                $curBounds = $result?->calculateBounds();
                $curSize = $curBounds === null ? 0 :
                    ($curBounds["x_max"] - $curBounds["x_min"]) * ($curBounds["y_max"] - $curBounds["y_min"]);
                $bounds = $geo->calculateBounds();
                $newSize = ($bounds["x_max"] - $bounds["x_min"]) * ($bounds["y_max"] - $bounds["y_min"]);
                if ($newSize > $curSize) {
                    $result = $geo;
                }
                return $result;
            },
        );
    }

    /**
     * @throws Exception
     */
    public static function getLargestPlayAreaGeometryFromDb(EntityManagerInterface $em): Geometry
    {
        /** @var LayerRepository $layerRepo */
        $layerRepo = $em->getRepository(Layer::class);
        $playAreaLayers = $layerRepo->getPlayAreaLayers();
        if (null === $geometry = self::getGeometryWithLargestBounds(
            collect($playAreaLayers)->map(fn(Layer $l) => $l->getGeometry()->first())->all()
        )) {
            throw new Exception("Could not find expected _PLAYAREA layer geometry");
        }
        return $geometry;
    }
}
