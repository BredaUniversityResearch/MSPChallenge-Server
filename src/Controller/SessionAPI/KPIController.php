<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Kpi;
use App\Domain\Common\MessageJsonResponse;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Entity\SessionAPI\Simulation;
use App\Entity\SessionAPI\Watchdog;
use App\Repository\ServerManager\GameWatchdogServerRepository;
use App\Repository\SessionAPI\SimulationRepository;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
                            // phpcs:ignore
                            description: 'The KPI\'s to post. Format: json array of object with: name, month, value, type, unit, country. For the internal watchdog, type must be set to either ECOLOGY, ENERGY or SHIPPING. The field country is the id of the country or -1 if it is global. You can retrieve the country id using /api/Game/GetCountries<br>Example:<br><pre>[<br>    {<br>        "name": "SunHours",<br>        "month": 0,<br>        "value": 267,<br>        "type": "ECOLOGY",<br>        "unit": "hours",<br>        "country": 3<br>    },<br>    {<br>        "name": "SunHours",<br>        "month": 0,<br>        "value": 243,<br>        "type": "ECOLOGY",<br>        "unit": "hours",<br>        "country": 4<br>    }<br>]</pre>',
                            type: 'string',
                            format: 'json',
                            default: null
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
            return new MessageJsonResponse(
                status: Response::HTTP_BAD_REQUEST,
                message: 'Invalid or missing data'
            );
        }

        $kpiValues = json_decode($kpiValues, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new MessageJsonResponse(status: Response::HTTP_BAD_REQUEST, message: 'Invalid or missing data');
        }

        try {
            // It is from an external watchdog
            $serverId = $this->getServerIdFromRequest($request);
            if ($serverId->toRfc4122() === Watchdog::getInternalServerId()->toRfc4122()) {
                throw new Exception('Nope, not an external watchdog');
            }
            $kpiValues = collect($kpiValues)->map(function ($kpiType) use ($connectionManager, $serverId) {
                /** @var GameWatchdogServerRepository $repo */
                $repo = $connectionManager->getServerManagerEntityManager()->getRepository(GameWatchdogServer::class);
                $repo->validateSimulationType($serverId, $kpiType['type'])
                    or throw new InvalidArgumentException('Invalid type: '.$kpiType['type']);
                $kpiType['type_external'] = $kpiType['type'];
                $kpiType['type'] = Kpi::KPI_TYPE_EXTERNAL;
                return $kpiType;
            })->all();
        } catch (InvalidArgumentException $e) {
            return new MessageJsonResponse(status: 400, message: $e->getMessage());
        } catch (Exception) {
            // process $kpiValues as-is
        }

        $kpi = new Kpi();
        $kpi->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $kpi->BatchPost($kpiValues);
        } catch (Exception $e) {
            return new MessageJsonResponse(status: $e->getCode() ?: 500, message: $e->getMessage());
        }

        $logs[] = 'KPI values posted successfully';
        $notify = $request->headers->get('x-notify-monthly-simulation-finished');
        if (!($notify && filter_var($notify, FILTER_VALIDATE_BOOLEAN))) {
            return new JsonResponse(['logs' => $logs]);
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
            $logs[] = 'Monthly simulation finished notified';
        } catch (Exception $e) {
            $logs[] = $e->getMessage();
        }
        return new JsonResponse(['logs' => $logs]);
    }
}
