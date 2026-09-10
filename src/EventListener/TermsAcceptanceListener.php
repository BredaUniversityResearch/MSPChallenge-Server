<?php

namespace App\EventListener;

use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\TermsAcceptance;
use App\Entity\ServerManager\TermsVersion;
use App\Entity\ServerManager\User;
use App\Repository\ServerManager\TermsAcceptanceRepository;
use App\Repository\ServerManager\TermsVersionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class TermsAcceptanceListener
{
    // routes that must remain reachable even when terms are outstanding
    private const EXEMPT_ROUTES = [
        'manager_terms',
        'manager_terms_document',
        'manager_terms_accept',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly ConnectionManager $connectionManager,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // only guard the manager/ServerManager area
        if (!preg_match('#^/(manager|ServerManager)#', $request->getPathInfo())) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (in_array($route, self::EXEMPT_ROUTES, true)) {
            return;
        }

        // this call also triggers authentication on the lazy 'manager' firewall
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // not authenticated (or hit an open route like manager/gamelist) - nothing to guard yet
            return;
        }

        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        /** @var TermsVersionRepository $termsVersionRepository */
        $termsVersionRepository = $entityManager->getRepository(TermsVersion::class);
        $currentTerms = $termsVersionRepository->getCurrent();
        if (null === $currentTerms) {
            // no terms configured yet - nothing to enforce
            return;
        }

        /** @var TermsAcceptanceRepository $termsAcceptanceRepository */
        $termsAcceptanceRepository = $entityManager->getRepository(TermsAcceptance::class);
        if ($termsAcceptanceRepository->hasAccepted($user, $currentTerms)) {
            return;
        }

        $request->getSession()->set('terms.target_path', $request->getUri());
        $event->setResponse(new RedirectResponse(
            $this->router->generate('manager_terms', [], UrlGeneratorInterface::ABSOLUTE_URL)
        ));
    }
}
