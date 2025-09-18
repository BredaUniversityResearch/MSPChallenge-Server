<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Layer;
use App\Domain\Common\MessageJsonResponse;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/Layer')]
#[OA\Tag(name: 'Layer', description: 'Operations related to layer management')]
class LayerController extends BaseController
{
    /**
     * @throws Exception
     */
    #[Route(
        path: '/List',
        name: 'session_api_layer_list',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Provides a list of raster layers and vector layers that have active geometry',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'layer_tags',
                            description: 'Optional array of layer tags to filter the layers',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            nullable: true
                        )
                    ]
                )
            )
        ),
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
                                            new OA\Property(
                                                property: 'layer_id',
                                                type: 'integer'
                                            ),
                                            new OA\Property(
                                                property: 'layer_name',
                                                type: 'string'
                                            ),
                                            new OA\Property(
                                                property: 'layer_geotype',
                                                type: 'string'
                                            )
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
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'database exception response',
                            value: [
                                'success' => false,
                                'message' => 'Query exception: SQLSTATE[42S02]: Base table or view not found...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function list(
        Request $request
    ): JsonResponse {
        $layerTags = $request->request->get('layer_tags', ''); // comma separated string
        $layerTags = empty($layerTags) ? [] : array_filter(explode(',', $layerTags));
        $layer = new Layer();
        $layer->setGameSessionId($this->getSessionIdFromRequest($request));
        try {
            $layers = $layer->list($layerTags);
            return new JsonResponse($layers);
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }

    #[Route('/Export', name: 'session_api_layer_export', methods: ['POST'])]
    #[OA\Post(
        summary: 'Export a layer to .json',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'layer_id',
                            description: 'id of the layer to export',
                            type: 'integer'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Layer exported successfully',
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
                                            new OA\Property(
                                                property: 'FID',
                                                type: 'integer',
                                                example: null,
                                                nullable: true
                                            ),
                                            new OA\Property(
                                                property: 'the_geom',
                                                type: 'string',
                                                example: 'POINT (3875583.0061738 3998069.7660753)'
                                            ),
                                            new OA\Property(
                                                property: 'type',
                                                type: 'string',
                                                example: '0'
                                            ),
                                            new OA\Property(
                                                property: 'mspid',
                                                type: 'string',
                                                example: '702'
                                            ),
                                            new OA\Property(
                                                property: 'id',
                                                type: 'integer',
                                                example: 2114
                                            ),
                                            new OA\Property(
                                                property: 'data',
                                                properties: [
                                                    new OA\Property(
                                                        property: 'Type_1',
                                                        type: 'string',
                                                        example: ''
                                                    ),
                                                    new OA\Property(
                                                        property: 'Connection',
                                                        type: 'string',
                                                        example: ''
                                                    ),
                                                    new OA\Property(
                                                        property: 'id',
                                                        type: 'string',
                                                        example: '1'
                                                    ),
                                                    new OA\Property(
                                                        property: 'original_layer_name',
                                                        type: 'string',
                                                        example: 'NS_Electicity_Sockets'
                                                    )
                                                ],
                                                type: 'object'
                                            )
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
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'database exception response',
                            value: [
                                'success' => false,
                                'message' => 'Query exception: SQLSTATE[42S02]: Base table or view not found...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function export(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $layerId = $request->request->get('layer_id');
            return new MessageJsonResponse(
                data: $layer->Export($layerId),
                message: 'Layer export with all geometry and their attributes'
            );
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }

    #[Route('/Get', name: 'session_api_layer_get', methods: ['POST'])]
    #[OA\Post(
        summary: 'Get all geometry in a single layer',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['layer_id'],
                    properties: [
                        new OA\Property(
                            property: 'layer_id',
                            description: 'id of the layer to return',
                            type: 'integer'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Vector layer retrieved successfully',
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
                                            new OA\Property(
                                                property: 'id',
                                                type: 'integer',
                                                example: 1586
                                            ),
                                            new OA\Property(
                                                property: 'geometry',
                                                type: 'array',
                                                items: new OA\Items(
                                                    type: 'array',
                                                    items: new OA\Items(
                                                        type: 'number',
                                                        maxItems: 2,
                                                        minItems: 2,
                                                        example: [3674414.2806077, 3346465.4397283]
                                                    )
                                                )
                                            ),
                                            new OA\Property(
                                                property: 'subtractive',
                                                type: 'array',
                                                items: new OA\Items(
                                                    type: 'integer'
                                                ),
                                                example: []
                                            ),
                                            new OA\Property(
                                                property: 'persistent',
                                                type: 'integer',
                                                example: 1586
                                            ),
                                            new OA\Property(
                                                property: 'mspid',
                                                type: 'integer',
                                                example: 14109
                                            ),
                                            new OA\Property(
                                                property: 'type',
                                                type: 'integer',
                                                example: 1
                                            ),
                                            new OA\Property(
                                                property: 'country',
                                                type: 'integer',
                                                example: -1
                                            ),
                                            new OA\Property(
                                                property: 'active',
                                                type: 'integer',
                                                enum: [0, 1],
                                                example: 1
                                            ),
                                            new OA\Property(
                                                property: 'data',
                                                type: 'object',
                                                example:
                                                    '{"Status":"Open","Area_cal":"2695738","Dist_coast":"2610.41",'.
                                                    '"Updateyea_":"2014","Country":"England","Depth_m_":"","Name":'.
                                                    '"WEST STONES","original_layer_name":"NS_Dredging_Deposit_Areas"}'
                                            )
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
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'Not a vector layer',
                            summary: 'Not a vector layer response',
                            value: [
                                'success' => false,
                                'message' => 'Not a vector layer'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function get(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $layerId = $request->request->get('layer_id');
            return new JsonResponse($layer->Get($layerId));
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }

    #[Route('/GetRaster', name: 'session_api_layer_get_raster', methods: ['POST'])]
    #[OA\Post(
        summary: 'Retrieves image data for raster',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'layer_name',
                            description: 'Name of the layer corresponding to the image data',
                            type: 'string',
                            example: 'NS_Bathymetry_Raster'
                        ),
                        new OA\Property(
                            property: 'month',
                            description: 'Month for which to retrieve the raster data',
                            type: 'integer',
                            default: -1
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Raster data retrieved successfully',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'payload',
                                    properties: [
                                        new OA\Property(
                                            property: 'displayed_bounds',
                                            type: 'array',
                                            items: new OA\Items(
                                                type: 'array',
                                                items: new OA\Items(
                                                    type: 'number',
                                                    maxItems: 2,
                                                    minItems: 2
                                                )
                                            ),
                                            example: [
                                                [2921042.1165, 2835279.6931],
                                                [4800949.2847, 4499955.1151]
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'image_data',
                                            description: 'Base64 encoded file string',
                                            type: 'string'
                                        )
                                    ],
                                    type: 'object'
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'database exception response',
                            value: [
                                'success' => false,
                                'message' => 'Query exception: SQLSTATE[42S02]: Base table or view not found...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function getRaster(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $layerName = $request->request->get('layer_name');
            $month = $request->request->get('month', -1);
            return new MessageJsonResponse(
                data: $layer->GetRaster($layerName, $month),
                message:'Returns array of displayed_bounds and image_data strings to payload, whereby image_data is '.
                    'base64 encoded file'
            );
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }

    #[Route('/Meta', name: 'session_api_layer_meta', methods: ['POST'])]
    #[OA\Post(
        summary: 'Get all the meta data of a single layer',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'layer_id',
                            description: 'Layer id to return',
                            type: 'integer'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                ref: '#/components/responses/LayerMetaResponse',
                response: 200
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'exception',
                            summary: 'database exception response',
                            value: [
                                'success' => false,
                                'message' => 'Query exception: SQLSTATE[42S02]: Base table or view not found...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function meta(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $layerId = $request->request->get('layer_id');
            return new MessageJsonResponse(data: $layer->Meta($layerId), message:'JSON object');
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }

    #[Route('/MetaByName', name: 'session_api_layer_meta_by_name', methods: ['POST'])]
    #[OA\Post(
        summary: 'Gets a single layer meta data by name',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'Name of the layer that we want the meta for',
                            type: 'string',
                            example: 'NS_Dredging_Deposit_Areas'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                ref: '#/components/responses/LayerMetaResponse',
                response: 200
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    examples: [
                        new OA\Examples(
                            example: 'could not find exception',
                            summary: 'could not find exception response',
                            value: [
                                'success' => false,
                                'message' => 'Could not find raster file for layer with name NS_Dredging_Deposit_Areas'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function metaByName(
        Request $request,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): JsonResponse {
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $name = $request->request->get('name');
            return new MessageJsonResponse(data: $layer->MetaByName($name), message:'JSON object');
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
    }
}
