<?php

namespace App\Controller\SessionAPI;

use App\Domain\POV\ConfigCreator;
use App\Domain\POV\Region;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;

class GameController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir
    ) {
    }

    public function createPOVConfig(
        int $sessionId,
        Request $request,
        LoggerInterface $logger
    ): StreamedResponse {
        $regionTopLeftX = $request->request->get('region_top_left_x');
        $regionTopLeftY = $request->request->get('region_top_left_y');
        $regionBottomRightX = $request->request->get('region_bottom_right_x');
        $regionBottomRightY = $request->request->get('region_bottom_right_y');
        if (!is_numeric($regionTopLeftX) ||
            !is_numeric($regionTopLeftY) ||
            !is_numeric($regionBottomRightX) ||
            !is_numeric($regionBottomRightY)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid region coordinates');
        }

        $region = new Region($regionTopLeftX, $regionTopLeftY, $regionBottomRightX, $regionBottomRightY);
        $configCreator = new ConfigCreator($this->projectDir, $sessionId, $logger);
        try {
            $zipFilepath = $configCreator->createAndZip($region);
        } catch (Exception $e) {
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
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

        return $response;
    }
}
