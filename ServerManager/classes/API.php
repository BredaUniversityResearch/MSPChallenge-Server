<?php

namespace ServerManager;

use JetBrains\PhpStorm\NoReturn;

class API extends Base
{
    private ?DB $db = null;
    private array $payload = [];
    private array $return = [];
    public bool $success = false;
    public string $message = '';
    public int $count = 0;

    public function __construct()
    {
        $this->setStatusFailure();

        // test database connection
        $this->db = DB::getInstance();
        if ($this->db->error()) {
            throw new ServerManagerAPIException($this->db->errorString());
        }
    }

    public function setStatusSuccess(): bool
    {
        $this->success = true;
        return true;
    }

    public function setStatusFailure(): bool
    {
        $this->success = false;
        return true;
    }

    public function setMessage(string $message): bool
    {
        $this->message = $message;
        return true;
    }

    public function setPayload($payload): bool
    {
        if (is_array($payload)) {
            $this->payload = $this->payload + $payload;
            return true;
        }
        return false;
    }

    // needs to be public now, it is used by ExceptionListener
    public function prepareReturn(): array
    {
        $this->count = (is_array(current($this->payload))) ?
            count(current($this->payload)) : 0;
        $this->return = getPublicObjectVars($this);
        $this->return += $this->payload;
        return $this->return;
    }

    public function printReturn()
    {
        header('Content-type: application/json');
        $this->prepareReturn();
        echo json_encode($this->return);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[NoReturn] public function Return(): void
    {
        $this->printReturn();
        die();
    }
}
