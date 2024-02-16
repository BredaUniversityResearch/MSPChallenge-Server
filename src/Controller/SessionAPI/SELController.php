<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Entity\Geometry;
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
}
