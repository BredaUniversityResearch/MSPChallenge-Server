<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Layer;
use App\Domain\Common\MessageJsonResponse;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\SessionAPI\Layer as LayerEntity;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/{layer}', requirements: ['layer' => '[lL]ayer'])]
#[OA\Tag(name: 'Layer', description: 'Operations related to layer management')]
#[OA\Parameter(
    name: 'layer',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        default: 'layer',
        enum: ['layer', 'Layer']
    )
)]
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

    /**
     * @throws Exception
     */
    #[Route('/GetGeometry', name: 'session_api_layer_get_geometry', methods: ['POST'])]
    #[OA\Post(
        summary: 'Get all geometry in a single layer up to a given month',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['layer_id', 'month'],
                    properties: [
                        new OA\Property(
                            property: 'layer_id',
                            description: 'id of the layer. You can retrieve it using /api/Layer/List',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'month',
                            description: 'Month for which to retrieve the raster data',
                            type: 'integer',
                            default: -1
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Geometry retrieved successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ResponseGeometry')
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
                                'message' => 'Query exception...'
                            ]
                        )
                    ],
                    ref: '#/components/schemas/ResponseStructure'
                )
            )
        ]
    )]
    public function getGeometry(
        Request $request
    ): JsonResponse {
        $qb = $this->connectionManager->getCachedGameSessionDbConnection($this->getSessionIdFromRequest($request));
        $result = $qb
            ->executeQuery(
                <<<'SQL'
                # for the above month and layer, do:
                WITH
                  # collect deleted geometry
                  GeometryPersistentDeleted AS (
                    SELECT DISTINCT pd.plan_delete_geometry_persistent as geometry_persistent
                    FROM layer l
                    INNER JOIN plan_layer pl ON pl.plan_layer_layer_id=l.layer_id
                    INNER JOIN plan p ON pl.plan_layer_plan_id = p.plan_id
                    INNER JOIN plan_delete pd ON pd.plan_delete_layer_id = l.layer_id
                    WHERE
                      -- make sure to select all active layers that match the id, or its original layer does
                      (l.layer_id = :layer_id OR l.layer_original_id = :layer_id) AND l.layer_active=1
                      -- to control only to retrieve layers either without plans or approved (or implemented) ones,
                      --   up to a specific month and that are active
                      AND (
                        p.plan_state IN ('APPROVED','IMPLEMENTED') AND p.plan_gametime <= :month AND p.plan_active = 1
                      )
                  ),
                  # fetch all geometry including deletion, inactive, history
                  AllGeometryInclHistory AS (
                    SELECT
                      g.*, IFNULL(MAX(p.plan_gametime), -1) as implementation_time
                    FROM layer l
                    LEFT JOIN plan_layer pl ON pl.plan_layer_layer_id=l.layer_id
                    LEFT JOIN plan p ON pl.plan_layer_plan_id = p.plan_id
                    -- all active "non-deleted" geometry on these layers
                    INNER JOIN geometry g ON g.geometry_layer_id = l.layer_id
                    WHERE
                      -- make sure to select all active layers that match the id, or its original layer does
                      (l.layer_id = :layer_id OR l.layer_original_id = :layer_id) AND l.layer_active=1
                      -- to control only to retrieve layers either without plans or approved (or implemented) ones,
                      --   up to a specific month and that are active
                      AND (
                        p.plan_id IS NULL OR (
                          p.plan_state IN ('APPROVED','IMPLEMENTED') AND p.plan_gametime <= :month AND p.plan_active = 1
                        )
                      )
                    GROUP BY g.geometry_id
                  ),
                  # group non-subtractive geometries by persistent id and give row number based on geometry_id,
                  #   row number 1 is the latest geometry
                  LatestGeometryStep1 AS (
                    SELECT
                      *,
                      ROW_NUMBER() OVER (PARTITION BY geometry_persistent ORDER BY geometry_id DESC) AS rn
                    FROM
                      AllGeometryInclHistory
                    WHERE geometry_deleted = 0
                      -- DISABLED: we cannot filter on "active" since this seems to not respect historic data
                      -- AND geometry_active = 1
                  ),
                  # filter latest geometries, so only with row number 1
                  LatestGeometryStep2 AS (
                    SELECT * FROM LatestGeometryStep1 WHERE rn = 1
                  ),
                  # remove geometry that was deleted in one of the "delete" plans
                  FinalLatestGeometry AS (
                    SELECT
                      g.geometry_id as id, g.geometry_geometry as geometry, g.geometry_country_id as country,
                      g.geometry_FID as FID, g.geometry_data as data, g.geometry_layer_id as layer,
                      g.geometry_subtractive as subtractive, g.geometry_type as type,
                      g.geometry_persistent as persistent, g.geometry_mspid as mspid, g.geometry_active as active,
                      g.implementation_time
                    FROM LatestGeometryStep2 g
                    WHERE
                        -- remove geometry that was deleted in one of the "delete" plans
                        g.geometry_persistent NOT IN (SELECT geometry_persistent FROM GeometryPersistentDeleted)
                    ORDER BY g.geometry_FID, g.geometry_subtractive
                  )
                -- SELECT * from AllGeometryInclHistory;
                -- SELECT * from LatestGeometryStep1;
                -- SELECT * from LatestGeometryStep1;
                SELECT * from FinalLatestGeometry;
                SQL,
                [
                    'month' => $request->request->get('month', -1),
                    'layer_id' => $request->request->get('layer_id')
                ]
            );
        $geometry = [];
        while ($row = $result->fetchAssociative()) {
            $row['data'] ??= '';
            $geometry[] = [
                'id' => $row['id'],
                'geometry' => json_decode($row['geometry'], true),
                // todo: to be supported, see Base::MergeGeometry ?? can I still use LatestGeometryStep1+2 ?
                'subtractive' => [],
                'persistent' => $row['persistent'],
                'mspid' => $row['mspid'] ?? 0,
                'type' => $row['type'],
                'country' => $row['country'] ?? -1,
                'active' => $row['active'],
                'data' => json_decode($row['data'] == '[]' ? '' : $row['data']),
                'implementation_time' => $row['implementation_time']
            ];
        }
        return new JsonResponse($geometry);
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
            $month = $request->request->get('month');
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
                            example: 'NS_Topography_raster'
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

    /**
     * @throws Exception
     */
    #[Route('/UpdateRaster', name: 'session_api_layer_update_raster', methods: ['POST'])]
    #[OA\Post(
        summary: 'UpdateRaster updates raster image',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['layer_name', 'image_data'],
                    properties: [
                        new OA\Property(
                            property: 'layer_name',
                            description: 'Name of the layer the raster image is for.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'image_data',
                            description: 'Base64 encoded string of image data.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'month',
                            description: 'The month for which the raster image is for. Defaults to the current month.',
                            type: 'integer',
                            default: '',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'raster_bounds',
                            description: '2x2 array of doubles specifying [[min X, min Y], [max X, max Y]]',
                            type: 'string',
                            format: 'json',
                            default: '',
                            example: '[[2921042.1165, 2835279.6931], [4800949.2847, 4499955.1151]]',
                            nullable: true
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Raster data updated successfully',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ResponseStructure'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'logs',
                                    type: 'array',
                                    items: new OA\Items(type: 'string')
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    // @todo: remove raster_bounds support ? REL/REL/RiskModel.cs in simulations uses it, but REL isn't used anymore?
    public function updateRaster(
        Request $request
    ): JsonResponse {
        if (empty($request->request->get('layer_name'))) {
            return new MessageJsonResponse(status: 400, message: 'Missing required field: layer_name');
        }
        if (empty($request->request->get('image_data'))) {
            return new MessageJsonResponse(status: 400, message: 'Missing required field: image_data');
        }
        if (null !== $rasterBounds = ($request->request->get('raster_bounds', '') ?: null)) {
            $rasterBounds = json_decode($rasterBounds, true);
        }
        $month = ($request->request->get('month', ''));
        if ($month == '') {
            $month = null;
        }
        if ($month != null && !is_numeric($month)) {
            return new MessageJsonResponse(status: 400, message: 'Invalid month: '.$month);
        }
        $em = $this->connectionManager->getGameSessionEntityManager($this->getSessionIdFromRequest($request));
        $repo = $em->getRepository(LayerEntity::class);
        $layerName = $request->request->get('layer_name');
        if (null === $layerEntity = $repo->findOneBy(['layerName' => $layerName])) {
            return new MessageJsonResponse(
                status: 500,
                message: "Could not find layer with name ".$layerName." to update the raster image"
            );
        }
        if (false === $imageData = base64_decode($request->request->get('image_data'), true)) {
            return new MessageJsonResponse(status: 400, message: 'Invalid image data');
        }
        try {
            $layer = new Layer();
            $layer->setGameSessionId($this->getSessionIdFromRequest($request));
            $layer->UpdateRaster(
                $layerEntity,
                $imageData,
                $rasterBounds,
                $month
            );
        } catch (Exception $e) {
            return new MessageJsonResponse(status: 500, message: $e->getMessage());
        }
        try {
            $em->persist($layerEntity);
            $em->flush();
            $logs[] = sprintf('Successfully flushed layer with id %d', $layerEntity->getLayerId());
        } catch (Exception $e) {
            $logs[] = sprintf('Failed to flush layer with id %d', $layerEntity->getLayerId());
        }
        return new MessageJsonResponse(data: ['logs' => $logs], message: 'Raster data updated successfully');
    }
}
