<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Domain\Services\TosDocumentService;
use App\Entity\ServerManager\TermsAcceptance;
use App\Entity\ServerManager\TermsVersion;
use App\Entity\ServerManager\User;
use App\Repository\ServerManager\TermsAcceptanceRepository;
use App\Repository\ServerManager\TermsVersionRepository;
use Exception;
use League\CommonMark\Exception\CommonMarkException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(
    path: '/{manager}/terms',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class TermsController extends BaseController
{
    public function __construct(
        string $projectDir,
        ConnectionManager $connectionManager,
        SymfonyToLegacyHelper $symfonyToLegacyHelper,
        private readonly TosDocumentService $tosDocumentService,
        private readonly RouterInterface $router
    ) {
        parent::__construct($projectDir, $connectionManager, $symfonyToLegacyHelper);
    }

    /**
     * @throws CommonMarkException
     * @throws Exception
     */
    #[Route(name: 'manager_terms')]
    public function index(): Response
    {
        $currentTerms = $this->getCurrentTerms();

        return $this->render('manager/terms_page.html.twig', [
            'terms' => $currentTerms,
            'documentHtml' => $this->tosDocumentService->renderDocument($currentTerms->getFilePath()),
            'acknowledgmentHtml' => $this->tosDocumentService->extractSection(
                $currentTerms->getFilePath(),
                $currentTerms->getAcknowledgmentHeadingPattern()
            ),
        ]);
    }

    /**
     * @throws CommonMarkException
     */
    #[Route('/document', name: 'manager_terms_document')]
    public function document(Request $request): Response
    {
        $path = (string) $request->query->get('path', '');

        return new Response($this->tosDocumentService->renderDocument($path));
    }

    /**
     * @throws Exception
     */
    #[Route('/accept', name: 'manager_terms_accept', methods: ['POST'])]
    public function accept(Request $request, Security $security): Response
    {
        if (!$this->isCsrfTokenValid('terms_accept', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $currentTerms = $this->getCurrentTerms();

        /** @var User $user */
        $user = $security->getUser();

        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        $acceptanceRepository = $entityManager->getRepository(TermsAcceptance::class);

        /** @var TermsAcceptanceRepository $acceptanceRepository */
        if (!$acceptanceRepository->hasAccepted($user, $currentTerms)) {
            $acceptance = new TermsAcceptance();
            $acceptance->setUser($user);
            $acceptance->setTermsVersion($currentTerms);
            $acceptance->setAcceptedAt(new \DateTime());
            $entityManager->persist($acceptance);
            $entityManager->flush();
        }

        return new RedirectResponse($this->resolveTargetPath($request));
    }

    private function resolveTargetPath(Request $request): string
    {
        $target = $request->getSession()->remove('terms.target_path');

        if (is_string($target) && $this->isSafeManagerTarget($target)) {
            return $target;
        }

        return $this->generateUrl('manager');
    }

    private function isSafeManagerTarget(string $target): bool
    {
        $path = parse_url($target, PHP_URL_PATH) ?? '';

        if (str_ends_with($path, '.md')) {
            return false;
        }

        try {
            $this->router->match($path);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    private function getCurrentTerms(): TermsVersion
    {
        /** @var TermsVersionRepository $repo */
        $repo = $this->connectionManager->getServerManagerEntityManager()->getRepository(TermsVersion::class);
        $currentTerms = $repo->getCurrent();
        if (null === $currentTerms) {
            throw new NotFoundHttpException('No terms of service configured.');
        }

        return $currentTerms;
    }
}
