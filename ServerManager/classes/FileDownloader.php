<?php

namespace ServerManager;

class FileDownloader
{
    public function __construct(private readonly string|array $fileVar)
    {
    }

    private function printReturn(): void
    {
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

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Return(): never
    {
        $this->printReturn();
        die();
    }
}
