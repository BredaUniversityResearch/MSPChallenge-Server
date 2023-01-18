<?php

namespace App\Domain\API\v1;

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\Setting;
use Exception;
use stdClass;

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

    private function getJsonWebTokenObject(): array
    {
        // get a temp JWT from the Authoriser for further communication
        $manager = SymfonyToLegacyHelper::getInstance()->getEntityManager();
        $serverID = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_id']);
        $serverPass = $manager->getRepository(Setting::class)->findOneBy(['name' => 'server_password']);
        return json_decode(
            $this->CallBack(
                Config::getInstance()->GetAuthJWTRetrieval(),
                array(
                    "username" => $serverID->getValue(),
                    "password" => $serverPass->getValue()
                ),
                array(), // no headers
                false, // synchronous, so wait
                true // post as json
            ),
            true
        );
    }

    /**
     * @throws Exception
     */
    public function authenticate(string $username, string $password): string
    {
        // use the jwt to authenticate the provided username and password
        $userCheckReturn = json_decode($this->CallBack(
            Config::getInstance()->GetAuthJWTRetrieval(),
            array(
                "username" => $username,
                "password" => $password
            ),
            array(), // no headers
            false,  // synchronous, so wait
            true // post as json
        ), true);

        return true; //if authentication failed, an exception would have been thrown
    }

    /**
     * @throws Exception
     * @noinspection SpellCheckingInspection
     */
    public function checkuser(string $input): array
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

        $usercheckReturn = json_decode($this->CallBack(
            sprintf(
                '%s?%s',
                Config::getInstance()->GetAuthJWTUserCheck(),
                http_build_query(['email' => $inputArray])
            ),
            array(),
            array('Authorization: Bearer '.$jwt)
        ), true);

        $usercheckReturn2 = json_decode($this->CallBack(
            sprintf(
                '%s?%s',
                Config::getInstance()->GetAuthJWTUserCheck(),
                http_build_query(['username' => $inputArray])
            ),
            array(),
            array('Authorization: Bearer '.$jwt)
        ), true);

        $usercheckReturnTotal =
            array_merge($usercheckReturn['hydra:member'] ?? [], $usercheckReturn2['hydra:member'] ?? []);

        if (empty($usercheckReturnTotal)) {
            return ['found' => '', 'notfound' => $input];
        }
        $notfound = $inputArray;
        $found = [];
        foreach ($usercheckReturnTotal as $user) {
            $notfound = array_diff($notfound, [strtolower($user['username']), strtolower($user['email'])]);
            $found[] = $user['username'];
        }
        return [
            "found" => implode("|", array_unique($found)),
            "notfound" => implode("|", array_unique($notfound))
        ];
    }
}
