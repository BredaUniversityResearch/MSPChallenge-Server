<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/Layer')]
#[OA\Tag(name: 'Layer', description: 'Operations related to layer management')]
class LayerController extends BaseController
{
    #[Route(
        path: '/List',
        name: 'session_api_layer_list',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Provides a list of raster layers and vector layers that have active geometry',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Layer created successfully',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'layer_id', type: 'integer'),
                                            new OA\Property(property: 'layer_name', type: 'string'),
                                            new OA\Property(property: 'layer_geotype', type: 'string')
                                        ],
                                        type: 'object'
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid input',
                content: new OA\JsonContent(ref: '#/components/schemas/ResponseStructure')
            )
        ]
    )]
    public function list(
        Request $request
    ): Response {
        return $this->forward('App\Controller\LegacyController::__invoke', [
            'request' => $request,
            'query' => 'Layer/List'
        ]);
    }
}
