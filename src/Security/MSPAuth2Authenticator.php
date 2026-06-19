<?php
namespace App\Security;

use App\Domain\Communicator\Auth2Communicator;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\Setting;
use App\Entity\ServerManager\User;
use App\Exception\MSPAuth2RedirectException;
use DateTime;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MSPAuth2Authenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private Auth2Communicator $auth2Communicator;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ConnectionManager $connectionManager
    ) {
        $this->auth2Communicator = new Auth2Communicator($this->client);
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return true;
    }

    /**
     * @throws \Exception
     */
    public function authenticate(Request $request): Passport
    {
        // get the token from Session or GET
        $apiToken = $request->query->get('token') ?? $request->request->get('token') ??
            $request->getSession()->get('token') ?? null;
        if (empty($apiToken)) {
            throw new MSPAuth2RedirectException();
        }
        // basic check of token validity
        $parser = new Parser(new JoseEncoder());
        try {
            /** @var UnencryptedToken $unencryptedToken */
            $unencryptedToken = $parser->parse($apiToken);
            $validator = new Validator();
            $validator->assert($unencryptedToken, new LooseValidAt(new Clock()));
        } catch (Exception $e) {
            $request->getSession()->remove('token');
            throw new MSPAuth2RedirectException();
        }
        // retrieve some user details from the token
        $user = $this->connectionManager->getServerManagerEntityManager()->getRepository(User::class)->findOneBy(
            [
                'username' => $unencryptedToken->claims()->get('username')
            ]
        );
        if (is_null($user)) {
            $user = new User();
            $user->setUsername($unencryptedToken->claims()->get('username'));
            $user->setId($unencryptedToken->claims()->get('id'));
            $user->setToken($apiToken);
            $user->setRefreshToken('unused');
            $user->setRefreshTokenExpiration(new DateTime());
            $this->connectionManager->getServerManagerEntityManager()->persist($user);
        } else {
            $user->setToken($apiToken);
        }
        // authorization through auth2.mspchallenge.info using the token, but only when first obtained through GET
        $this->auth2Communicator->setToken($apiToken);
        if (empty($request->getSession()->get('token')) && !$this->authorized($user, $request)) {
            throw new AccessDeniedHttpException(
                'You do not have permission to access this MSP Challenge Server Manager at this time.'
            );
        }
        $this->connectionManager->getServerManagerEntityManager()->flush();
        // add token to session storage if still required
        if ($request->getSession()->get('token') !== $apiToken) {
            $request->getSession()->set('token', $apiToken);
        }
        // UserBadge parameters are to get Symfony to continue, second is the actual user object
        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user),
            [new PreAuthenticatedUserBadge()]
        );
    }

    private function authorized(User $user, Request $request): bool
    {
        try {
            $serverUUID = $this->connectionManager->getServerManagerEntityManager()
                ->getRepository(Setting::class)->findOneBy(['name' => 'server_uuid']);
            if (is_null($serverUUID)) {
                // including first-time Server Manager use registration, if still required
                $serverUUID = $this->register($user, $request);
            }
            $response = collect(
                collect($this->auth2Communicator->getResource(
                    sprintf(
                        'servers/%s/server_users',
                        $serverUUID->getValue()
                    )
                ))->pull('hydra:member')
            )->filter(function ($value) use ($user) {
                // when true, only that array item is filtered out of the collection
                return (string) str_replace('/api/users/', '', $value['user']['username']) === $user->getUsername();
            });
            return !$response->isEmpty();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @throws \Exception
     */
    private function register(User $user, Request $request): Setting
    {
        $serverId = new Setting('server_id', uniqid('', true));
        $serverName = new Setting('server_name', $user->getUserIdentifier().'_'.date('Ymd'));
        $serverDescription = new Setting(
            'server_description',
            'This is a new MSP Challenge server installation. The administrator has not '.
            'changed this default description yet. This can be done through the ServerManager.'
        );
        $serverUuid = new Setting('server_uuid', Uuid::v4());
        $serverPassword = new Setting('server_password', (string) time());
        $mspServerManagerEntityManager = $this->connectionManager->getServerManagerEntityManager();
        $mspServerManagerEntityManager->persist($serverId);
        $mspServerManagerEntityManager->persist($serverName);
        $mspServerManagerEntityManager->persist($serverDescription);
        $mspServerManagerEntityManager->persist($serverUuid);
        $mspServerManagerEntityManager->persist($serverPassword);
        $mspServerManagerEntityManager->flush();

        $params = ["server" =>
            [
                "uuid" => $serverUuid->getValue(),
                "serverID" => $serverId->getValue(),
                "password" => $serverPassword->getValue(),
                "serverName" => $serverName->getValue()
            ],
            "user" => "/api/users/".$user->getId()
        ];
        try {
            $this->auth2Communicator->postResource("server_users", $params);
        } catch (ExceptionInterface $e) {
            throw new AccessDeniedHttpException(
                'You do not have permission to access this MSP Challenge Server Manager at this time.'
            );
        }
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add(
                'notice',
                "Welcome! You are now the primary user of this Server Manager with full administrator privileges. ".
                'You can use this application and optionally allow other users too through Settings > User Access. '.
                'Create your first MSP Challenge session by clicking on the button.'
            );
        }
        return $serverUuid;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // not returning a Response here just to keep the code cleaner
        throw new AccessDeniedHttpException($exception->getMessage());
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // not returning a Response here just to keep the code cleaner
        // redirect to auth2.mspchallenge.info by means of throwing an Exception that is listened to
        throw new MSPAuth2RedirectException();
    }
}
