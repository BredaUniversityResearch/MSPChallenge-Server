<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\MEL;
use App\Domain\Common\MessageJsonResponse;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mel')]
#[OA\Tag(
    name: 'MEL',
    description: 'Operations related to MEL'
)]
class MELController extends BaseController
{
    #[Route('/InitialFishing', name: 'session_api_mel_initial_fishing', methods: ['POST'])]
    #[OA\Post(
        summary: 'Set initial fishing values',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['fishing_values'],
                    properties: [
                        new OA\Property(
                            property: 'fishing_values',
                            description: 'JSON array of fishing values per fleet',
                            type: 'string',
                            pattern: '^\[.*\]$', // Optional: hints at array format
                            example: '[{"fleet_name":"Fishing Gr- Artisanal","fishing_value":0.5}]'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad Request',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal Server Error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure')
                    ]
                )
            )
        ]
    )]
    public function initialFishing(Request $request): JsonResponse
    {
        $fishingValues = json_decode($request->request->get('fishing_values'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new MessageJsonResponse(
                status: 400,
                message: 'Invalid JSON'
            );
        }
        try {
            $mel = new MEL();
            $mel->setGameSessionId($this->getSessionIdFromRequest($request));
            return new JsonResponse($mel->InitialFishing($fishingValues));
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: 500,
                message: $e->getMessage()
            );
        }
    }
}
