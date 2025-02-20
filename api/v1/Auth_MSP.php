<?php

namespace App\Domain\API\v1;

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\Setting;
use Exception;

/**
 * when you create your own authentication provider subclass, make sure you define these methods:
 * public function getName();
 * public function authenticate($username, $password);
 * just like in the default MSP Challenge authentication provider below.
 *
 * @noinspection PhpUnused
 */
// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class Auth_MSP extends Auths
{
    private string $name = 'MSP Challenge';

    public function getName(): string
    {
        return $this->name;
    }

    private function loginWithMSPChallengeAuth($username, $password): array
    {
        return json_decode($this->CallBack(
            Config::getInstance()->GetAuthJWTRetrieval(),
            array(
                "username" => $username,
                "password" => $password
            ),
            array(), // no headers
            false,  // synchronous, so wait
            true // post as json
        ), true);
    }

    private function getJsonWebTokenObject(): array
    {
        // get a temp JWT from the Authoriser for further communication
        $manager = SymfonyToLegacyHelper::getInstance()->getEntityManager();
        $serverID = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_id']);
        $serverPass = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_password']);
        return $this->loginWithMSPChallengeAuth($serverID->getValue(), $serverPass->getValue());
    }

    /**
     * @throws Exception
     */
    public function authenticate(string $username, string $password): bool
    {
        $this->loginWithMSPChallengeAuth($username, $password);
        return true; //if authentication failed, an exception would have been thrown
    }

    /**
     * @throws Exception
     * @noinspection SpellCheckingInspection
     */
    public function checkUser(string $input): array
    {
        $input = strtolower($input);
        $jwtReturn = $this->getJsonWebTokenObject();
        if (isset($jwtReturn['code'])) {
            throw new Exception(
                "Could not authenticate through ".$this->getName().
                ": ".$jwtReturn['message']
            );
        }
        $jwt = $jwtReturn['token'] ?? '';

        // use the jwt to check the sent username and password at the Authoriser
        $inputArray = explode("|", $input);

        $userEmailCheckReturn = json_decode($this->CallBack(
            sprintf(
                '%s?%s',
                Config::getInstance()->GetAuthJWTUserCheck(),
                http_build_query(['email' => $inputArray])
            ),
            array(),
            array('Authorization: Bearer '.$jwt)
        ), true);

        $userNameCheckReturn = json_decode($this->CallBack(
            sprintf(
                '%s?%s',
                Config::getInstance()->GetAuthJWTUserCheck(),
                http_build_query(['username' => $inputArray])
            ),
            array(),
            array('Authorization: Bearer '.$jwt)
        ), true);

        $userTotalCheck =
            array_merge($userEmailCheckReturn['hydra:member'] ?? [], $userNameCheckReturn['hydra:member'] ?? []);

        // since Auth2 API only returns usernames (for understandable privary/security reasons)
        // when a user entered email addresses, we cannot know which of those were not found
        // so, for now, if *anything* was found, keep 'notfound' simply empty
        if (empty($userTotalCheck)) {
            return ['found' => '', 'notfound' => $input];
        }
        foreach ($userTotalCheck as $user) {
            $found[] = $user['username'];
        }
        return [
            'found' => implode('|', array_unique($found)),
            'notfound' => ''
        ];
    }
}
