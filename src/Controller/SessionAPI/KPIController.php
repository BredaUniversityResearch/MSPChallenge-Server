<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\APIHelper;
use App\Domain\API\v1\Game;
use App\Domain\API\v1\Kpi;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        APIHelper $apiHelper
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
            return new JsonResponse(self::wrapPayloadForResponse(true, 'KPI values posted successfully'));
        } catch (Exception $e) {
            return new JsonResponse(self::wrapPayloadForResponse(false, message: $e->getMessage()), 500);
        }
    }
}
