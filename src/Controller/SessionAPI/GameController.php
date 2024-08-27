<?php

namespace App\Controller\SessionAPI;

use App\Domain\Services\ConnectionManager;
use App\Controller\BaseController;
use App\Domain\API\v1\Plan;
use App\Domain\API\v1\Router;
use App\Domain\POV\ConfigCreator;
use App\Domain\POV\LayerTags;
use App\Domain\POV\Region;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\Game;
use App\Domain\Common\EntityEnums\GameStateValue;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Domain\Communicator\WatchdogCommunicator;
use App\Entity\ServerManager\GameList;

use function App\await;

class GameController extends BaseController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    // not a route yet, should replace /[sessionId]/api/Game/State one day
    public function state(
        int $sessionId,
        string $state,
        WatchdogCommunicator $watchdogCommunicator,
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): void {
        $state = new GameStateValue(strtolower($state));
        $em = ConnectionManager::getInstance()->getGameSessionEntityManager($sessionId);
        $game = $em->getRepository(Game::class)->retrieve();
        $currentState = $game->getGameState();
        if ($currentState == GameStateValue::END || $currentState == GameStateValue::SIMULATION) {
            throw new \Exception("Invalid current state of ".$currentState);
        }
        if ($currentState == GameStateValue::SETUP) {
            //Starting plans should be implemented when we finish the SETUP phase (PAUSE, PLAY, FASTFORWARD request)
            $plan = new Plan();
            $plan->setGameSessionId($sessionId);
            await($plan->updateLayerState(0));
            if ($state == GameStateValue::PAUSE) {
                $game->setGameCurrentMonth(0);
            }
        }
        $game->setGameLastUpdate(microtime(true)); // note: not using mysql's UNIX_TIMESTAMP(NOW(6)) function here
        $game->setGameState($state);
        $em->flush();
        $watchdogCommunicator->changeState($sessionId, $state);
    }

    public function createPOVConfig(
        int $sessionId,
        Request $request,
        LoggerInterface $logger,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $symfonyToLegacyHelper
    ): StreamedResponse|JsonResponse {
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
