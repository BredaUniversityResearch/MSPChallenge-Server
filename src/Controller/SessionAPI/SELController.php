<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\SEL;
use App\Entity\SessionAPI\Geometry;
use App\Entity\SessionAPI\Layer;
use App\Repository\SessionAPI\LayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Domain\Common\MessageJsonResponse;

#[Route('/api/SEL')]
#[OA\Tag(
    name: 'SEL',
    description: 'Operations related to SEL'
)]
class SELController extends BaseController
{
    #[Route(
        path: '/GetRestrictionGeometry',
        name: 'session_api_sel_get_restriction_geometry',
        methods: ['POST']
    )]
    public function getRestrictionGeometry(
        Request $request,
        LoggerInterface $gameSessionLogger
    ): JsonResponse {
        $sel = new SEL();
        $gameSessionId = $this->getSessionIdFromRequest($request);
        $sel->setGameSessionId($gameSessionId);
        try {
            $result = $sel->GetRestrictionGeometry();
            $sel->pushToLogger($gameSessionLogger, ['gameSession' => $gameSessionId]);
            return new JsonResponse($result);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: $e->getCode() ?: 500,
                message: $e->getMessage()
            );
        }
    }

    #[Route(
        path: '/GetUpdatePackage',
        name: 'session_api_sel_get_update_package',
        methods: ['POST']
    )]
    public function getUpdatePackage(
        Request $request,
        LoggerInterface $gameSessionLogger
    ): JsonResponse {
        $sel = new SEL();
        $gameSessionId = $this->getSessionIdFromRequest($request);
        $sel->setGameSessionId($gameSessionId);
        try {
            $result = $sel->GetUpdatePackage();
            $sel->pushToLogger($gameSessionLogger, ['gameSession' => $gameSessionId]);
            return new JsonResponse($result);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: $e->getCode() ?: 500,
                message: $e->getMessage()
            );
        }
    }

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
