<?php

namespace App\Domain\Services;

use App\Domain\API\APIHelper;
use Closure;
use Exception;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class SymfonyToLegacyHelper
{
    private static ?SymfonyToLegacyHelper $instance = null;

    private string $projectDir;
    private UrlGeneratorInterface $urlGenerator;
    private UrlMatcherInterface $urlMatcher;
    private RequestStack $requestStack;
    private ?Closure $fnControllerForwarder = null;

    public function __construct(
        string $projectDir,
        UrlGeneratorInterface $urlGenerator,
        UrlMatcherInterface $urlMatcher,
        RequestStack $requestStack,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        APIHelper $apiHelper,
        ConnectionManager $connectionManager
    ) {
        $this->projectDir = $projectDir;
        $this->urlGenerator = $urlGenerator;
        $this->urlMatcher = $urlMatcher;
        $this->requestStack = $requestStack;
        self::$instance = $this;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
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
