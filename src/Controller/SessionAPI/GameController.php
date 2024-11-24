<?php

namespace App\Controller\SessionAPI;

use App\Controller\BaseController;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\SymfonyToLegacyHelper;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/Game')]
#[OA\Tag(name: 'Game', description: 'Operations related to game management')]
class GameController extends BaseController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    #[Route(
        path: '/CreatePOVConfig',
        name: 'session_api_game_create_pov_config',
        methods: ['POST']
    )]
    #[OA\Post(
        summary: 'Create POV Config',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'region_bottom_left_x', type: 'number'),
                    new OA\Property(property: 'region_bottom_left_y', type: 'number'),
                    new OA\Property(property: 'region_top_right_x', type: 'number'),
                    new OA\Property(property: 'region_top_right_y', type: 'number'),
                    new OA\Property(property: 'output_image_format', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'excl_layers_by_tags',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        nullable: true
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'POV Config created successfully',
                content: new OA\MediaType(
                    mediaType: 'application/zip',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 400, description: 'Invalid region coordinates'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function createPOVConfig(
        Request $request,
        LoggerInterface $logger,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): StreamedResponse|JsonResponse {
        $sessionId = $this->getSessionIdFromRequest($request);
        $regionBottomLeftX = $request->request->get('region_bottom_left_x');
        $regionBottomLeftY = $request->request->get('region_bottom_left_y');
        $regionTopRightX = $request->request->get('region_top_right_x');
        $regionTopRightY = $request->request->get('region_top_right_y');
        if (!is_numeric($regionBottomLeftX) ||
            !is_numeric($regionBottomLeftY) ||
            !is_numeric($regionTopRightX) ||
            !is_numeric($regionTopRightY)) {
            return new JsonResponse(
                Router::formatResponse(false, 'Invalid region coordinates', null, __CLASS__, __FUNCTION__),
                Response::HTTP_BAD_REQUEST
            );
        }

        $region = new Region($regionBottomLeftX, $regionBottomLeftY, $regionTopRightX, $regionTopRightY);
        $configCreator = new ConfigCreator($this->projectDir, $sessionId, $logger);
        try {
            if ($request->request->has('output_image_format')) {
                $configCreator->setOutputImageFormat(
                    $request->request->get('output_image_format') ?: ConfigCreator::DEFAULT_IMAGE_FORMAT
                );
            }
            if ($request->request->has('excl_layers_by_tags')) {
                $exclLayerByTags = json_decode(
                    $request->request->get('excl_layers_by_tags'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                $exclLayerByTags = is_array($exclLayerByTags) ? $exclLayerByTags : [];
                $configCreator->setExcludedLayersByTags(array_map(
                    fn($s) => new LayerTags($s),
                    $exclLayerByTags
                ));
            }
            $zipFilepath = $configCreator->createAndZip($region);
        } catch (\Exception $e) {
            return new JsonResponse(
                Router::formatResponse(false, $e->getMessage(), null, __CLASS__, __FUNCTION__),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Create a StreamedResponse
        $response = new StreamedResponse(function () use ($zipFilepath) {
            $fileStream = fopen($zipFilepath, 'rb');
            fpassthru($fileStream);
            fclose($fileStream);

            $filesystem = new Filesystem();
            $filesystem->remove($zipFilepath);
        });

        // Set response headers
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($zipFilepath)
        ));
        $response->headers->set('Content-Length', (string)filesize($zipFilepath));

        return $response;
    }
}
