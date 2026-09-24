<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Plan;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Domain\Common\MessageJsonResponse;

#[Route('/api/{plan}', requirements: ['plan' => '[pP]lan'])]
#[OA\Tag(
    name: 'Plan',
    description: 'Operations related to plan management.'
)]
#[OA\Parameter(
    name: 'plan',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        default: 'plan',
        enum: ['plan', 'Plan']
    )
)]
class PlanController extends BaseController
{
    #[Route(
        path: '/Restrictions',
        name: 'session_api_plan_restrictions',
        methods: ['GET', 'POST']
    )]
    public function restrictions(Request $request): JsonResponse
    {
        $plan = new Plan();
        $plan->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $restrictions = $plan->Restrictions();
            return new JsonResponse($restrictions);
        } catch (Exception $e) {
            return new MessageJsonResponse(
                status: $e->getCode() ?: 500,
                message: $e->getMessage()
            );
        }
    }
}
