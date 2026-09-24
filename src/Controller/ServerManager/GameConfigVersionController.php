<?php

namespace App\Controller\ServerManager;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameConfigVersionVisibilityValue;
use App\Entity\ServerManager\GameConfigFile;
use App\Entity\ServerManager\GameConfigVersion;
use App\Form\GameConfigVersionUploadFormType;
use JsonSchema\Validator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

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
        int $configId
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
        $response = new BinaryFileResponse($gameConfigFilePath);
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
        KernelInterface $kernel
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $form = $this->createForm(
            GameConfigVersionUploadFormType::class,
            new GameConfigVersion,
            ['entity_manager' => $entityManager, 'action' => $this->generateUrl('manager_gameconfig_form')]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && self::isConfigFileValid($form, $kernel)) {
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
            $form->get('gameConfigFileActual')->getData()->move(
                $this->getParameter('app.server_manager_config_dir').
                    $gameConfigVersion->getGameConfigFile()->getFilename(),
                "{$gameConfigVersion->getGameConfigFile()->getFilename()}_{$gameConfigVersion->getVersion()}.json"
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

    private static function isConfigFileValid(FormInterface $form, KernelInterface $kernel): bool
    {
        $uploadedGameConfigFileContents = json_decode(
            file_get_contents($form->get('gameConfigFileActual')->getData()->getRealPath())
        );
        $validator = new Validator();
        $validator->validate(
            $uploadedGameConfigFileContents,
            json_decode(file_get_contents($kernel->getProjectDir().'/src/Domain/SessionConfigJSONSchema.json'))
        );
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $form->get('gameConfigFileActual')->addError(
                    new FormError(sprintf("[%s] %s", $error['property'], $error['message']))
                );
            }
            return false;
        }
        return true;
    }
}
