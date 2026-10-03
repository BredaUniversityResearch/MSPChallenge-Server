<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Domain\Config\ConfigLoader;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\InvalidSessionConfigException;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Form\GameConfigVersionUploadFormType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;

#[Route(
    '/{manager}/gameconfig',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameConfigVersionController extends BaseController
{
    #[Route(name: 'manager_gameconfig')]
    public function index(): Response
    {
        return $this->render('manager/gameconfigversion_page.html.twig');
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{visibility}',
        name: 'manager_gameconfig_list',
        requirements: ['visibility' => '(active|archived)']
    )]
    public function gameConfigVersion(
        string $visibility
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameConfigVersions = $entityManager->getRepository(GameConfigVersion::class)
            ->orderedList(['visibility' => $visibility]);
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion.html.twig',
            ['configslist' => $gameConfigVersions]
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{configId}/details',
        name: 'manager_gameconfig_details',
        requirements: ['configId' => '\d+']
    )]
    public function gameConfigVersionDetails(
        int $configId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameConfigVersion = $entityManager->getRepository(GameConfigVersion::class)->find($configId);
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion_details.html.twig',
            ['gameConfigVersion' => $gameConfigVersion]
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{configFileId}/file',
        name: 'manager_gameconfig_file',
        requirements: ['configFileId' => '\d+']
    )]
    public function gameConfigFileDetails(
        int $configFileId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        return $this->json(
            $entityManager->getRepository(GameConfigFile::class)->findAllSimple($configFileId)
        );
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{configId}/download',
        name: 'manager_gameconfig_download',
        requirements: ['configId' => '\d+']
    )]
    public function gameConfigVersionDownload(
        int $configId,
        ConfigLoader $configLoader
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameConfigVersion = $entityManager->getRepository(GameConfigVersion::class)->find($configId);
        if (is_null($gameConfigVersion)) {
            return new Response(null, 422);
        }
        $fileSystem = new FileSystem();
        $gameConfigFilePath = $this->getParameter('app.server_manager_config_dir').$gameConfigVersion->getFilePath();
        if (!$fileSystem->exists($gameConfigFilePath)) {
            return new Response(null, 422);
        }
        // the final config: the stored file (which may be stripped) merged with its parents
        try {
            $response = new Response(
                $configLoader->mergedJsonOfFile($gameConfigFilePath),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (InvalidSessionConfigException | ConfigParentException $e) {
            return new Response($e->getMessage(), 500);
        }
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            pathinfo($gameConfigFilePath)['filename']
        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{configId}/archive',
        name: 'manager_gameconfig_archive',
        requirements: ['configId' => '\d+']
    )]
    public function gameSaveArchive(
        int $configId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameConfigVersion = $entityManager->getRepository(GameConfigVersion::class)->find($configId);
        $gameConfigVersion->setVisibility(new GameConfigVersionVisibilityValue('archived'));
        $entityManager->flush();
        return new Response(null, 204);
    }

    /**
     * @throws \Exception
     */
    #[Route('/form', name: 'manager_gameconfig_form')]
    public function gameConfigVersionForm(
        Request $request,
        ConfigLoader $configLoader
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $form = $this->createForm(
            GameConfigVersionUploadFormType::class,
            new GameConfigVersion,
            ['entity_manager' => $entityManager, 'action' => $this->generateUrl('manager_gameconfig_form')]
        );
        $form->handleRequest($request);
        $contentsToStore = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $contentsToStore = self::checkUploadedConfig($form, $configLoader);
        }
        if ($contentsToStore !== null) {
            $gameConfigVersion = $form->getData();
            if (is_null($gameConfigVersion->getGameConfigFile())) {
                $gameConfigFile = new GameConfigFile;
                $gameConfigFile->setFilename($form->get('filename')->getData());
                $gameConfigFile->setDescription($form->get('description')->getData());
                $gameConfigVersion->setGameConfigFile($gameConfigFile);
                $gameConfigVersion->setVersion(1);
                (new Filesystem)->
                    mkdir($this->getParameter('app.server_manager_config_dir').$gameConfigFile->getFilename());
            } else {
                $gameConfigVersion->setVersion(
                    $entityManager->getRepository(GameConfigVersion::class)
                        ->findLatestVersion($gameConfigVersion->getGameConfigFile())->getVersion() + 1
                );
            }
            // the config as it is stored: the final config, complete (so it needs no parent file)
            (new Filesystem)->dumpFile(
                $this->getParameter('app.server_manager_config_dir').
                    $gameConfigVersion->getGameConfigFile()->getFilename().'/'.
                    "{$gameConfigVersion->getGameConfigFile()->getFilename()}_{$gameConfigVersion->getVersion()}.json",
                $contentsToStore
            );
            $entityManager->persist($gameConfigVersion);
            $entityManager->flush();
        }
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion_form.html.twig',
            ['gameConfigVersionForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    /**
     * Checks the uploaded config: it has to be valid JSON, its parents (if it names any) have to be known, and its
     * final config has to match the schema. The errors, with the line of a syntax error, are shown on the form.
     *
     * @return ?string what to store: the final config, complete and in the new shape; null when the config is not
     *         valid
     * @throws \JsonException
     */
    private static function checkUploadedConfig(FormInterface $form, ConfigLoader $configLoader): ?string
    {
        $uploaded = $form->get('gameConfigFileActual')->getData()->getRealPath();
        $check = $configLoader->checkUpload((string)file_get_contents($uploaded));
        foreach ($check->errors as $error) {
            $form->get('gameConfigFileActual')->addError(new FormError($error));
        }
        return $check->contents;
    }
}
