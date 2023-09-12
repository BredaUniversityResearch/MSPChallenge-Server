<?php

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class TestCEL extends TestBase
{
    public function __construct(string $token)
    {
        parent::__construct($token);
    }

    /**
     * @TestMethod
     */
    public function GetConnections() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $response = $this->DoApiRequest("/api/cel/GetConnections", array());
        Assert::ExpectArray($response);
    }
}
