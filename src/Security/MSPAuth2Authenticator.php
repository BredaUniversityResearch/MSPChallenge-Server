<?php
namespace App\Security;

use App\Domain\Communicators\Auth2Communicator;
use App\Entity\ServerManager\Setting;
use App\Entity\ServerManager\User;
use App\Exception\MSPAuth2RedirectException;
use Doctrine\ORM\EntityManagerInterface;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly EntityManagerInterface $mspServerManagerEntityManager
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

    public function authenticate(Request $request): Passport
    {
        // get the token from Session or GET
        $apiToken = $request->get('token') ?? $request->getSession()->get('token') ?? null;
        if (empty($apiToken)) {
            throw new MSPAuth2RedirectException();
        }
        // basic check of token validity
        $parser = new Parser(new JoseEncoder());
        try {
            /** @var UnencryptedToken $unencryptedToken */
            $unencryptedToken = $parser->parse($apiToken);
            $validator = new Validator();
            $validator->assert($unencryptedToken, new LooseValidAt(new FrozenClock(new \DateTimeImmutable())));
        } catch (Exception $e) {
            $request->getSession()->remove('token');
            throw new MSPAuth2RedirectException();
        }
        $this->auth2Communicator->setToken($apiToken);
        // retrieve some user details from the token
        $user = new User();
        $user->setUsername($unencryptedToken->claims()->get('username'));
        $user->setId($unencryptedToken->claims()->get('id'));
        // authorization through auth2.mspchallenge.info using the token, but only when first obtained through GET
        if (empty($request->getSession()->get('token')) && !$this->authorized($user, $request)) {
            throw new AccessDeniedHttpException(
                'You do not have permission to access this MSP Challenge Server Manager at this time.'
            );
        }
        // add token to session storage if still required
        if ($request->getSession()->get('token') !== $apiToken) {
            $request->getSession()->set('token', $apiToken);
        }
        // UserBadge parameters are purely to get Symfony to continue
        // first is a user identifier, second is the UserInterface object with which user is actually identified
        return new SelfValidatingPassport(
            new UserBadge($user->getUsername(), fn() => $user),
            [new PreAuthenticatedUserBadge()]
        );
    }

    private function authorized(User $user, Request $request): bool
    {
        try {
            $serverUUID = $this->mspServerManagerEntityManager
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
                return $value['user']['username'] === $user->getUsername();
            });
            return !$response->isEmpty();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function register(User $user, Request $request): Setting
    {
        $serverId = new Setting('server_id', uniqid('', true));
        $serverName = new Setting('server_name', $user->getUsername().'_'.date('Ymd'));
        $serverDescription = new Setting(
            'server_description',
            'This is a new MSP Challenge server installation. The administrator has not '.
            'changed this default description yet. This can be done through the ServerManager.'
        );
        $serverUuid = new Setting('server_uuid', Uuid::v4());
        $serverPassword = new Setting('server_password', (string) time());
        $this->mspServerManagerEntityManager->persist($serverId);
        $this->mspServerManagerEntityManager->persist($serverName);
        $this->mspServerManagerEntityManager->persist($serverDescription);
        $this->mspServerManagerEntityManager->persist($serverUuid);
        $this->mspServerManagerEntityManager->persist($serverPassword);
        $this->mspServerManagerEntityManager->flush();

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
            $request->getSession()->remove('token');
            throw new AccessDeniedHttpException(
                'You do not have permission to access this MSP Challenge Server Manager at this time.'
            );
        }
        // @phpstan-ignore-next-line "Call to an undefined method"
        $request->getSession()->getFlashBag()->add(
            'notice',
            'You, '.$user->getUsername().', are now the primary user of this Server Manager. '.
            'This means that you can use this application, and optionally add other users to it through '.
            '"Settings" - "User Access". Set up your first MSP Challenge session through the "New Session" button.'
        );
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
