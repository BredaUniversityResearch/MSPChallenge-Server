<?php

namespace App\Controller\ServerManager;

use App\Entity\ServerManager\GameList;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;
use App\Domain\Common\EntityEnums\GameSaveVisibilityValue;
use App\Entity\ServerManager\GameSave;
use App\Form\GameListAddBySaveLoadFormType;
use App\Form\GameSaveEditFormType;
use App\Message\GameSave\GameSaveLoadMessage;
use App\Domain\Common\GameSaveZipFileValidator;
use App\Domain\Services\SymfonyToLegacyHelper;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\KernelInterface;

#[Route(
    '/{manager}/gamesave',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class GameSaveController extends BaseController
{
    // Ceiling on the *assembled* save file, independent of any php.ini upload limit. Tune as needed.
    private const MAX_UPLOAD_BYTES = 5_368_709_120; // 5GB

    // How long an abandoned in-progress upload's temp files are kept before being swept away.
    private const STALE_UPLOAD_MAX_AGE_SECONDS = 21_600; // 6 hours

    private const UPLOAD_TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    #[Route(name: 'manager_gamesave')]
    public function index(): Response
    {
        return $this->render('manager/gamesave_page.html.twig');
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{saveVisibility}',
        name: 'manager_gamesave_list',
        requirements: ['saveVisibility' => '(active|archived)']
    )]
    public function gameSave(
        string $saveVisibility
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameSaves = $entityManager->getRepository(GameSave::class)->findBy(['saveVisibility' => $saveVisibility]);
        return $this->render('manager/GameSave/gamesave.html.twig', ['gameSaves' => $gameSaves]);
    }

    /**
     * @throws \Exception
     */
    #[Route(
        '/{saveId}/download',
        name: 'manager_gamesave_download',
        requirements: ['saveId' => '\d+']
    )]
    public function gameSaveDownload(
        int $saveId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        if (is_null($gameSave)) {
            return new Response(null, 422);
        }
        $fileSystem = new Filesystem();

        $saveFileName = sprintf($this->getParameter('app.server_manager_save_name'), $saveId);
        $saveFilePath = $this->getParameter('app.server_manager_save_dir').$saveFileName;
        if (!$fileSystem->exists($saveFilePath)) {
            return new Response(null, 422);
        }
        $response = new BinaryFileResponse($saveFilePath);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $saveFileName
        );
        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    /**
     * @throws \Exception
     */
    #[Route('/{saveId}/form', name: 'manager_gamesave_form', requirements: ['saveId' => '\d+'])]
    public function gameSaveForm(
        Request $request,
        MessageBusInterface $messageBus,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        int $saveId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $form = $this->createForm(
            GameListAddBySaveLoadFormType::class,
            new GameList(),
            [
                'save' => $saveId,
                'entity_manager' => $entityManager,
                'action' => $this->generateUrl('manager_gamesave_form', ['saveId' => $saveId])
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSession = $form->getData();
            $entityManager->persist($gameSession);
            $entityManager->flush();
            $messageBus->dispatch(
                new GameSaveLoadMessage($gameSession->getId(), $gameSession->getGameSave()->getId())
            );
            return new Response($gameSession->getId(), 200);
        }
        return $this->render(
            'manager/GameSave/gamesave_form.html.twig',
            ['gameSaveForm' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    /**
     * @throws \Exception
     */
    #[Route('/{saveId}/details', name: 'manager_gamesave_details', requirements: ['saveId' => '\d+'])]
    public function gameSaveDetails(
        Request $request,
        int $saveId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $form = $this->createForm(
            GameSaveEditFormType::class,
            $gameSave,
            ['action' => $this->generateUrl('manager_gamesave_details', ['saveId' => $saveId])]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $gameSave = $form->getData();
            $entityManager->flush();
        }
        return $this->render(
            'manager/GameSave/gamesave_details.html.twig',
            [
                'gameSaveForm' => $form->createView(),
                'gameSave' => $gameSave
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }

    #[Route('/upload', name: 'manager_gamesave_upload')]
    public function gameSaveUpload(): Response
    {
        return $this->render('manager/GameSave/gamesave_upload.html.twig');
    }

    /**
     * Starts a new chunked upload: validates the declared size, allocates a random token, and
     * creates the (empty) temp file the chunks will be appended to.
     */
    #[Route('/upload/init', name: 'manager_gamesave_upload_init', methods: ['POST'])]
    public function gameSaveUploadInit(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('gamesave_upload', (string)$request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['errors' => ['Invalid CSRF token, please reload the page and try again.']], 403);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $totalSize = (int)($payload['totalSize'] ?? 0);
        $filename = (string)($payload['filename'] ?? 'save.zip');

        if ($totalSize <= 0 || $totalSize > self::MAX_UPLOAD_BYTES) {
            return new JsonResponse(['errors' => [sprintf(
                'File size must be between 1 byte and %s.',
                $this->formatBytes(self::MAX_UPLOAD_BYTES)
            )]], 422);
        }

        $tmpDir = $this->getUploadTmpDir();
        $this->cleanupStaleUploads($tmpDir);

        $token = bin2hex(random_bytes(16));
        touch($this->getUploadPartPath($tmpDir, $token));
        $this->writeUploadMeta($tmpDir, $token, [
            'totalSize' => $totalSize,
            'filename' => $filename,
            'expectedIndex' => 0,
        ]);

        return new JsonResponse(['token' => $token]);
    }

    /**
     * Appends one chunk (raw request body) to the upload's temp file. Chunks must arrive in
     * order; anything else aborts the upload, since this is a "restart from scratch" flow.
     */
    #[Route('/upload/chunk', name: 'manager_gamesave_upload_chunk', methods: ['POST'])]
    public function gameSaveUploadChunk(Request $request): JsonResponse
    {
        $token = $this->getUploadToken($request);
        if ($token === null) {
            return new JsonResponse(['errors' => ['Missing or invalid upload token.']], 400);
        }

        $tmpDir = $this->getUploadTmpDir();
        $meta = $this->readUploadMeta($tmpDir, $token);
        $partFile = $this->getUploadPartPath($tmpDir, $token);
        if ($meta === null || !is_file($partFile)) {
            return new JsonResponse(['errors' => ['Unknown or expired upload. Please restart the upload.']], 404);
        }

        $chunkIndex = (int)$request->headers->get('X-Chunk-Index', '-1');
        if ($chunkIndex !== $meta['expectedIndex']) {
            $this->cleanupUploadFiles($tmpDir, $token);
            return new JsonResponse(
                ['errors' => ['Upload chunks arrived out of order. Please restart the upload.']],
                409
            );
        }

        $fileHandle = fopen($partFile, 'ab');
        if ($fileHandle === false || !flock($fileHandle, LOCK_EX)) {
            return new JsonResponse(['errors' => ['Could not write chunk to server.']], 500);
        }
        $inputStream = fopen('php://input', 'rb');
        stream_copy_to_stream($inputStream, $fileHandle);
        fclose($inputStream);
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);

        if (filesize($partFile) > $meta['totalSize']) {
            $this->cleanupUploadFiles($tmpDir, $token);
            return new JsonResponse(['errors' => ['Uploaded data exceeds the declared file size.']], 413);
        }

        $meta['expectedIndex']++;
        $this->writeUploadMeta($tmpDir, $token, $meta);

        return new JsonResponse(['receivedBytes' => filesize($partFile)]);
    }

    /**
     * Called once every chunk has been sent. Verifies the assembled file is the expected size,
     * then runs the existing GameSaveZipFileValidator against it exactly as the old single-shot
     * upload did, and moves it into place on success.
     *
     * @throws \Exception
     */
    #[Route('/upload/complete', name: 'manager_gamesave_upload_complete', methods: ['POST'])]
    public function gameSaveUploadComplete(Request $request, KernelInterface $kernel): JsonResponse
    {
        if (!$this->isCsrfTokenValid('gamesave_upload', (string)$request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['errors' => ['Invalid CSRF token, please reload the page and try again.']], 403);
        }

        $token = $this->getUploadToken($request);
        if ($token === null) {
            return new JsonResponse(['errors' => ['Missing or invalid upload token.']], 400);
        }

        $tmpDir = $this->getUploadTmpDir();
        $meta = $this->readUploadMeta($tmpDir, $token);
        $partFile = $this->getUploadPartPath($tmpDir, $token);
        if ($meta === null || !is_file($partFile)) {
            return new JsonResponse(['errors' => ['Unknown or expired upload. Please restart the upload.']], 404);
        }

        if (filesize($partFile) !== $meta['totalSize']) {
            $this->cleanupUploadFiles($tmpDir, $token);
            return new JsonResponse(['errors' => [sprintf(
                'Upload incomplete: expected %s but received %s. Please try again.',
                $this->formatBytes($meta['totalSize']),
                $this->formatBytes(filesize($partFile))
            )]], 422);
        }

        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameSaveZip = new GameSaveZipFileValidator($partFile, $kernel, $this->connectionManager);

        if (!$gameSaveZip->isValid()) {
            $this->cleanupUploadFiles($tmpDir, $token);
            return new JsonResponse(['errors' => $gameSaveZip->getErrors()], 422);
        }

        $gameSave = $gameSaveZip->getGameSave();
        $entityManager->persist($gameSave);
        $entityManager->flush();

        try {
            $newFilename = sprintf($this->getParameter('app.server_manager_save_name'), $gameSave->getId());
            // Same volume as the temp dir, so this is an atomic rename, not a slow cross-device copy.
            new Filesystem()->rename(
                $partFile,
                $this->getParameter(
                    'app.server_manager_save_dir'
                ).$newFilename,
                true
            );
        } catch (IOExceptionInterface $e) {
            return new JsonResponse(['errors' => ['Could not save ZIP file on the server.']], 500);
        } finally {
            @unlink($tmpDir.'/'.$token.'.meta');
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/{saveId}/archive', name: 'manager_gamesave_archive', requirements: ['saveId' => '\d+'])]
    public function gameSaveArchive(
        int $saveId
    ): Response {
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $gameSave = $entityManager->getRepository(GameSave::class)->find($saveId);
        $gameSave->setSaveVisibility(new GameSaveVisibilityValue('archived'));
        $entityManager->flush();
        return new Response(null, 204);
    }

    /**
     * Temp files live inside the save directory itself (not sys_get_temp_dir()) so the final
     * rename() in gameSaveUploadComplete() is a same-filesystem, atomic, near-instant move even
     * for multi-gigabyte files, rather than a slow cross-device copy.
     */
    private function getUploadTmpDir(): string
    {
        $dir = rtrim($this->getParameter('app.server_manager_save_dir'), '/').'/uploads_tmp';
        (new Filesystem())->mkdir($dir);
        return $dir;
    }

    private function getUploadPartPath(string $tmpDir, string $token): string
    {
        return $tmpDir.'/'.$token.'.part';
    }

    /**
     * Best-effort sweep of abandoned uploads (closed tab, crashed browser, etc). Run opportunistically
     * on init rather than via a cron job, since that's the only place we know we're about to write more.
     */
    private function cleanupStaleUploads(string $tmpDir): void
    {
        foreach (glob($tmpDir.'/*.{part,meta}', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) && (time() - (int)filemtime($file)) > self::STALE_UPLOAD_MAX_AGE_SECONDS) {
                @unlink($file);
            }
        }
    }

    private function cleanupUploadFiles(string $tmpDir, string $token): void
    {
        @unlink($this->getUploadPartPath($tmpDir, $token));
        @unlink($tmpDir.'/'.$token.'.meta');
    }

    private function readUploadMeta(string $tmpDir, string $token): ?array
    {
        $metaFile = $tmpDir.'/'.$token.'.meta';
        if (!is_file($metaFile)) {
            return null;
        }
        $meta = json_decode((string)file_get_contents($metaFile), true);
        return is_array($meta) ? $meta : null;
    }

    private function writeUploadMeta(string $tmpDir, string $token, array $meta): void
    {
        file_put_contents($tmpDir.'/'.$token.'.meta', json_encode($meta), LOCK_EX);
    }

    /**
     * The upload token acts as a capability for chunk/complete requests, scoped to files this
     * controller itself created in init(). Validated by format only here; existence is checked
     * by the caller against the temp dir.
     */
    private function getUploadToken(Request $request): ?string
    {
        $token = (string)$request->headers->get('X-Upload-Token', '');
        return preg_match(self::UPLOAD_TOKEN_PATTERN, $token) ? $token : null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = $bytes;
        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }
        return sprintf('%.1f%s', $value, $units[$unitIndex]);
    }
}
