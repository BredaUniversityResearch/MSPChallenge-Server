<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Domain\Config\ConfigLoader;
use App\Domain\Config\ConfigParentException;
use App\Domain\Config\ConfigUploads;
use App\Domain\Config\InvalidSessionConfigException;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Form\GameConfigVersionUploadFormType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;

#[Route(
    '/{manager}/gameconfig',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameConfigVersionController extends BaseController
{
    private const string UPLOAD_TOKEN = 'gameconfig.upload.token';
    private const string CANCEL_CSRF_ID = 'gameconfig_upload_cancel';

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
     * Uploading a configuration, together with the generic configs (parents) it needs and the server does not have.
     * When files are missing, what was uploaded is kept (for the session of the user) and the form tells which files
     * to add; nothing is processed before the upload is complete.
     *
     * @throws \Exception
     */
    #[Route('/form', name: 'manager_gameconfig_form')]
    public function gameConfigVersionForm(Request $request, ConfigUploads $uploads): Response
    {
        $session = $request->getSession();
        $token = $session->get(self::UPLOAD_TOKEN);
        $token = is_string($token) ? $token : null;
        $pendingUpload = $uploads->describe($token);
        if ($pendingUpload->token === null) {
            $session->remove(self::UPLOAD_TOKEN);
        }
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $form = $this->createForm(
            GameConfigVersionUploadFormType::class,
            new GameConfigVersion,
            [
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_gameconfig_form'),
                'has_pending_upload' => $pendingUpload->isWaiting(),
            ]
        );
        $form->handleRequest($request);
        $contentsToStore = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $progress = $uploads->submit($token, self::uploadedFiles($form));
            foreach ($progress->errors as $error) {
                $form->get('gameConfigFileActual')->addError(new FormError($error));
            }
            if ($progress->token === null) {
                $session->remove(self::UPLOAD_TOKEN);
            } else {
                $session->set(self::UPLOAD_TOKEN, $progress->token);
            }
            $pendingUpload = $progress->isDone() ? $uploads->describe(null) : $progress;
            $contentsToStore = $progress->contents;
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
        // anything but a finished upload is shown again (status 422, as for any other problem with the form)
        $finished = $contentsToStore !== null;
        return $this->render(
            'manager/GameConfigVersion/gameconfigversion_form.html.twig',
            [
                'gameConfigVersionForm' => $form->createView(),
                'pendingUpload' => $pendingUpload->token === null ? null : $pendingUpload,
            ],
            new Response(null, $form->isSubmitted() && !($form->isValid() && $finished) ? 422 : 200)
        );
    }

    /**
     * Throws away the files of the upload that is waiting for more files.
     */
    #[Route('/form/cancel', name: 'manager_gameconfig_form_cancel', methods: ['POST'])]
    public function gameConfigVersionFormCancel(Request $request, ConfigUploads $uploads): Response
    {
        if (!$this->isCsrfTokenValid(self::CANCEL_CSRF_ID, (string)$request->request->get('_token'))) {
            return new Response(null, 400);
        }
        $session = $request->getSession();
        $token = $session->get(self::UPLOAD_TOKEN);
        $uploads->cancel(is_string($token) ? $token : null);
        $session->remove(self::UPLOAD_TOKEN);
        return new Response(null, 204);
    }

    /**
     * @return array<string, string> the uploaded files: name => contents
     */
    private static function uploadedFiles(FormInterface $form): array
    {
        $files = [];
        /** @var iterable<UploadedFile> $uploadedFiles */
        $uploadedFiles = $form->get('gameConfigFileActual')->getData() ?? [];
        foreach ($uploadedFiles as $uploaded) {
            $contents = file_get_contents($uploaded->getRealPath());
            $files[$uploaded->getClientOriginalName()] = $contents === false ? '' : $contents;
        }
        return $files;
    }
}
