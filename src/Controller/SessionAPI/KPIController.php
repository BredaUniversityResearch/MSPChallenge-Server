<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Kpi;
use App\Domain\API\v1\Router;
use App\Domain\Services\ConnectionManager;
use App\Entity\Simulation;
use App\Entity\Watchdog;
use App\Repository\SimulationRepository;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/kpi')]
#[OA\Tag(name: 'KPI', description: 'Operations related to kpi management')]
class KPIController extends BaseController
{
    #[Route(
        path: '/BatchPost',
        name: 'session_api_kpi_batch_post',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Batch post KPI values',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['kpiValues'],
                    properties: [
                        new OA\Property(
                            property: 'kpiValues',
                            description: 'The KPI\'s to post. Format: json array of object with: name, month, value, '.
                                'type, unit, country. type is one of ECOLOGY, ENERGY, SHIPPING. The field country is '.
                                'the id of the country or -1 if it is global. You can retrieve the country id using '.
                                '/api/Game/GetCountries',
                            type: 'string',
                            format: 'json',
                            default: null,
                            example: '[{"name":"SunHours","month":0,"value":267,"type":"ECOLOGY","unit":"hours",'.
                                '"country":3},{"name":"SunHours","month":0,"value":243,"type":"ECOLOGY",'.
                                '"unit":"hours",'.'"country":4}]'
                        )
                    ]
                )
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'x-server-id',
                description: 'Watchdog server ID',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'x-notify-monthly-simulation-finished',
                description: 'If set to true, all simulations for the watchdog server - given by x-server-id header - '.
                    'will be notified that the monthly simulation is finished',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPI values posted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid or missing data')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error message')
                    ]
                )
            )
        ]
    )]
    public function batchPost(
        Request $request,
        // below is required by legacy to be auto-wired
        APIHelper $apiHelper,
        ConnectionManager $connectionManager
    ): JsonResponse {
        $kpiValues = $request->request->get('kpiValues');
        if (empty($kpiValues)) {
            return new JsonResponse(
                Router::formatResponse(false, 'Invalid or missing data', null, __CLASS__, __FUNCTION__),
                Response::HTTP_BAD_REQUEST
            );
        }

        $kpiValues = json_decode($kpiValues, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                Router::formatResponse(false, 'Invalid or missing data', null, __CLASS__, __FUNCTION__),
                Response::HTTP_BAD_REQUEST
            );
        }

        $kpi = new Kpi();
        $kpi->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $kpi->BatchPost($kpiValues);
        } catch (Exception $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 500);
        }

        $message = 'KPI values posted successfully';
        $notify = $request->headers->get('x-notify-monthly-simulation-finished');
        if (!($notify && filter_var($notify, FILTER_VALIDATE_BOOLEAN))) {
            return new JsonResponse(self::wrapPayloadForResponse(true, $message));
        }

        try {
            $serverId = $this->getServerIdFromRequest($request);
            $em = $connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
            if (null === $watchdog = $em->getRepository(Watchdog::class)->findOneBy(['serverId' => $serverId])) {
                throw new Exception(sprintf('Watchdog with server id %s not found', $serverId->toRfc4122()));
            }
            /** @var SimulationRepository $repo */
            $repo = $em->getRepository(Simulation::class);
            foreach ($watchdog->getSimulations() as $simulation) {
                $repo->notifyMonthSimulationFinished($serverId, $simulation->getName(), $kpiValues[0]['month']);
            }
            $message .= '. Monthly simulation finished notified';
        } catch (Exception $e) {
            $message .= '. ' . $e->getMessage();
        }
        return new JsonResponse(self::wrapPayloadForResponse(true, $message));
    }
}
