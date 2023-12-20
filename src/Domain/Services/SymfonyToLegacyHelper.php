<?php

namespace App\Domain\Services;

use App\Domain\API\APIHelper;
use App\Kernel;
use App\VersionsProvider;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SymfonyToLegacyHelper
{
    private static ?SymfonyToLegacyHelper $instance = null;

    private string $projectDir;
    private UrlGeneratorInterface $urlGenerator;
    private UrlMatcherInterface $urlMatcher;
    private RequestStack $requestStack;
    private Kernel $kernel;
    private TranslatorInterface $translator;
    private ?Closure $fnControllerForwarder = null;
    private EntityManagerInterface $em;

    private VersionsProvider $provider;
    private MessageBusInterface $analyticsMessageBus;
    private LoggerInterface $analyticsLogger;

    private AuthenticationSuccessHandler $authenticationSuccessHandler;

    public function __construct(
        string $projectDir,
        UrlGeneratorInterface $urlGenerator,
        UrlMatcherInterface $urlMatcher,
        RequestStack $requestStack,
        Kernel $kernel,
        TranslatorInterface $translator,
        EntityManagerInterface $em,
        VersionsProvider $provider,
        MessageBusInterface $analyticsMessageBus,
        LoggerInterface $analyticsLogger,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        APIHelper $apiHelper,
        ConnectionManager $connectionManager,
        AuthenticationSuccessHandler $authenticationSuccessHandler
    ) {
        $this->projectDir = $projectDir;
        $this->urlGenerator = $urlGenerator;
        $this->urlMatcher = $urlMatcher;
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
        $this->translator = $translator;
        $this->em = $em;
        $this->provider = $provider;
        $this->analyticsMessageBus = $analyticsMessageBus;
        $this->analyticsLogger = $analyticsLogger;
        $this->authenticationSuccessHandler = $authenticationSuccessHandler;
        self::$instance = $this;
    }

    /**
     * @return AuthenticationSuccessHandler
     */
    public function getAuthenticationSuccessHandler(): AuthenticationSuccessHandler
    {
        return $this->authenticationSuccessHandler;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getKernel(): Kernel
    {
        return $this->kernel;
    }

    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    public function getProvider(): VersionsProvider
    {
        return $this->provider;
    }

    public function getAnalyticsMessageBus(): MessageBusInterface
    {
        return $this->analyticsMessageBus;
    }

    public function getAnalyticsLogger(): LoggerInterface
    {
        return $this->analyticsLogger;
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            throw new Exception(
                'Instance is unavailable. It should be set by first constructor call, using Symfony services.'
            );
        }
        return self::$instance;
    }

    public function setControllerForwarder(
        ?Closure $fnControllerForwarder
    ) {
        $this->fnControllerForwarder = $fnControllerForwarder;
    }

    public function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function getUrlGenerator(): UrlGeneratorInterface
    {
        return $this->urlGenerator;
    }

    public function getUrlMatcher(): UrlMatcherInterface
    {
        return $this->urlMatcher;
    }

    public function getCurrentRouteName(): ?string
    {
        if (null === $request = $this->getRequest()) {
            return null;
        }
        return $this->getRootRequestRouteName($request);
    }

    /**
     * @param array|string $mixRouteNames
     * @return bool
     */
    public function matchRouteNames($mixRouteNames): bool
    {
        if (empty($mixRouteNames)) {
            return false;
        }
        if (null === $routeName = $this->getCurrentRouteName()) {
            return false;
        }
        if (!is_array($mixRouteNames)) {
            $mixRouteNames = array($mixRouteNames);
        }
        $mixRouteNames = array_combine($mixRouteNames, $mixRouteNames);
        return isset($mixRouteNames[$routeName]);
    }

    /**
     * @throws Exception
     */
    public function forward(string $controller, array $path = [], array $query = [])
    {
        if (null === $this->fnControllerForwarder) {
            throw new Exception('The controller forwarder is not set.');
        }
        return ($this->fnControllerForwarder)($controller, $path, $query);
    }

    public function getRootRequestRouteName(Request $request): ?string
    {
        $rootRequestAttributes = $this->getRootRequestAttributes($request);
        if (!$rootRequestAttributes->has('_route')) {
            return null;
        }
        return $rootRequestAttributes->get('_route');
    }

    /**
     * @param Request $request
     * @return mixed|ParameterBag|null
     */
    public function getRootRequestAttributes(Request $request)
    {
        $requestAttributes = null;
        if ($request->attributes->has('_forwarded')) {
            $requestAttributes = $request->attributes->get('_forwarded');
            while ($requestAttributes->has('_forwarded')) {
                $requestAttributes = $request->attributes->get('_forwarded');
            }
        }
        if (!is_null($requestAttributes)) {
            return $requestAttributes;
        }
        return $request->attributes;
    }
}
