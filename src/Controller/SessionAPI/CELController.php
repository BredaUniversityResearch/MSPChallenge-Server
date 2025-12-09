<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\CEL;
use App\Domain\Common\MessageJsonResponse;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cel')]
#[OA\Tag(
    name: 'CEL',
    description: 'Operations related to CEL'
)]
class CELController extends BaseController
{
    #[Route('/ShouldUpdate', name: 'session_api_cel_should_update', methods: ['POST'])]
    #[OA\Post(
        summary: 'Check for CEL if it should update',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'boolean'
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function shouldUpdate(Request $request): JsonResponse
    {
        try {
            $cel = new CEL();
            $cel->setGameSessionId($this->getSessionIdFromRequest($request));
            return new JsonResponse($cel->ShouldUpdate());
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: $e->getCode() ?: 500,
                message: $e->getMessage()
            );
        }
    }
}
