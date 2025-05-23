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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SymfonyToLegacyHelper
{
    private static ?SymfonyToLegacyHelper $instance = null;
    private ?Closure $fnControllerForwarder = null;

    public function __construct(
        private readonly string $projectDir,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly Kernel $kernel,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly VersionsProvider $provider,
        private readonly LoggerInterface $analyticsLogger,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly SimulationHelper $simulationHelper,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        private readonly ConnectionManager $connectionManager,
        // @phpstan-ignore-next-line "Property is never read, only written"
        private readonly APIHelper $apiHelper
    ) {
        self::$instance = $this;
    }

    /**
     * @return AuthenticationSuccessHandler
     */
    public function getAuthenticationSuccessHandler(): AuthenticationSuccessHandler
    {
        return $this->authenticationSuccessHandler;
    }

    public function getSimulationHelper(): SimulationHelper
    {
        return $this->simulationHelper;
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

    /**
     * @return MessageBusInterface
     */
    public function getMessageBus(): MessageBusInterface
    {
        return $this->messageBus;
    }

    /**
     * @throws Exception
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->connectionManager->getServerManagerEntityManager();
    }

    public function getProvider(): VersionsProvider
    {
        return $this->provider;
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
    ): void {
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
}
