<?php
namespace App\Security;

use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Validator;

class BearerTokenValidator
{

    private string $token;

    private Token $unencryptedToken;

    public function __construct(string $token = '')
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function setTokenFromHeader(string $headerValue): self
    {
        $this->token = str_replace('Bearer ', '', $headerValue);

        return $this;
    }

    public function validate(): bool
    {
        try {
            // this might throw an exception because token is not a valid JWT, or just non-existent
            $this->unencryptedToken = (new Parser(new JoseEncoder()))->parse($this->token);
            // this might throw an exception because token is no longer valid
            (new Validator())->assert(
                $this->unencryptedToken,
                new LooseValidAt(new FrozenClock(new \DateTimeImmutable()))
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getClaims(): Token\DataSet
    {
        // @phpstan-ignore-next-line 'Call to an undefined method Lcobucci\JWT\Token::claims()'
        return $this->unencryptedToken->claims();
    }
}
