<?php

namespace App\DataCollector;

use Exception;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MSPDataCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        try {
            $serverManager = \ServerManager\ServerManager::getInstance();
        } catch (Exception $e) {
            return;
        }
        $address = $serverManager->GetTranslatedServerURL();
        if ($address == "localhost") {
            $address .= PHP_EOL . "Translated automatically to ".gethostbyname(gethostname());
        }

        $this->data = [
            'MSP Challenge Server version' => $serverManager->GetCurrentVersion(),
            'Server Address' => $address,
            // e.g.:
            //'currentToken' => \ServerManager\Session::get('currentToken'),
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }
}
