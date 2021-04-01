<?php

class API 
{
    private $_status, $_message, $_count, $_payload, $_return;

    public function __construct()
    {
        $this->setStatusFailure();
        $this->setPayLoad(array());
        header('Content-type: application/json');
    }

    public function setStatusSuccess()
    {
        $this->_status = "success";
        return true;
    }

    public function setStatusFailure()
    {
        $this->_status = "error";
        return true;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function setCount($count)
    {
        $this->_count = $count;
        return true;
    }

    public function setMessage($message)
    {
        $this->_message = $message;
        return true;
    }

    public function setPayload($payload)
    {
        if (is_array($payload)) 
        {
            $this->_payload = $payload;
            return true;
        }
        return false;
    }

    private function prepareReturn()
    {
        $this->_return = array(
            "status" => $this->_status,
            "message" => $this->_message,
            "count" => (is_array(current($this->_payload))) ? count(current($this->_payload)) : 0
        );
        $this->_return = array_merge($this->_return, $this->_payload);
    }

    public function printReturn()
    {
        $this->prepareReturn();
        echo json_encode($this->_return);
        return true;
    }
}