<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function App\resolveOnFutureTick;
use function Clue\React\Block\await;

class Security extends Base
{
    const ACCESS_LEVEL_FLAG_FULL = 0x7FFFFFFF;
    const ACCESS_LEVEL_FLAG_NONE = 0;
    const ACCESS_LEVEL_FLAG_REQUEST_TOKEN = (1 << 0);
    const ACCESS_LEVEL_FLAG_SERVER_MANAGER = (1 << 1);

    const DEFAULT_TOKEN_LIFETIME_SECONDS = 5 * 60;
    const DEFAULT_TOKEN_RENEWAL_TIME = 1 * 60;
    const TOKEN_LIFETIME_INFINITE = -1;
    const TOKEN_DELETE_AFTER_TIME = self::DEFAULT_TOKEN_LIFETIME_SECONDS + 30 * 60;

    private const DISABLE_SECURITY_CHECK = false;

    private const ALLOWED = array(
        ["RequestToken", Security::ACCESS_LEVEL_FLAG_REQUEST_TOKEN],
        ["CheckAccess", Security::ACCESS_LEVEL_FLAG_NONE]
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
     * @apiGroup Security
     * @throws Exception
     * @api {POST} /security/CheckAccess CheckAccess
     * @apiDescription Checks if the the current access token is valid to access a certain level.
     *   Currently only checks for full access tokens.
     * @apiSuccess Returns json object indicating status of the current token.
     *   { "status": ["Valid"|"UpForRenewal"|"Expired"] }
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CheckAccess(string $scope = ""): array
    {
        $scope = ($scope == "ServerManager") ? self::ACCESS_LEVEL_FLAG_SERVER_MANAGER : self::ACCESS_LEVEL_FLAG_FULL;
        $accessTimeRemaining = 0;
        $hasAccess = $this->validateAccess($scope, $accessTimeRemaining);

        $result = "Expired";
        if ($hasAccess) {
            $result = ($accessTimeRemaining <= self::DEFAULT_TOKEN_RENEWAL_TIME)? "UpForRenewal" : "Valid";
        }

        return array("status" => $result, "time_remaining" => $accessTimeRemaining);
    }

    /**
     * @apiGroup Security
     * @throws Exception
     * @api {POST} /security/RequestToken RequestToken
     * @apiDescription Requests a new access token for the API.
     * @apiParam expired_token OPTIONAL A previously used access token that is now expired.
     *   Needs a valid REQUEST_ACCESS token to be sent with the request before it generates a new token with the same
     *   access as the expired token.
     * @apiSuccess Returns json object indicating success and the token containing token identifier and unix timestap
     *   for until when it's valid. { "success": [0|1], "token": { "token": [identifier], "valid_until": [timestamp] } }
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RequestToken(string $expired_token = ""): array
    {
        $token = null;

        $requestToken = $this->getCurrentRequestTokenDetails();
        if ($requestToken != null) {
            if ($this->TokenHasAccess($requestToken, self::ACCESS_LEVEL_FLAG_FULL)) {
                $token = $this->GenerateToken();
            } else {
                if (!empty($expired_token)) {
                    $expiredTokenDetails = $this->getTokenDetails($expired_token);
                    if ($expiredTokenDetails != null) {
                        $token = $this->GenerateToken($expiredTokenDetails['api_token_scope']);
                    }
                }
            }
        }
        if ($token == null) {
            throw new Exception("Request token failed");
        }

        return $token;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function generateTokenAsync(
        int $accessLevel = self::ACCESS_LEVEL_FLAG_FULL,
        int $lifetimeSeconds = self::DEFAULT_TOKEN_LIFETIME_SECONDS
    ): PromiseInterface {
        return $this->getAsyncDatabase()->query(
            $this->getAsyncDatabase()->createQueryBuilder()
                ->delete('api_token')
                ->where('api_token_valid_until != 0')
                ->andWhere('api_token_valid_until < DATE_ADD(NOW(), INTERVAL -? SECOND)')
                ->setParameters([self::TOKEN_DELETE_AFTER_TIME])
        )
        ->then(function (/*Result $result*/) use ($lifetimeSeconds, $accessLevel) {
            $token = random_int(0, PHP_INT_MAX);
            if ($lifetimeSeconds == self::TOKEN_LIFETIME_INFINITE) {
                return $this->getAsyncDatabase()->insert(
                    'api_token',
                    [
                        'api_token_token' => $token,
                        'api_token_scope' => $accessLevel,
                        'api_token_valid_until' => 0
                    ]
                );
            } else {
                return $this->getAsyncDatabase()->query(
                    $this->getAsyncDatabase()->createQueryBuilder()
                        ->insert('api_token')
                        ->values([
                            'api_token_token' => $token,
                            'api_token_scope' => $accessLevel,
                            'api_token_valid_until' => 'DATE_ADD(NOW(), INTERVAL ? SECOND)'
                        ])
                        ->setParameters([$lifetimeSeconds])
                );
            }
        })
        ->then(function (Result $result) {
            $id = $result->getLastInsertedId() ?? 0;
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select('api_token_token', 'api_token_valid_until')
                    ->from('api_token')
                    ->where('api_token_id = ' . $qb->createPositionalParameter($id))
            );
        })
        ->then(function (Result $result) {
            $row = $result->fetchFirstRow();
            return [
                'token' => $row['api_token_token'] ?? null,
                'valid_until' => $row['api_token_valid_until'] ?? null
            ];
        });
    }

    /**
     * Returns array of [token => TokenValue, valid_until => UnixTimestamp]
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GenerateToken(
        int $accessLevel = self::ACCESS_LEVEL_FLAG_FULL,
        int $lifetimeSeconds = self::DEFAULT_TOKEN_LIFETIME_SECONDS
    ): array {
        return await($this->generateTokenAsync($accessLevel, $lifetimeSeconds));
    }

    /**
     * Returns array of [token => TokenValue]
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetRecoveryToken(): array
    {
        return $this->GetSpecialToken(self::ACCESS_LEVEL_FLAG_REQUEST_TOKEN);
    }

    /**
     * Returns array of [token => TokenValue]
     *
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetServerManagerToken(): array
    {
        return $this->GetSpecialToken(self::ACCESS_LEVEL_FLAG_SERVER_MANAGER);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public function getSpecialTokenAsync(int $accessLevel): PromiseInterface
    {
        $deferred = new Deferred();
        if ($accessLevel == self::ACCESS_LEVEL_FLAG_REQUEST_TOKEN ||
            $accessLevel == self::ACCESS_LEVEL_FLAG_SERVER_MANAGER) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $this->getAsyncDatabase()->query(
                $qb
                    ->select('api_token_token')
                    ->from('api_token')
                    ->where('api_token_valid_until = 0')
                    ->andWhere('api_token_scope = ' . $qb->createPositionalParameter($accessLevel))
                    ->setMaxResults(1)
            )
            ->done(
                function (Result $result) use ($deferred) {
                    $row = $result->fetchFirstRow();
                    $deferred->resolve($row['api_token_token'] ?? '');
                },
                function () use ($deferred) {
                    $deferred->resolve('');
                }
            );
            return $deferred->promise();
        }
        return resolveOnFutureTick($deferred, '')->promise();
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function GetSpecialToken(int $accessLevel): array
    {
        $result['token'] = await($this->getSpecialTokenAsync($accessLevel));
        return $result;
    }

    /**
     * @throws Exception
     */
    private function getCurrentRequestTokenDetails(): ?array
    {
        $token = $this->getToken();
        if ($token == null) {
            return null;
        }
        return $this->getTokenDetails($token);
    }

    /**
     * @throws Exception
     */
    private function getTokenDetails(string $tokenValue): ?array
    {
        $details = Database::GetInstance($this->getGameSessionId())->query(
            "SELECT api_token_scope, UNIX_TIMESTAMP(api_token_valid_until) as expiry_time,
            UNIX_TIMESTAMP(api_token_valid_until) - UNIX_TIMESTAMP(NOW()) as valid_time_remaining
            FROM api_token WHERE api_token_token = ?
            ",
            array($tokenValue)
        );
        if (count($details) > 0) {
            return $details[0];
        }
        return null;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function TokenHasAccess(array $tokenDetails, int $accessLevel): bool
    {
        if (($tokenDetails["api_token_scope"] & $accessLevel) == $accessLevel) {
            return true;
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function validateAccess(
        int $requiredAccessLevelFlags,
        int &$tokenValidTimeRemaining = null,
        ?string $token = null
    ): bool {
        if (self::DISABLE_SECURITY_CHECK) {
            $tokenValidTimeRemaining = self::DEFAULT_TOKEN_LIFETIME_SECONDS;
            return true;
        }

        if ($requiredAccessLevelFlags == self::ACCESS_LEVEL_FLAG_NONE) {
            return true;
        }

        $tokenDetails = null === $token ?
            $this->getCurrentRequestTokenDetails() : $this->getTokenDetails($token);
        if ($tokenDetails == null) {
            return false;
        }

        if (!$this->TokenHasAccess($tokenDetails, $requiredAccessLevelFlags)) {
            return false;
        }

        if ($tokenDetails["valid_time_remaining"] < 0 && $tokenDetails["expiry_time"] > 0) {
            return false;
        }

        if ($tokenValidTimeRemaining !== null) {
            $tokenValidTimeRemaining = $tokenDetails["valid_time_remaining"];
        }
        return true;
    }
}
