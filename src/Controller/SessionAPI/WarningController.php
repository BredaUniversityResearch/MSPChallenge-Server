<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Warning;
use App\Domain\Common\MessageJsonResponse;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Note that the Route-, and OA attributes are defined through API Platform, see App\Entity\SessionAPI\Warning
 */
class WarningController extends BaseController
{
    /**
     * Note that the Route-, and OA attributes are defined through API Platform, see App\Entity\SessionAPI\Warning
     */
    public function post(
        Request $request
    ): JsonResponse {
        if (null !== $added = $request->request->get('added')) {
            $added = json_decode($added, true) ?: [];
        }
        if (null !== $removed = $request->request->get('removed')) {
            $removed = json_decode($removed, true) ?: [];
        }
        $warning = new Warning();
        $warning->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            return new JsonResponse(data: $warning->Post(
                plan: $request->request->get('plan'),
                planlayer_id: $request->request->get('planlayer_id'),
                added: $added,
                removed: $removed
            ));
        } catch (Exception $e) {
            return new MessageJsonResponse(status: $e->getCode() ?: 500, message: $e->getMessage());
        }
    }
}
