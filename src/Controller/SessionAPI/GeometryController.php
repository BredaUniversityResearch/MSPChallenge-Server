<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\Common\MessageJsonResponse;
use App\Entity\SessionAPI\Geometry;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/{geometry}', requirements: ['geometry' => '[gG]eometry'])]
#[OA\Tag(
    name: 'Geometry',
    description: 'Operations related to geometry management.'
)]
#[OA\Parameter(
    name: 'geometry',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        default: 'geometry',
        enum: ['geometry', 'Geometry']
    )
)]
class GeometryController extends BaseController
{
    /**
     * @throws \Exception
     */
    #[Route(
        path: '/{id}/wkt',
        name: 'session_api_geometry_wkt',
        methods: ['GET']
    )]
    #[OA\Get(
        description: 'Returns the Well-Known Text (WKT) representation of the geometry with the specified ID. '.
            'Tip: use <A href="http://wktmap.com">wktmap.com</A> to visualize it, given epsg 3035',
        summary: 'Retrieve the WKT representation of a geometry by ID.',
        tags: ['Geometry'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the geometry to retrieve.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with the WKT representation.',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string')
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Geometry not found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'null'),
                        new OA\Property(property: 'message', type: 'string')
                    ],
                    type: 'object'
                )
            )
        ]
    )]
    public function wkt(
        Request $request,
        int $id
    ): JsonResponse|Response {
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $geom = $em->getRepository(Geometry::class);
        /** @var Geometry $geometry */
        $geometry = $geom->find($id);
        if (null == $geometry) {
            return new MessageJsonResponse(
                status: 404,
                message: 'Geometry with ID ' . $id . ' not found.'
            );
        }
        return new Response($geometry->toWkt());
    }
}
