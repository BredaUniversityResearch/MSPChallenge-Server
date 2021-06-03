<?php

class API extends Base
{
    private $_db, $_payload = array(), $_return;
    public $success, $message, $count;

    public function __construct()
    {
        // This captures all PHP errors and warnings to ensure the standard return format 
        set_exception_handler(array($this, 'exceptions_handler'));
        set_error_handler(array($this, 'error_handler'));
        
        $this->setStatusFailure();

        // test database connection
        $this->_db = DB::getInstance();
        if ($this->_db->error()) throw new Exception($this->_db->errorString());
    }

    public function exceptions_handler($e) 
    {
        $message = $e->getMessage();
        if (is_a($e, "ErrorException") || is_a($e, "ParseError")) $message = $message." - on line ".$e->getLine()." of file ".$e->getFile();
        $this->setMessage($message); 
        $this->Return();
    }

    public function error_handler($errno, $errstr, $errfile, $errline )
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline); 
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

    private function prepareReturn()
    {
        $this->count = (is_array($this->_payload) && is_array(current($this->_payload))) ? count(current($this->_payload)) : 0;
        $this->_return = getPublicObjectVars($this);
        $this->_return += $this->_payload;
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