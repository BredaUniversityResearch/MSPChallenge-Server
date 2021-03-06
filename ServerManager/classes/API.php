<?php

class API extends Base
{
    private $_db, $_payload = array(), $_return;
    public $success, $message, $count;

    public function __construct()
    {
        $this->setStatusFailure();

        // test database connection
        $this->_db = DB::getInstance();
        if ($this->_db->error()) throw new ServerManagerAPIException($this->_db->errorString());
    }

    public function setStatusSuccess()
    {
        $this->success = true;
        return true;
    }

    public function setStatusFailure()
    {
        $this->success = false;
        return true;
    }

    public function setMessage($message)
    {
        $this->message = $message;
        return true;
    }

    public function setPayload($payload)
    {
        if (is_array($payload)) 
        {
            $this->_payload = $this->_payload + $payload;
            return true;
        }
        return false;
    }

    // needs to be public now, it is used by ExceptionListener
    public function prepareReturn(): array
    {
        $this->count = (is_array($this->_payload) && is_array(current($this->_payload))) ? count(current($this->_payload)) : 0;
        $this->_return = getPublicObjectVars($this);
        $this->_return += $this->_payload;
        return $this->_return;
    }

    public function printReturn()
    {
        header('Content-type: application/json');
        $this->prepareReturn();
        echo json_encode($this->_return);
    }

    public function Return()
    {
        $this->printReturn();
        die();
    }
}