<?php

namespace ServerManager;

use ErrorException;
use JetBrains\PhpStorm\NoReturn;

class FileDownloader
{
    public $message;
    public $fileVar;

    public function __construct()
    {
        // This captures all PHP errors and warnings to ensure the standard return format
        set_exception_handler(array($this, 'exceptions_handler'));
        set_error_handler(array($this, 'error_handler'));
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[NoReturn] public function exceptions_handler($e): void
    {
        $this->message = $e->getMessage();
        if (is_a($e, "ErrorException")) {
            $this->message = $e->getSeverity().": ".$this->message." - on line ".$e->getLine()." of file ".
                $e->getFile();
        }
        $this->Return();
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function error_handler($errno, $errstr, $errfile, $errline)
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function printReturn(): void
    {
        if (!empty($this->message)) {
            echo $this->message;
        } else {
            header('Content-Type: application/x-download');
            if (is_array($this->fileVar)) {
                header("Content-Disposition: attachment; filename=".$this->fileVar[0].";");
                header('Content-Length: ' . strlen($this->fileVar[1]));
                print($this->fileVar[1]);
            } elseif (file_exists($this->fileVar)) {
                header("Content-Disposition: attachment; filename=".basename($this->fileVar).";");
                header('Content-Length: ' . filesize($this->fileVar));
                readfile($this->fileVar);
            } else {
                echo "Cannot make heads or tails of this file.";
            }
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    #[NoReturn] public function Return(): void
    {
        $this->printReturn();
        die();
    }
}
