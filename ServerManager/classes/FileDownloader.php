<?php

class FileDownloader
{
    public $message, $file_var;

    public function __construct()
    {
        // This captures all PHP errors and warnings to ensure the standard return format 
        set_exception_handler(array($this, 'exceptions_handler'));
        set_error_handler(array($this, 'error_handler'));
    }

    public function exceptions_handler($e) 
    {
        $this->message = $e->getMessage();
        if (is_a($e, "ErrorException")) $this->message = $e->getSeverity().": ".$this->message." - on line ".$e->getLine()." of file ".$e->getFile();
        $this->Return();
    }

    public function error_handler($errno, $errstr, $errfile, $errline )
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline); 
    }

    public function printReturn()
    {
        if (!empty($this->message)) echo $this->message;
        else
        {
            header('Content-Type: application/x-download');
            if (is_array($this->file_var)) 
            {
                header("Content-Disposition: attachment; filename=".$this->file_var[0].";");
                header('Content-Length: ' . strlen($this->file_var[1]));
                print($this->file_var[1]);
            } 
            elseif (file_exists($this->file_var)) 
            {
                header("Content-Disposition: attachment; filename=".basename($this->file_var).";");
                header('Content-Length: ' . filesize($this->file_var));
                readfile($this->file_var);
            } 
            else
            {
                echo "Cannot make heads or tails of this file.";
            }
        }
    }

    public function Return()
    {
        $this->printReturn();
        die();
    }
}